/* ---------- join: the address an Invite email links to ---------- */
/* The backend emails invited people `<app>/#join/<token>`; the token is URL-safe base64. */

/** The Invite token in a location hash like `#join/<token>`, or null when the hash is no Invite link. */
export function joinToken(hash) {
  const m = /^#join\/([A-Za-z0-9_-]+)$/.exec(hash || '');
  return m ? m[1] : null;
}
