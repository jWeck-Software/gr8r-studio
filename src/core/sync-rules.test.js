import { test } from 'node:test';
import assert from 'node:assert/strict';
import { SYNCED, afterSignIn, midEdit, mergeSnapshot } from './sync-rules.js';

test('a refresh takes the synced collections from the server and keeps what lives only in this browser', () => {
  const local = { me: 'm1', tasks: [{ id: 't1', title: 'Old' }], savedViews: [{ id: 'v1' }], files: [{ id: 'f1' }] };
  const server = { me: 'm1', tasks: [{ id: 't1', title: 'New' }], savedViews: [], files: [] };

  assert.deepEqual(mergeSnapshot(local, server, ['tasks']), {
    me: 'm1',
    tasks: [{ id: 't1', title: 'New' }],
    savedViews: [{ id: 'v1' }],
    files: [{ id: 'f1' }],
  });
});

test('a refresh that brings nothing new changes nothing, so the page is not redrawn', () => {
  const local = { me: 'm1', tasks: [{ id: 't1', title: 'Same', tags: [] }], savedViews: [{ id: 'v1' }] };
  const server = { me: 'm1', tasks: [{ tags: [], title: 'Same', id: 't1' }], savedViews: [] };

  assert.equal(mergeSnapshot(local, server, ['tasks']), null);
});

test("a snapshot of another Member (signed in from another tab) replaces everything, nothing of this browser's copy stays", () => {
  const local = { me: 'm1', tasks: [{ id: 't1' }], savedViews: [{ id: 'v1' }] };
  const server = { me: 'm2', tasks: [{ id: 't1' }], savedViews: [] };

  assert.deepEqual(mergeSnapshot(local, server, ['tasks']), { me: 'm2', tasks: [{ id: 't1' }], savedViews: [] });
});

const resting = {
  route: 'project',
  drawer: 't1',
  sel: new Set(['t2']),
  drafts: { t1: 'half a comment' },
  editCell: null,
  composer: null,
  modals: [],
  pop: null,
  palette: null,
  auth: null,
  undos: 0,
  drag: null,
};

test('an open drawer, a selection and a comment draft do not hold a refresh back: render keeps them', () => {
  assert.equal(midEdit(resting), false);
});

test('a cell being renamed, a new Task being typed or an open dialog holds a refresh back: their text lives only in the page', () => {
  assert.equal(midEdit({ ...resting, editCell: { id: 't1', f: 'title' } }), true);
  assert.equal(midEdit({ ...resting, composer: { ctx: 'p1', group: 'todo' } }), true);
  assert.equal(midEdit({ ...resting, modals: [{ type: 'newTask' }] }), true);
});

test('a popover, the command palette, the login screen or a drag in progress holds a refresh back', () => {
  assert.equal(midEdit({ ...resting, pop: { type: 'assignee', id: 't1' } }), true);
  assert.equal(midEdit({ ...resting, palette: { q: '' } }), true);
  assert.equal(midEdit({ ...resting, auth: 'login' }), true);
  assert.equal(midEdit({ ...resting, drag: { type: 'card', id: 't1' } }), true);
});

test('an open undo toast holds a refresh back: undo puts back a copy of S.data from before the refresh', () => {
  assert.equal(midEdit({ ...resting, undos: 1 }), true);
});

test('text typed into a field of the page holds a refresh back, except a comment draft, which the page keeps itself', () => {
  assert.equal(midEdit(resting, { typing: 'field' }), true);
  assert.equal(midEdit(resting, { typing: 'draft' }), false);
});

test('signing in again as the Member whose session ended sends the changes left over from that session', () => {
  assert.equal(afterSignIn({ member: 'm1' }, 'm1'), 'resend');
});

test('another Member signing in discards the changes left over from the ended session: they are not theirs to send', () => {
  assert.equal(afterSignIn({ member: 'm1' }, 'm3'), 'discard');
});

test('without leftover changes a sign-in just loads the Workspace', () => {
  assert.equal(afterSignIn(null, 'm1'), 'load');
});

test('the synced types go out in an order where every record comes after the records it names', () => {
  const before = (a, b) => SYNCED.indexOf(a) >= 0 && SYNCED.indexOf(a) < SYNCED.indexOf(b);
  // A Member names their Team; a Project its Team, lead and Members; a Task its Project and assignee;
  // the Project order its Projects; Comments and Activity their Tasks.
  for (const [named, naming] of [
    ['teams', 'members'],
    ['teams', 'projects'],
    ['members', 'projects'],
    ['members', 'tasks'],
    ['projects', 'tasks'],
    ['projects', 'projOrder'],
    ['tasks', 'comments'],
    ['tasks', 'activity'],
  ])
    assert.ok(before(named, naming), `${named} before ${naming}`);
  assert.ok(SYNCED.includes('ws'));
});
