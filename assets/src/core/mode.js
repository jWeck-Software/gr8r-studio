/* ---------- mode: what each build keeps, allows and says ---------- */
/* The localStorage demo and the backend-mode app differ in data, not in code: what this browser
   keeps, which demo stand-ins show, how long the demo's account calls take, the upload limit and a
   few sentences. They are decided here, once; callers read MODE. The mode is fixed when the app is
   built (VITE_API_URL, core/api.js), so each build keeps only its own half of this table and of
   every `MODE.x ? … : …`: read single values, never spread or hand MODE around, or the bundler can
   no longer drop the other half. */
import { BACKEND } from './api.js';

export const MODE = BACKEND
  ? {
      /* The server owns S.data and keeps the Member's prefs and views: this browser keeps a copy of
         those and the sidebar state, under its own key so they never mix with the demo's saved
         Workspace, together with the Member they belong to. */
      storage: { key: 'gr8r.v2.backend', keepsData: false, keepsOwner: true },
      can: {
        // A deleted File's content and a removed Member's sign-in are gone on the server: undo can't bring them back.
        undoDeletes: false,
        // Every Invite is one person's: there is no Workspace-wide link to copy.
        copyInviteLink: false,
        // Files have content on the server: images preview as themselves, and every File downloads.
        openFiles: true,
        // Email links are real: the reset page is opened from one, not from a button standing in for it.
        fakeEmailLinks: false,
        // Account calls ask the server: they take their time (the button shows it), it can refuse them, and a User in
        // setup has no Workspace to go back to. The demo's stand-ins answer at once and always succeed, into its Workspace.
        askServer: true,
      },
      // How long a button shows it is loading before its account call goes out (the demo fakes the server's time).
      wait: { signIn: 0, signUp: 0, forgotPassword: 0, resetPassword: 0, changePassword: 0 },
      maxUploadMB: 25,
      text: {
        // The server answers alike for every address, so the screen says the same (before and after the address).
        resetSent: ['If there is an account for', ', we sent a reset link to it'],
        passwordChanged: 'Your other sessions were signed out.',
        workspaceDeleted: 'Workspace deleted',
      },
    }
  : {
      storage: { key: 'gr8r.v2', keepsData: true, keepsOwner: false },
      can: { undoDeletes: true, copyInviteLink: true, openFiles: false, fakeEmailLinks: true, askServer: false },
      wait: { signIn: 750, signUp: 800, forgotPassword: 700, resetPassword: 700, changePassword: 800 },
      maxUploadMB: 250,
      text: {
        resetSent: ['We sent a reset link to', ''],
        passwordChanged: 'Other sessions stay signed in.',
        workspaceDeleted: 'Workspace deleted. Demo data was restored for this prototype.',
      },
    };
