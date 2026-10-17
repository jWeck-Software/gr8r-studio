import { test } from 'node:test';
import assert from 'node:assert/strict';
import { diffData } from './diff.js';

test('a new record is put', () => {
  const prev = { tasks: [{ id: 't1', title: 'Wireframes' }] };
  const next = {
    tasks: [
      { id: 't1', title: 'Wireframes' },
      { id: 'x9', title: 'Copy' },
    ],
  };

  assert.deepEqual(diffData(prev, next, ['tasks']), [{ op: 'put', type: 'tasks', id: 'x9', data: { id: 'x9', title: 'Copy' } }]);
});

test('a changed record is put, an unchanged one is left out', () => {
  const prev = {
    tasks: [
      { id: 't1', status: 'todo' },
      { id: 't2', status: 'todo' },
    ],
  };
  const next = {
    tasks: [
      { id: 't1', status: 'todo' },
      { id: 't2', status: 'done' },
    ],
  };

  assert.deepEqual(diffData(prev, next, ['tasks']), [{ op: 'put', type: 'tasks', id: 't2', data: { id: 't2', status: 'done' } }]);
});

test('a record that is gone is deleted, after the puts of its collection', () => {
  const prev = {
    tasks: [
      { id: 't1', title: 'A' },
      { id: 't2', title: 'B' },
    ],
  };
  const next = { tasks: [{ id: 't1', title: 'A, renamed' }] };

  assert.deepEqual(diffData(prev, next, ['tasks']), [
    { op: 'put', type: 'tasks', id: 't1', data: { id: 't1', title: 'A, renamed' } },
    { op: 'delete', type: 'tasks', id: 't2' },
  ]);
});

test('the order of keys inside a record is not a change', () => {
  const prev = { tasks: [{ id: 't1', title: 'A', subtasks: [{ id: 's1', title: 'Draft', done: false }] }] };
  const next = { tasks: [{ subtasks: [{ done: false, id: 's1', title: 'Draft' }], title: 'A', id: 't1' }] };

  assert.deepEqual(diffData(prev, next, ['tasks']), []);
});

test('a key set to undefined is the same as a missing key, as on the wire', () => {
  const prev = { tasks: [{ id: 't1', status: 'todo' }] };
  const next = { tasks: [{ id: 't1', status: 'todo', completedAt: undefined }] };

  assert.deepEqual(diffData(prev, next, ['tasks']), []);
});

test('only the listed collections are compared, in the listed order', () => {
  const prev = { projects: [], tasks: [], comments: [] };
  const next = { projects: [{ id: 'p9' }], tasks: [{ id: 'x2' }, { id: 'x1' }], comments: [{ id: 'c1' }] };

  assert.deepEqual(diffData(prev, next, ['projects', 'tasks']), [
    { op: 'put', type: 'projects', id: 'p9', data: { id: 'p9' } },
    { op: 'put', type: 'tasks', id: 'x2', data: { id: 'x2' } },
    { op: 'put', type: 'tasks', id: 'x1', data: { id: 'x1' } },
  ]);
});

test('a collection missing on one side counts as empty', () => {
  assert.deepEqual(diffData({}, { tasks: [{ id: 'x1' }] }, ['tasks']), [{ op: 'put', type: 'tasks', id: 'x1', data: { id: 'x1' } }]);
  assert.deepEqual(diffData({ tasks: [{ id: 'x1' }] }, {}, ['tasks']), [{ op: 'delete', type: 'tasks', id: 'x1' }]);
});

test('a changed singleton is set as a whole, after the collections listed before it', () => {
  const prev = { projects: [{ id: 'p1' }], projOrder: ['p1', 'p2'] };
  const next = { projects: [{ id: 'p1' }, { id: 'p9' }], projOrder: ['p9', 'p2', 'p1'] };

  assert.deepEqual(diffData(prev, next, ['projects', 'projOrder']), [
    { op: 'put', type: 'projects', id: 'p9', data: { id: 'p9' } },
    { op: 'set', type: 'projOrder', data: ['p9', 'p2', 'p1'] },
  ]);
});

test('an unchanged singleton is left out, an emptied one is set', () => {
  assert.deepEqual(diffData({ projOrder: ['p1'] }, { projOrder: ['p1'] }, ['projOrder']), []);
  assert.deepEqual(diffData({ projOrder: ['p1'] }, { projOrder: [] }, ['projOrder']), [{ op: 'set', type: 'projOrder', data: [] }]);
});
