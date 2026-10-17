/* =====================================================================
   SYNC (backend mode): the signed-in session and the Workspace on the server.
   Callers need four calls: resumeSession() at boot, and signIn() from the login form,
   acceptInvite() from the page an Invite email links to (#join/<token>) and signOut(), which
   reach this module through store.js (onSession). Everything else happens on its own: every debounced save() diffs the synced
   collections against the copy the server last confirmed and posts the ops, one request at
   a time. A refused batch (403/422) reloads the Workspace and says why; a failed request
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
import { diffData } from '../core/diff.js';
import { SYNCED, afterSignIn, midEdit, mergeSnapshot } from '../core/sync-rules.js';
import { S, me, onSave, onSession, task } from '../core/store.js';
import { toast } from '../ui/toast.js';
import { render } from '../shell/render.js';

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
const copyOf = data => JSON.parse(JSON.stringify(Object.fromEntries(SYNCED.map(c => [c, data[c]]))));
const pending = () => !!synced && diffData(synced, copyOf(S.data), SYNCED).length > 0;

/** Boot: true when a session exists and its Workspace is loaded, false when nobody is signed in. Rejects with an ApiError otherwise. */
export async function resumeSession() {
  try {
    await api.me();
  } catch (e) {
    if (e.status === 401) return false;
    throw e;
  }
  await loadWorkspace();
  return true;
}

/** Signs in and loads the Workspace. Rejects with an ApiError whose message can be shown as is. */
async function signIn(email, password) {
  await signingOut;
  const { member } = await api.login(email, password);
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

async function loadWorkspace() {
  const s = ++session;
  const { data } = await api.bootstrap();
  if (s !== session) return;
  // Preferences and view settings stay in this browser until they are synced too.
  S.data = data;
  synced = copyOf(data);
  failing = false;
  watch();
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
      const { data } = await api.bootstrap();
      // A batch sent meanwhile may be missing from this snapshot: the next refresh brings it.
      if (s !== session || sends !== sent || inFlight || pending() || !mayApply()) return;
      const next = mergeSnapshot(S.data, data, SYNCED);
      if (!next) return;
      S.data = next;
      synced = copyOf(next);
      dropVanished();
      render();
    } catch (e) {
      if (s === session && e.status === 401) sessionEnded();
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
  const next = copyOf(S.data);
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
    if (e.status === 403 || e.status === 422) await reloadAfterRefusal(e.message);
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
      if (p && n > (p.seq || 200)) p.seq = n;
    }
  }
  render();
}

async function reloadAfterRefusal(message) {
  try {
    await loadWorkspace();
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
  onSession({ signIn, signOut, acceptInvite });
  addEventListener('online', push);
}
