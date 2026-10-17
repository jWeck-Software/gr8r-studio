import { test } from 'node:test';
import assert from 'node:assert/strict';
import { removeFile, undoTo } from './files.js';

const wire = { id: 'f1', project: 'p1', name: 'wireframes.fig', type: 'fig', size: '4.2 MB', by: 'm2', at: 1, task: 't3' };
const audit = { id: 'f2', project: 'p1', name: 'audit.pdf', type: 'pdf', size: '860 KB', by: 'm2', at: 2, task: 't3' };
const att = ({ id, name, type, size, by, at }) => ({ id, name, type, size, by, at });
const data = () => ({ files: [wire, audit], tasks: [{ id: 't3', attachments: [att(wire), att(audit)] }] });

test("removing a Task's attachment deletes its File too", () => {
  const d = data();

  removeFile(d, 't3', att(wire));

  assert.deepEqual(d.files, [audit]);
  assert.deepEqual(d.tasks[0].attachments, [att(audit)]);
});

test("in the browser's demo data, whose Attachments have ids of their own, the File is the Task's one of that name", () => {
  const sameName = { ...wire, id: 'f7', task: null };
  const d = {
    files: [sameName, wire, audit],
    tasks: [
      {
        id: 't3',
        attachments: [
          { ...att(wire), id: 'a1' },
          { ...att(audit), id: 'a2' },
        ],
      },
    ],
  };

  removeFile(d, 't3', { ...att(wire), id: 'a1' });
  assert.deepEqual(d.files, [sameName, audit]);

  removeFile(d, 't3', audit);
  assert.deepEqual(d.files, [sameName]);
  assert.deepEqual(d.tasks[0].attachments, []);
});

test('an Attachment another Task carries over (a duplicated Task) removes only itself, not the File of the Task it came from', () => {
  const d = data();
  d.tasks.push({ id: 't9', attachments: [att(wire)] });

  removeFile(d, 't9', att(wire));

  assert.deepEqual(d.files, [wire, audit]);
  assert.deepEqual(d.tasks[1].attachments, []);
  assert.deepEqual(d.tasks[0].attachments, [att(wire), att(audit)]);
});

/* Undo: the copy taken before archiving t5 had wireframes.fig only; audit.pdf was uploaded to t3 since. */
const late = { id: 'f9', project: 'p1', name: 'late.txt', type: 'doc', size: '9 B', by: 'm1', at: 9, task: 't3' };
const copyBefore = () => ({
  projects: [{ id: 'p1' }],
  files: [wire],
  tasks: [
    { id: 't3', attachments: [att(wire)] },
    { id: 't5', archived: false, attachments: [] },
  ],
});
const now = () => ({
  projects: [{ id: 'p1' }],
  files: [late, wire],
  tasks: [
    { id: 't3', attachments: [att(wire), att(late)] },
    { id: 't5', archived: true, attachments: [] },
  ],
});

test('undo keeps a File uploaded after its copy was taken, with its Attachment', () => {
  const d = undoTo(copyBefore(), now());

  assert.deepEqual(d.files, [late, wire]);
  assert.deepEqual(d.tasks[0].attachments, [att(wire), att(late)]);
  assert.equal(d.tasks[1].archived, false);
});

test('a File uploaded since to a Task or Project that undo takes away goes with it', () => {
  const onNewTask = { ...late, id: 'f10', task: 't8' };
  const onNewProject = { ...late, id: 'f11', project: 'p9', task: null };
  const current = now();
  current.files.unshift(onNewTask, onNewProject);
  current.tasks.push({ id: 't8', attachments: [att(onNewTask)] });
  current.projects.push({ id: 'p9' });

  const d = undoTo(copyBefore(), current);

  assert.deepEqual(d.files, [late, wire]);
  assert.deepEqual(
    d.tasks.map(t => t.id),
    ['t3', 't5'],
  );
});
