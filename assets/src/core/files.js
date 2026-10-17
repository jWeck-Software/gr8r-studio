/* ---------- files: a File and the Attachment it is on its Task ---------- */
/* Pure and import-free, so `npm test` runs it under plain Node.
   A File lives in `files`; when it is attached to a Task, the Task's `attachments` carries the same
   File again in the Attachment shape (attachmentOf). Both go together: removing either removes both,
   which the sync sends as one `files` delete (the server keeps the Files; a Task's Attachments are rendered
   from them). A File is attached to one Task: a duplicated Task starts without Attachments. Undo keeps the Files
   uploaded after the copy it puts back was taken (undoTo). */

/** The Attachment a File is on its Task: the File without its Project and Task. */
export const attachmentOf = ({ id, name, type, size, by, at }) => ({ id, name, type, size, by, at });

/** Removes a File, given as itself or as its Attachment on the Task `taskId`, from `files` and from the Task's `attachments`.
   Only a File attached to that Task goes: an Attachment carried over to another Task removes only itself.
   The browser's demo data (seed.js) gives Attachments ids of their own; there, the File is the one of that name on that Task. */
export function removeFile(data, taskId, { id, name }) {
  data.files = without(data.files, id, name, f => !taskId || f.task === taskId);
  const t = taskId ? data.tasks.find(x => x.id === taskId) : null;
  if (t) t.attachments = without(t.attachments, id, name);
}

/* The record with this id, or else the first one of this name; of those `mine`. */
function without(list, id, name, mine = () => true) {
  const byId = list.findIndex(x => x.id === id && mine(x));
  const i = byId >= 0 ? byId : list.findIndex(x => x.name === name && mine(x));
  return i < 0 ? list : list.filter((_, j) => j !== i);
}

/** What undo puts back: `copy`, the data as it was when the undo was offered, in place of `current`, except that the Files
   uploaded (or duplicated) since stay, with their Attachments: undo is about the change it was offered for, and in backend
   mode a File it dropped would be deleted on the server. A File on a Task or Project that undo takes away goes with it,
   as it does on the server. Changes and returns `copy`. */
export function undoTo(copy, current) {
  const had = new Set(copy.files.map(f => f.id));
  const kept = f => copy.projects.some(p => p.id === f.project) && (!f.task || copy.tasks.some(t => t.id === f.task));
  const added = current.files.filter(f => !had.has(f.id) && kept(f));
  copy.files = [...added, ...copy.files];
  for (const f of added) {
    const t = f.task && copy.tasks.find(x => x.id === f.task);
    if (t && !t.attachments.some(a => a.id === f.id)) t.attachments.push(attachmentOf(f));
  }
  return copy;
}
