/* =====================================================================
   SYNC (backend mode): the signed-in session and the Workspace on the server.
   Callers need nine calls: resumeSession() at boot, and signIn() from the login form,
   acceptInvite() from the page an Invite email links to (#join/<token>), verifyEmail() from the one
   a verification email links to (#verify/<token>), resetPassword() from the one a password reset
   email links to (#reset/<token>), startWorkspace() at the end of onboarding, deleteWorkspace()
   from the settings, signOut() and flush() (before an upload, which names a Task the server must
   know), which reach this module through store.js (onSession). A User signed in without a Workspace
   (just verified, or removed from theirs) has nothing to load: signIn(), verifyEmail(),
   resetPassword() and resumeSession() say so, and the caller sends them to onboarding, whose
   startWorkspace() makes one and loads it. When the Workspace goes while signed in (deleted by its
   Owner, or the Member removed), the next sync or refresh is 403 no_workspace: the sync stops and
   onboarding opens (workspaceGone()), as it does for the Owner who deleted it. Everything else happens on its own: every debounced save() diffs the synced
   collections, and the Member's Preferences and view settings beside S.data (syncState), against
   the copy the server last confirmed and posts the ops, one request at a time. When the server
   keeps no Preferences or view settings for the Member yet, the sign-in keeps this browser's and
   sends them (signedInState). A refused batch (403/422) reloads the Workspace and says why; a failed request
   (offline, 5xx) keeps the changes pending and retries on the next save or when the browser
   is back online. When the server renumbers a new Task whose key someone else took meanwhile,
   the new key replaces the local one (ADR 0002, ADR 0003).
   While signed in, teammates' changes come in by a refresh when the tab is shown again or the
   window regains focus, and every 60 s while the tab is visible: pending changes are sent first,
   and the snapshot replaces the synced collections only when nothing is unsynced afterwards and
   the user is neither in the middle of an edit nor able to undo (sync-rules.js). An expired
   session (401) keeps the unsynced changes and shows the login screen; signing in again as the
   same Member sends them, another Member signing in discards them.
   ===================================================================== */
import { BACKEND, api } from '../core/api.js';
import { FIRST_SEQ } from '../core/constants.js';
import { diffData } from '../core/diff.js';
import { SYNCED, afterSignIn, midEdit, mergeSnapshot, signedInState, syncState } from '../core/sync-rules.js';
import { DEFAULT_PREFS, S, me, onSave, onSession, ownPrefs, prefsOwnerIs, task } from '../core/store.js';
import { toast } from '../ui/toast.js';
import { render } from '../shell/render.js';
import { blankView } from '../shell/view-engine.js';
import { beginOnboarding } from './actions.js';

let synced = null; // what the server last confirmed, as a deep copy; null while signed out
let session = 0; // bumped on every sign-in and sign-out, so late answers of an old session are dropped
let inFlight = null; // the running push, if any: never two sync requests at once
let pushAgain = false; // a save came in while a push was running
let sends = 0; // counts the batches sent, so a refresh sees whether one went out while it was fetching
let failing = false; // the last push could not reach the server
let signingOut = Promise.resolve(); // a sign-out still talking to the server
let leftover = null; // unsynced changes of a session that ended: { member, name, synced }, sent after signing in again as that Member
let refreshing = null; // the running refresh, if any
let poll = 0; // the 60 s refresh timer while signed in
let typedIn = null; // the field last typed into: its text may exist only in the page

const REFRESH_MS = 60_000;
const state = () => syncState(S.data, S.prefs, S.views, blankView);
const copyOf = st => JSON.parse(JSON.stringify(Object.fromEntries(SYNCED.map(c => [c, st[c]]))));
const pending = () => !!synced && diffData(synced, copyOf(state()), SYNCED).length > 0;
const snapshotState = ({ data, prefs, views }) => ({ ...data, prefs, views });

/* Puts a synced state into S: S.data, and the Member's prefs and views beside it. */
function adopt(st) {
  const { prefs, views, ...data } = st;
  S.data = data;
  S.prefs = { ...DEFAULT_PREFS, ...prefs };
  S.views = views;
  ownPrefs(data.me);
}

/** Boot: 'in' when a session exists and its Workspace is loaded, 'out' when nobody is signed in,
    'setup' when the User signed in has no Workspace. Rejects with an ApiError otherwise. */
export async function resumeSession() {
  try {
    await api.me();
  } catch (e) {
    if (e.status === 401) return 'out';
    if (e.code === 'no_workspace') return 'setup';
    throw e;
  }
  await loadWorkspace();
  return 'in';
}

/** Signs in and loads the Workspace; resolves with the Member, or null for a User without a Workspace.
    Rejects with an ApiError whose message can be shown as is (`code` 'unverified' before the address is verified). */
async function signIn(email, password) {
  await signingOut;
  const { member } = await api.login(email, password);
  if (member) await enter(member);
  return member;
}

/** Verifies the email address of the link's token, which signs in; resolves like signIn(). */
async function verifyEmail(token) {
  await signingOut;
  const { member } = await api.verifyEmail(token);
  if (member) await enter(member);
  return member;
}

/** Sets a new password with the token of a reset link, which signs in; resolves like signIn(). */
async function resetPassword(token, password) {
  await signingOut;
  const { member } = await api.resetPassword(token, password);
  if (member) await enter(member);
  return member;
}

/** Deletes the signed-in Owner's Workspace, and leaves it as everyone in it does (workspaceGone()). Rejects with an ApiError. */
async function deleteWorkspace(name) {
  await api.deleteWorkspace(name);
  workspaceGone();
}

/** Starts the signed-in User's Workspace as its Owner and loads it. Rejects with an ApiError (422: the URL is taken). */
async function startWorkspace(name, url, c) {
  const { member } = await api.startWorkspace(name, url, c);
  await enter(member);
}

/** Accepts an Invite, which signs in as the new Member, and loads the Workspace. Rejects like signIn(). */
async function acceptInvite(token, name, password) {
  await signingOut;
  const { member } = await api.acceptInvite(token, name, password);
  await enter(member);
}

/* Signed in as this Member: send what a session that ended left unsynced when it was theirs, then load the Workspace. */
async function enter(member) {
  const was = leftover;
  leftover = null;
  const next = afterSignIn(was, member);
  if (next === 'resend') return resend(was.synced);
  await loadWorkspace();
  if (next === 'discard')
    toast(`Changes ${was.name || 'the previous Member'} had not synced yet were discarded, because a different Member signed in.`, { kind: 'err', ms: 8000 });
}

/** Sends what is still pending, then ends the session on the server. */
function signOut() {
  unwatch();
  signingOut = (async () => {
    await settle();
    if (synced) keepLeftover();
    synced = null;
    session++;
    await api.logout().catch(() => {});
  })();
  return signingOut;
}

/* `keepLocal`: on signing in, keep this browser's Preferences and views where the server has none for the Member yet. */
async function loadWorkspace({ keepLocal = true } = {}) {
  const s = ++session;
  const server = snapshotState(await api.bootstrap());
  if (s !== session) return;
  adopt(keepLocal ? signedInState(server, state(), prefsOwnerIs()) : server);
  synced = copyOf(server);
  failing = false;
  watch();
  // Sends this browser's prefs and views when the server had none for the Member (and fills in DEFAULT_PREFS) at once,
  // before the user's first change; the next save() keeps in localStorage whose they are.
  push();
}

/* Signed in again as the Member whose session ended: S.data still holds their changes, so diffing
   against what the server last confirmed for them sends exactly those; then catch up with the team. */
async function resend(base) {
  session++;
  synced = base;
  failing = false;
  watch();
  await settle();
  await refresh(() => true);
}

/* Keeps the unsynced changes of a session that is ending, for when its Member signs in again. */
function keepLeftover() {
  leftover = pending() ? { member: S.data.me, name: me()?.name, synced } : null;
}

/* ---------- refresh: teammates' changes ---------- */

function watch() {
  if (poll) return;
  poll = setInterval(refreshIfIdle, REFRESH_MS);
  addEventListener('focus', refreshIfIdle);
  document.addEventListener('visibilitychange', refreshIfIdle);
  document.addEventListener('input', noteTyping, true);
}

function unwatch() {
  clearInterval(poll);
  poll = 0;
  removeEventListener('focus', refreshIfIdle);
  document.removeEventListener('visibilitychange', refreshIfIdle);
  document.removeEventListener('input', noteTyping, true);
}

function noteTyping(e) {
  typedIn = e.target;
}

/* A field counts as typed into while it keeps focus; render() replaces the fields it redraws from S.ui. */
function stillEditing() {
  const a = document.activeElement;
  const typing = a && a === typedIn ? (a.dataset?.in === 'draft' ? 'draft' : 'field') : null;
  return midEdit(S.ui, { typing });
}

function refreshIfIdle() {
  if (document.visibilityState !== 'visible' || stillEditing()) return;
  refresh(() => !stillEditing());
}

/* Brings in teammates' changes, one refresh at a time: send what is pending, fetch the snapshot,
   and apply it only when nothing is unsynced (a failed send, or an edit while the request was out),
   nothing was sent meanwhile, and `mayApply()` still holds. refreshIfIdle() is what focus, tab
   visibility and the poll call. */
function refresh(mayApply) {
  refreshing ||= (async () => {
    const s = session;
    try {
      if (inFlight || pending()) await settle();
      if (s !== session || pending()) return;
      const sent = sends;
      const server = snapshotState(await api.bootstrap());
      // A batch sent meanwhile may be missing from this snapshot: the next refresh brings it.
      if (s !== session || sends !== sent || inFlight || pending() || !mayApply()) return;
      const next = mergeSnapshot(state(), server, SYNCED);
      if (!next) return;
      adopt(next);
      synced = copyOf(next);
      dropVanished();
      render();
    } catch (e) {
      if (s === session && e.status === 401) sessionEnded();
      else if (s === session && e.code === 'no_workspace') workspaceGone(GONE);
      /* otherwise unreachable now: the next focus or poll tries again */
    }
  })().finally(() => (refreshing = null));
  return refreshing;
}

function push() {
  if (!synced) return;
  if (inFlight) {
    pushAgain = true;
    return;
  }
  inFlight = send().finally(() => {
    inFlight = null;
    if (pushAgain) {
      pushAgain = false;
      push();
    }
  });
}

async function settle() {
  while (inFlight) await inFlight;
  if (!synced) return;
  inFlight = send().finally(() => (inFlight = null));
  await inFlight;
}

async function send() {
  const s = session;
  const next = copyOf(state());
  const ops = diffData(synced, next, SYNCED);
  if (!ops.length) return;
  sends++;
  try {
    const res = await api.sync(ops);
    if (s !== session) return;
    if (res?.keys) applyKeys(res.keys, [S.data, next]);
    synced = next;
    if (failing) toast('Reconnected. All changes are synced.');
    failing = false;
  } catch (e) {
    if (s !== session) return;
    if (e.code === 'no_workspace') workspaceGone(GONE);
    else if (e.status === 403 || e.status === 422) await reloadAfterRefusal(e.message);
    else if (e.status === 401) sessionEnded();
    else if (!failing) {
      // Offline or a server error: `synced` stays put, so the next diff still holds these changes.
      failing = true;
      toast("Your changes aren't saved yet. They'll be sent when the connection is back.", { kind: 'err', ms: 6000 });
    }
  }
}

/* The server renumbered these new Tasks ({taskId: key}). Each copy of the data takes the new keys,
   and the Project's seq moves past them so the next new Task doesn't collide again. */
function applyKeys(keys, copies) {
  for (const data of copies) {
    for (const [id, key] of Object.entries(keys)) {
      const t = data.tasks.find(x => x.id === id);
      if (!t) continue; // deleted here in the meantime
      t.key = key;
      const p = data.projects.find(x => x.id === t.project);
      const n = Number(key.slice(key.lastIndexOf('-') + 1));
      if (p && n > (p.seq || FIRST_SEQ)) p.seq = n;
    }
  }
  render();
}

async function reloadAfterRefusal(message) {
  try {
    await loadWorkspace({ keepLocal: false });
  } catch {
    /* unreachable now: the refused changes stay local and are sent (and refused) again on the next save */
  }
  dropVanished();
  render();
  toast(`Your last change was undone: ${message}`, { kind: 'err', ms: 7000 });
}

/* The drawer and the selection let go of Tasks that are no longer there. */
function dropVanished() {
  if (S.ui.drawer && !task(S.ui.drawer)) S.ui.drawer = null;
  for (const id of S.ui.sel) if (!task(id)) S.ui.sel.delete(id);
}

/* The signed-in User is no Member of a Workspace any more: it was deleted, or they were removed (403 no_workspace).
   Nothing is left to sync into or to send later; still signed in, they go on to onboarding to start one of their own. */
function workspaceGone(message) {
  unwatch();
  synced = null;
  leftover = null;
  session++;
  S.ui.drawer = null;
  S.ui.modals = [];
  S.ui.pop = null;
  beginOnboarding();
  render();
  if (message) toast(message, { kind: 'info', ms: 8000 });
}
const GONE = 'You are no longer a Member of this Workspace: it was deleted, or you were removed from it.';

function sessionEnded() {
  keepLeftover();
  unwatch();
  synced = null;
  session++;
  S.ui.drawer = null;
  S.ui.modals = [];
  S.ui.pop = null;
  S.ui.auth = 'login';
  S.ui.errors = { login: 'Your session has ended. Sign in again to continue.' };
  render();
}

if (BACKEND) {
  onSave(push);
  onSession({ signIn, signOut, acceptInvite, verifyEmail, resetPassword, startWorkspace, deleteWorkspace, resume: resumeSession, flush: settle });
  addEventListener('online', push);
}
