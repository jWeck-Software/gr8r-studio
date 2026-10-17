/* =====================================================================
   SYNC (backend mode): the signed-in session and the Workspace on the server.
   Callers need three calls: resumeSession() at boot, signIn() from the login form,
   signOut(). Everything else happens on its own: every debounced save() diffs the synced
   collections against the copy the server last confirmed and posts the ops, one request at
   a time. A refused batch (403/422) reloads the Workspace and says why; a failed request
   (offline, 5xx) keeps the changes pending and retries on the next save or when the browser
   is back online. When the server renumbers a new Task whose key someone else took meanwhile,
   the new key replaces the local one (ADR 0002, ADR 0003).
   While signed in, teammates' changes come in by a refresh when the tab is shown again or the
   window regains focus, and every 60 s while the tab is visible: pending changes are sent first,
   and the snapshot replaces the synced collections only when nothing is unsynced afterwards and
   the user is not in the middle of an edit (sync-rules.js).
   ===================================================================== */
import { BACKEND, api } from '../core/api.js';
import { diffData } from '../core/diff.js';
import { midEdit, refreshed } from '../core/sync-rules.js';
import { S, onSave, task } from '../core/store.js';
import { toast } from '../ui/toast.js';
import { render } from '../shell/render.js';
import { DRAG } from './drag-drop.js';

/* Collections and singletons written through /api/sync, in the order the server applies them:
   Projects before the Tasks and the Project order that refer to them, Tasks before the Comments
   and Activity about them. Notifications are the server's; only their `read` flag goes back.
   Grows ticket by ticket. */
export const SYNCED = ['projects', 'tasks', 'projOrder', 'comments', 'activity', 'notifs'];

let synced = null; // what the server last confirmed, as a deep copy; null while signed out
let session = 0; // bumped on every sign-in and sign-out, so late answers of an old session are dropped
let inFlight = null; // the running push, if any: never two sync requests at once
let again = false; // a save came in while a push was running
let sends = 0; // counts the batches sent, so a refresh sees whether one went out while it was fetching
let failing = false; // the last push could not reach the server
let leaving = Promise.resolve(); // a sign-out still talking to the server
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
export async function signIn(email, password) {
  await leaving;
  await api.login(email, password);
  await loadWorkspace();
}

/** Sends what is still pending, then ends the session on the server. */
export function signOut() {
  unwatch();
  leaving = (async () => {
    await settle();
    synced = null;
    session++;
    await api.logout().catch(() => {});
  })();
  return leaving;
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

/* ---------- refresh: teammates' changes ---------- */

function watch() {
  if (poll) return;
  poll = setInterval(refresh, REFRESH_MS);
  addEventListener('focus', refresh);
  document.addEventListener('visibilitychange', refresh);
  document.addEventListener('input', noteTyping, true);
}

function unwatch() {
  clearInterval(poll);
  poll = 0;
  removeEventListener('focus', refresh);
  document.removeEventListener('visibilitychange', refresh);
  document.removeEventListener('input', noteTyping, true);
}

function noteTyping(e) {
  typedIn = e.target;
}

/* A field counts as typed into while it keeps focus; render() replaces the fields it redraws from S.ui. */
function stillEditing() {
  const a = document.activeElement;
  const typing = a && a === typedIn ? (a.dataset?.in === 'draft' ? 'draft' : 'field') : null;
  return midEdit(S.ui, { typing, dragging: !!DRAG });
}

function refresh() {
  if (document.visibilityState !== 'visible' || stillEditing()) return;
  pull(() => !stillEditing());
}

/* One refresh at a time: send what is pending, fetch the snapshot, and apply it only when nothing
   is unsynced (a failed send, or an edit while the request was out), nothing was sent meanwhile,
   and `mayApply()` still holds. */
function pull(mayApply) {
  refreshing ||= (async () => {
    const s = session;
    try {
      if (inFlight || pending()) await settle();
      if (s !== session || pending()) return;
      const sent = sends;
      const { data } = await api.bootstrap();
      // A batch sent meanwhile may be missing from this snapshot: the next refresh brings it.
      if (s !== session || sends !== sent || inFlight || pending() || !mayApply()) return;
      const next = refreshed(S.data, data, SYNCED);
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
    again = true;
    return;
  }
  inFlight = send().finally(() => {
    inFlight = null;
    if (again) {
      again = false;
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
  addEventListener('online', push);
}
