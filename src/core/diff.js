/* ---------- diff: the sync ops between two copies of S.data ---------- */
/* Pure and import-free, so `npm test` runs it under plain Node.
   For each listed collection (an array of records with an `id`), in the listed order:
   a `put` for every record that is new or changed, in the order of `next`, then a `delete`
   for every id that is gone, in the order of `prev`. The server applies ops in order, so list
   collections that others refer to first (projects before tasks).
   Records compare as JSON would: key order is ignored and keys holding `undefined` count as
   missing. A put carries the record from `next` itself, not a copy. */
export function diffData(prev, next, collections) {
  const ops = [];
  for (const type of collections) {
    const before = new Map((prev[type] || []).map(r => [r.id, r]));
    const after = new Set((next[type] || []).map(r => r.id));
    for (const r of next[type] || []) if (!before.has(r.id) || !same(before.get(r.id), r)) ops.push({ op: 'put', type, id: r.id, data: r });
    for (const id of before.keys()) if (!after.has(id)) ops.push({ op: 'delete', type, id });
  }
  return ops;
}

function same(a, b) {
  if (a === b) return true;
  if (typeof a !== 'object' || typeof b !== 'object' || a === null || b === null || Array.isArray(a) !== Array.isArray(b)) return false;
  const defined = o => Object.keys(o).filter(k => o[k] !== undefined);
  const ka = defined(a);
  return ka.length === defined(b).length && ka.every(k => b[k] !== undefined && same(a[k], b[k]));
}
