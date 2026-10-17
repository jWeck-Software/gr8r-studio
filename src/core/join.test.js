import { test } from 'node:test';
import assert from 'node:assert/strict';
import { joinToken } from './join.js';

test('an Invite link opens the app at #join/<token>', () => {
  assert.equal(joinToken('#join/xkjG24V-a_-3k-Q_pXkRd5nzzurTgAUqP4X_T5vvanA'), 'xkjG24V-a_-3k-Q_pXkRd5nzzurTgAUqP4X_T5vvanA');
});

test('any other address is no Invite link', () => {
  assert.equal(joinToken(''), null);
  assert.equal(joinToken('#members'), null);
  assert.equal(joinToken('#join'), null);
  assert.equal(joinToken('#join/'), null);
  assert.equal(joinToken('#join/abc/def'), null);
  assert.equal(joinToken('#join/<script>'), null);
});
