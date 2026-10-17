/* ---------- sync rules: the decisions of the sync module (backend mode) ---------- */
/* Pure and free of browser APIs, so `npm test` runs them under plain Node. */
import { diffData, same } from './diff.js';

/* Collections and singletons written through /api/sync, in the order the server applies them: every
   record after those it names. Teams before the Members in them, Members before the Projects they lead
   or belong to and the Tasks assigned to them, Projects before their Tasks and the Project order, Tasks
   before the Comments, Activity and Files about them, and before their Events and Saved views. Notifications
   are the server's; only their `read` flag goes back. Files are made by an upload or a copy (handleFiles,
   dupFile); the sync only renames and deletes them. The Member's own Preferences (`prefs`), view settings
   (`views`), notification settings and recent searches are singletons the server keeps per Member; prefs
   and views live beside S.data in S (syncState). */
export const SYNCED = [
  'ws',
  'teams',
  'members',
  'projects',
  'tasks',
  'projOrder',
  'comments',
  'activity',
  'files',
  'notifs',
  'events',
  'savedViews',
  'prefs',
  'views',
  'notifPrefs',
  'recentSearches',
];

/** What this browser syncs: S.data with the Member's Preferences and view settings beside it. Name and title stay out
   of the Preferences: the Member record owns them (members). Views equal to `blankView(key)` (what viewOf() makes when
   a view is first rendered) stay out too, so a browser that only showed a Project or a Saved view has nothing to send. */
export function syncState(data, prefs, views, blankView) {
  const own = { ...prefs };
  delete own.name;
  delete own.title;
  return { ...data, prefs: own, views: Object.fromEntries(Object.entries(views).filter(([key, v]) => !same(v, blankView(key)))) };
}

/** The state to start from after signing in: the server's (`server`, in the shape of syncState()). Where the server keeps
   no Preferences or no view settings for the Member yet, as on their first sign-in in backend mode, this browser's
   (`local`) stay, and the sync sends them; but only when they were this Member's (`localOwner`: the Member they were
   last synced for, null when not known). */
export function signedInState(server, local, localOwner) {
  if (localOwner && localOwner !== server.me) return server;
  const empty = o => !o || !Object.keys(o).length;
  return { ...server, prefs: empty(server.prefs) ? local.prefs : server.prefs, views: empty(server.views) ? local.views : server.views };
}

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
