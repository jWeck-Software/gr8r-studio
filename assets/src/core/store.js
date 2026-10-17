/* ---------- store ---------- */
import { TODAY, diffD, iso, parse, uid } from './utils.js';
import { PCOLORS, TEAMS_SEED } from './constants.js';
import { seed } from '../data/seed.js';
import { MODE } from './mode.js';

/* What this browser keeps, and under which key, depends on the mode (core/mode.js). */
export const STORE_KEY = MODE.storage.key;
export const DEFAULT_PREFS = {
  theme: 'system',
  accent: 'indigo',
  side: 'comfortable',
  density: 'comfortable',
  weekStart: 1,
  dateFmt: 'MMM d',
  lang: 'English (US)',
  tz: '(GMT-07:00) Pacific Time',
  motion: 'system',
  home: 'home',
  openTasks: 'drawer',
};
export function load() {
  try {
    const raw = localStorage.getItem(STORE_KEY);
    if (raw) {
      const j = JSON.parse(raw);
      if (j && (!MODE.storage.keepsData || (j.data && j.data.tasks))) return j;
    }
  } catch {
    /* storage blocked or corrupt: start from seed data */
  }
  return null;
}
export const saved = load();
export const S = {
  data: (MODE.storage.keepsData && saved?.data) || seed(),
  prefs: Object.assign({}, DEFAULT_PREFS, saved?.prefs || {}),
  views: saved?.views || {},
  ui: {
    fx: {},
    auth: null,
    authStep: 'login',
    onb: 0,
    onbData: { use: 'product', team: 'kanban', size: '2-10', ws: '', proj: 'Website Launch', tmpl: 'web', invites: '' },
    route: 'home',
    params: {},
    history: [],
    collapsed: !!saved?.collapsed,
    mnav: false,
    expanded: { p1: true },
    drawer: null,
    drawerFull: false,
    drawerTab: 'comments',
    modals: [],
    pop: null,
    palette: null,
    loading: false,
    offline: false,
    sel: new Set(),
    composer: null,
    editCell: null,
    openTasks: {},
    collapsedGroups: {},
    calDate: iso(TODAY),
    calMode: 'month',
    tlZoom: 'week',
    tlGroup: 'status',
    fileView: 'grid',
    projView: 'grid',
    homeTab: 'upcoming',
    inboxCat: 'all',
    inboxUnread: false,
    inboxSel: null,
    notifFilter: 'all',
    settings: 'appearance',
    searchQ: '',
    searchCat: 'all',
    myView: 'list',
    membersTab: 'members',
    collapsedCols: {},
    errors: {},
    drafts: {},
    undos: 0, // undo toasts open now (ui/toast.js)
    drag: null, // the card, row, calendar Task or sidebar Project being dragged (actions/drag-drop.js)
  },
};
/* Backend mode: the Member whose prefs and views S holds, the one they were last synced for; null when not known
   (nobody signed in yet in this browser, or saved before this was kept). */
let prefsOwner = (MODE.storage.keepsOwner && saved?.member) || null;
export const prefsOwnerIs = () => prefsOwner;
export const ownPrefs = member => (prefsOwner = member);
export let _saveT;
let afterSave = null;
/* Backend mode's sync module registers here when it loads; it runs on every debounced save. */
export const onSave = fn => (afterSave = fn);
/* What a User does with their account, session and Workspace. Each mode hands its calls over here when
   its module loads, so the actions reach them without importing it: backend mode's sync module, which
   asks the server (it imports the shell, which imports the actions), and the demo's stand-in
   (actions/demo-account.js), which answers at once. Every call returns a Promise (the demo: a thenable
   that calls back in the same tick); a refusal rejects with an Error whose message can be shown as is. */
const account = {};
export const onSession = calls => Object.assign(account, calls);
/* Resolves with the Member signed in, or null for a User without a Workspace. */
export const signIn = (email, password) => account.signIn(email, password);
export const signOut = () => account.signOut();
export const signUp = (name, email, password) => account.signUp(name, email, password);
export const resendVerification = email => account.resendVerification(email);
/* The reset link from the form, and again from "Resend email" (which the demo answers at once). */
export const forgotPassword = email => account.forgotPassword(email);
export const resendResetLink = email => account.resendResetLink(email);
/* Resolves with 'in', or 'setup' (signed in without a Workspace). The demo's sets the password and signs nobody in. */
export const resetPassword = (token, password) => account.resetPassword(token, password);
export const changePassword = (current, next) => account.changePassword(current, next);
export const resendInvite = memberId => account.resendInvite(memberId);
/* Backend mode only: the pages Invite and verification emails link to. */
export const acceptInvite = (token, name, password) => account.acceptInvite(token, name, password);
export const verifyEmail = token => account.verifyEmail(token);
export const deleteWorkspace = name => account.deleteWorkspace(name);
/* The Workspace onboarding's answers (S.ui.onbData) describe. Resolves with what names it inside
   the change that sets it up (the demo's, see setUpWorkspace()), or undefined when that is done. */
export const startWorkspace = onb => account.startWorkspace(onb);
/* Leaving onboarding unfinished: backend mode signs out; the demo goes back into its Workspace. */
export const leaveSetup = () => account.leaveSetup();
/* 'in', 'out' or 'setup' (signed in without a Workspace), as at boot. */
export const resumeSession = () => account.resume();
/* Sends what is not synced yet, now: an upload names a Task that may be new. */
export const flushSync = () => account.flush?.() ?? Promise.resolve();
export function save() {
  clearTimeout(_saveT);
  _saveT = setTimeout(() => {
    try {
      localStorage.setItem(
        STORE_KEY,
        JSON.stringify({
          data: MODE.storage.keepsData ? S.data : undefined,
          member: prefsOwner || undefined,
          prefs: S.prefs,
          views: S.views,
          collapsed: S.ui.collapsed,
        }),
      );
    } catch {
      /* storage full or blocked: keep working in memory */
    }
    afterSave?.();
  }, 150);
}

/* ---------- lookups ---------- */
export const D = () => S.data;
export const me = () => D().members.find(m => m.id === D().me);
export const mem = id => D().members.find(m => m.id === id);
export const proj = id => D().projects.find(p => p.id === id);
export const task = id => D().tasks.find(t => t.id === id);
export const pColor = p => PCOLORS[p?.color] || PCOLORS.slate;
export const visibleProjects = () =>
  D()
    .projOrder.map(proj)
    .filter(Boolean)
    .filter(p => !p.archived);
export const canSee = p => !p.private || p.members.includes(D().me);
export const tasksOf = pid => D().tasks.filter(t => t.project === pid && !t.archived);
export const allTasks = () => D().tasks.filter(t => !t.archived && canSee(proj(t.project)));
export const isOver = t => t.due && t.status !== 'done' && diffD(parse(t.due), TODAY) < 0;
export const commentsOf = tid => D().comments.filter(c => c.task === tid);
export function progressOf(pid) {
  const ts = tasksOf(pid);
  if (!ts.length) return 0;
  return Math.round((ts.filter(t => t.status === 'done').length / ts.length) * 100);
}
export function taskProg(t) {
  if (t.status === 'done') return 100;
  if (t.subtasks.length) return Math.round((t.subtasks.filter(s => s.done).length / t.subtasks.length) * 100);
  return { backlog: 0, todo: 5, progress: 45, review: 80 }[t.status] || 0;
}

/* ---------- activity + notification helpers ---------- */
export function logAct(verb, t, extra = '') {
  D().activity.unshift({ id: uid('a'), by: D().me, verb, task: t?.id || null, project: t?.project || null, at: Date.now(), extra });
  if (t) t.updated = Date.now();
}

/* ---------- teams (workspace data, seeded on first use) ---------- */
export const NO_TEAM = { id: '', name: 'No team', icon: 'users', c: '#8A867E', desc: '' };
export function teamsList() {
  const d = D();
  if (!d.teams) d.teams = TEAMS_SEED.map(t => ({ ...t }));
  return d.teams;
}
export function team(id) {
  return teamsList().find(t => t.id === id) || null;
}
export const TM = new Proxy({}, { get: (_, k) => team(k) || NO_TEAM });
