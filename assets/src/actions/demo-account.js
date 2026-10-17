/* =====================================================================
   DEMO ACCOUNT: the localStorage demo's stand-ins for the account calls (core/store.js, onSession)
   that backend mode's sync module answers from the server. There is no server: every call succeeds,
   after the delay the demo has always faked for its button (none where it answered at once), and
   does here what the demo does instead. Email links (acceptInvite, verifyEmail) open only in backend
   mode, and nothing waits to be synced (flush), so the demo leaves those out.
   ===================================================================== */
import { BACKEND } from '../core/api.js';
import { uid } from '../core/utils.js';
import { seed } from '../data/seed.js';
import { D, S, onSession, save } from '../core/store.js';
import { render } from '../shell/render.js';

const after = (ms, value) => new Promise(resolve => setTimeout(() => resolve(value), ms));
const now = value => Promise.resolve(value);

if (!BACKEND)
  onSession({
    // The demo's own Member, whatever was typed.
    signIn: () => after(750, D().me),
    signOut: () => now(),
    signUp: () => after(800),
    resendVerification: () => now(),
    forgotPassword: () => after(700),
    resendResetLink: () => now(),
    // The demo's reset page has no token, and setting the password signs nobody in.
    resetPassword: () => after(700, 'out'),
    changePassword: () => after(800),
    resendInvite: () => now(),
    deleteWorkspace: () => {
      S.data = seed();
      S.views = {};
      save();
      S.ui.auth = 'login';
      render();
      return now();
    },
    // The demo names its Workspace inside the change that sets it up, so going offline refuses (and "Try again" repeats) both.
    startWorkspace: o =>
      now(() => {
        if (o.ws.trim()) {
          const c = ['#2F2E2A', '#5A67D8', '#23918A', '#C54B78'][D().workspaces.length % 4];
          D().ws = { name: o.ws.trim(), url: o.url || 'workspace', c };
          if (!D().workspaces.some(w => w.name === D().ws.name)) D().workspaces.push({ id: uid('w'), name: D().ws.name, c, plan: 'Free' });
        }
      }),
    // The demo's Workspace is still there to go back to.
    leaveSetup: () => now('in'),
    // A User who just verified their address has no Workspace yet.
    resume: () => now('setup'),
  });
