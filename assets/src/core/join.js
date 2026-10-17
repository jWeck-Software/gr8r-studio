/* ---------- join: the addresses emails link to ---------- */
/* The backend emails invited people `<app>/#join/<token>` and people who signed up
   `<app>/#verify/<token>`, and those who forgot their password `<app>/#reset/<token>`; the token is URL-safe base64. */

const tokenAt = (route, hash) => {
  const m = new RegExp(`^#${route}/([A-Za-z0-9_-]+)$`).exec(hash || '');
  return m ? m[1] : null;
};

/** The Invite token in a location hash like `#join/<token>`, or null when the hash is no Invite link. */
export const joinToken = hash => tokenAt('join', hash);

/** The email verification token in a location hash like `#verify/<token>`, or null when the hash is no verification link. */
export const verifyToken = hash => tokenAt('verify', hash);

/** The password reset token in a location hash like `#reset/<token>`, or null when the hash is no reset link. */
export const resetToken = hash => tokenAt('reset', hash);
