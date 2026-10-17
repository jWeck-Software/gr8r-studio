/* ---------- password: what a new password needs ---------- */
/* Pure, so `npm test` runs it under plain Node. The same policy as the backend's PasswordPolicy:
   at least 10 characters, including a number or symbol ("_" counts as a letter). */

/** Why `pw` is too weak to be chosen, as the message to show; null when it will do. */
export const weakPassword = pw => (pw.length < 10 || !/[0-9\W]/.test(pw) ? 'Use at least 10 characters, including a number or symbol' : null);
