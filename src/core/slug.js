/* ---------- slug: a Workspace URL from what is typed ---------- */
/* Pure, so `npm test` runs it under plain Node. Lowercase letters and digits with single hyphens
   between them, which is what the backend accepts as a Workspace URL. */
export const slug = text =>
  String(text)
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-|-$/g, '');
