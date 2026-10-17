import { test } from 'node:test';
import assert from 'node:assert/strict';
import { afterSignIn, midEdit, refreshed } from './sync-rules.js';

test('a refresh takes the synced collections from the server and keeps what lives only in this browser', () => {
  const local = { me: 'm1', tasks: [{ id: 't1', title: 'Old' }], savedViews: [{ id: 'v1' }], files: [{ id: 'f1' }] };
  const server = { me: 'm1', tasks: [{ id: 't1', title: 'New' }], savedViews: [], files: [] };

  assert.deepEqual(refreshed(local, server, ['tasks']), {
    me: 'm1',
    tasks: [{ id: 't1', title: 'New' }],
    savedViews: [{ id: 'v1' }],
    files: [{ id: 'f1' }],
  });
});

test('a refresh that brings nothing new changes nothing, so the page is not redrawn', () => {
  const local = { me: 'm1', tasks: [{ id: 't1', title: 'Same', tags: [] }], savedViews: [{ id: 'v1' }] };
  const server = { me: 'm1', tasks: [{ tags: [], title: 'Same', id: 't1' }], savedViews: [] };

  assert.equal(refreshed(local, server, ['tasks']), null);
});

test("a snapshot of another Member (signed in from another tab) replaces everything, nothing of this browser's copy stays", () => {
  const local = { me: 'm1', tasks: [{ id: 't1' }], savedViews: [{ id: 'v1' }] };
  const server = { me: 'm2', tasks: [{ id: 't1' }], savedViews: [] };

  assert.deepEqual(refreshed(local, server, ['tasks']), { me: 'm2', tasks: [{ id: 't1' }], savedViews: [] });
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
  assert.equal(midEdit(resting, { dragging: true }), true);
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
