/* =====================================================================
   ENTRY: styles, then every module in dependency order (several register
   global listeners or add handlers to the action table when they load), then boot.
   ===================================================================== */
import './styles/index.css';
import './core/utils.js';
import './core/icons.js';
import './core/constants.js';
import './data/seed.js';
import './core/store.js';
import './ui/helpers.js';
import './core/theme.js';
import './ui/toast.js';
import './ui/select.js';
import './shell/render.js';
import './shell/layout.js';
import './shell/skeletons.js';
import './shell/view-engine.js';
import './components/task-list.js';
import './pages/home.js';
import './pages/overview.js';
import './pages/my-tasks.js';
import './pages/tasks.js';
import './pages/inbox.js';
import './pages/notifications.js';
import './pages/favorites.js';
import './pages/projects.js';
import './pages/members.js';
import './pages/activity.js';
import './pages/search.js';
import './pages/errors.js';
import './pages/project.js';
import './views/project-overview.js';
import './views/board.js';
import './views/table.js';
import './views/calendar.js';
import './views/timeline.js';
import './views/files.js';
import './overlays/drawer.js';
import './overlays/modals.js';
import './overlays/popovers.js';
import './overlays/context-menu.js';
import './overlays/palette.js';
import './pages/settings.js';
import './pages/auth.js';
import './pages/design-system.js';
import './actions/actions.js';
import './actions/events.js';
import './actions/keyboard.js';
import './actions/drag-drop.js';
import './features/teams.js';
import './features/subtasks.js';
import './features/archive.js';
import './actions/teams.js';
import './actions/subtasks.js';
import './actions/archive.js';
import './actions/sync.js';
import { S, D, verifyEmail } from './core/store.js';
import { BACKEND, api } from './core/api.js';
import { joinToken, verifyToken } from './core/join.js';
import { A, beginOnboarding } from './actions/actions.js';
import { resumeSession } from './actions/sync.js';
import { ROUTE_NAMES, render, go } from './shell/render.js';

(async function init() {
  document.documentElement.lang = 'en';
  if (!document.getElementById('sr-live')) {
    const l = document.createElement('div');
    l.id = 'sr-live';
    l.className = 'sr';
    l.setAttribute('aria-live', 'polite');
    document.body.appendChild(l);
  }
  const h = (location.hash || '').slice(1);
  const authRoutes = ['login', 'signup', 'forgot', 'reset', 'verify', 'onboarding'];
  const token = BACKEND && joinToken(location.hash);
  const verifying = BACKEND && verifyToken(location.hash);
  S.ui.route = S.prefs.home || 'home';
  if (verifying) {
    // The link in a verification email works once: a reload must not open it again.
    history.replaceState(null, '', location.pathname + location.search);
    try {
      // Verified and signed in; a new User goes on to set up their Workspace.
      if (!(await verifyEmail(verifying))) beginOnboarding();
      else S.ui.route = S.prefs.home || 'home';
    } catch (e) {
      S.ui.auth = 'verify-failed';
      S.ui.errors.verify = e.message;
    }
  } else if (token) {
    // The link in an Invite email: the page to join shows whatever session this browser has.
    S.ui.auth = 'join';
    S.ui.join = { token };
    try {
      S.ui.join.invite = await api.invite(token);
    } catch (e) {
      S.ui.join.error = e.message;
    }
  } else if (authRoutes.includes(h)) S.ui.auth = h;
  else if (h && ROUTE_NAMES[h]) S.ui.route = h;
  // Backend mode: nothing renders until the server says who is signed in, so the demo data never flashes.
  if (BACKEND && !token && !verifying) {
    try {
      const session = await resumeSession();
      if (session === 'out') S.ui.auth ||= 'login';
      // Signed in without a Workspace: set one up (or exit setup, which signs out).
      else if (session === 'setup') beginOnboarding();
      // The Member's start page, now that their Preferences have come from the server.
      else if (!ROUTE_NAMES[h]) S.ui.route = S.prefs.home || 'home';
    } catch (e) {
      S.ui.auth ||= 'login';
      S.ui.errors.login = e.message;
    }
  }
  render();
})();

// A link from an email opened in a tab that runs the app already changes only the hash: start over with it.
if (BACKEND) addEventListener('hashchange', () => (joinToken(location.hash) || verifyToken(location.hash)) && location.reload());

// Dev-only handle for debugging and screenshot tooling; stripped from production builds.
if (import.meta.env.DEV) window.__gr8r = { S, D, A, go, render };
