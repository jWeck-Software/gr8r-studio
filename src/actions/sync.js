/* =====================================================================
   SYNC (backend mode): the signed-in session and the Workspace on the server.
   Callers need three calls: resumeSession() at boot, signIn() from the login form,
   signOut(). Everything else happens on its own: every debounced save() diffs the synced
   collections against the copy the server last confirmed and posts the ops, one request at
   a time. A refused batch (403/422) reloads the Workspace and says why; a failed request
   (offline, 5xx) keeps the changes pending and retries on the next save or when the browser
   is back online. ADR 0002.
   ===================================================================== */
import { BACKEND, api } from '../core/api.js';
import { diffData } from '../core/diff.js';
import { S, onSave, task } from '../core/store.js';
import { toast } from '../ui/toast.js';
import { render } from '../shell/render.js';

/* Collections written through /api/sync, in the order the server applies them. Grows ticket by ticket. */
export const SYNCED = ['tasks'];

let synced = null; // what the server last confirmed, as a deep copy; null while signed out
let session = 0; // bumped on every sign-in and sign-out, so late answers of an old session are dropped
let inFlight = null; // the running push, if any: never two sync requests at once
let again = false; // a save came in while a push was running
let failing = false; // the last push could not reach the server
let leaving = Promise.resolve(); // a sign-out still talking to the server

const copyOf = data => JSON.parse(JSON.stringify(Object.fromEntries(SYNCED.map(c => [c, data[c] || []]))));

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
  try {
    await api.sync(ops);
    if (s !== session) return;
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

async function reloadAfterRefusal(message) {
  try {
    await loadWorkspace();
  } catch {
    /* unreachable now: the refused changes stay local and are sent (and refused) again on the next save */
  }
  if (S.ui.drawer && !task(S.ui.drawer)) S.ui.drawer = null;
  for (const id of S.ui.sel) if (!task(id)) S.ui.sel.delete(id);
  render();
  toast(`Your last change was undone: ${message}`, { kind: 'err', ms: 7000 });
}

function sessionEnded() {
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
