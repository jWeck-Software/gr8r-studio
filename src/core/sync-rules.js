/* ---------- sync rules: the decisions of the sync module (backend mode) ---------- */
/* Pure and free of browser APIs, so `npm test` runs them under plain Node. */
import { diffData } from './diff.js';

/** What S.data becomes when a refresh brings the server's snapshot: the synced `types` from the server, everything else (kept in this browser only so far) from `local`. Null when the server has nothing new for those types. A snapshot of another Member (someone signed in from another tab) replaces everything. */
export function mergeSnapshot(local, server, types) {
  if (server.me !== local.me) return server;
  if (!diffData(local, server, types).length) return null;
  return { ...local, ...Object.fromEntries(types.map(t => [t, server[t]])) };
}

/** Whether a refresh now would throw away something the user is in the middle of. `render()` keeps the
   route, the drawer, the selection, scroll, focus and comment drafts (S.ui.drafts), but not text that
   lives only in a field of the page, an open overlay's half-made choice, or a drag (S.ui.drag). While an undo toast
   is open (S.ui.undos), undo would put back a copy of S.data from before the refresh and so undo
   teammates' changes as well.
   `typing`: the focused field has been typed into, 'draft' for a comment draft, 'field' for any other. */
export function midEdit(ui, { typing = null } = {}) {
  return !!(ui.editCell || ui.composer || ui.modals.length || ui.pop || ui.palette || ui.auth || ui.undos > 0 || ui.drag || typing === 'field');
}

/** What a sign-in does with the changes left over from a session that ended (`leftover`: the Member they were made by, or null). */
export function afterSignIn(leftover, member) {
  if (!leftover) return 'load';
  return leftover.member === member ? 'resend' : 'discard';
}
