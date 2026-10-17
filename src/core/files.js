/* ---------- files: a File and the Attachment it is on its Task ---------- */
/* Pure and import-free, so `npm test` runs it under plain Node.
   A File lives in `files`; when it is attached to a Task, the Task's `attachments` carries the same
   File again in the Attachment shape (attachmentOf). Both go together: removing either removes both,
   which the sync sends as one `files` delete (the server keeps Files only, ADR 0003). */

/** The Attachment a File is on its Task: the File without its Project and Task. */
export const attachmentOf = ({ id, name, type, size, by, at }) => ({ id, name, type, size, by, at });

/** Removes a File, given as itself or as its Attachment on the Task `taskId`, from `files` and from the Task's `attachments`.
   The browser's demo data (seed.js) gives Attachments ids of their own; there, the File is the one of that name on that Task. */
export function removeFile(data, taskId, { id, name }) {
  data.files = without(data.files, id, f => !!taskId && f.task === taskId && f.name === name);
  const t = taskId ? data.tasks.find(x => x.id === taskId) : null;
  if (t) t.attachments = without(t.attachments, id, a => a.name === name);
}

/* The record with this id, or else the first one `alike`. */
function without(list, id, alike) {
  const byId = list.findIndex(x => x.id === id);
  const i = byId >= 0 ? byId : list.findIndex(alike);
  return i < 0 ? list : list.filter((_, j) => j !== i);
}
