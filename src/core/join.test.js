import { test } from 'node:test';
import assert from 'node:assert/strict';
import { joinToken, verifyToken } from './join.js';

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

test('a verification link opens the app at #verify/<token>', () => {
  assert.equal(verifyToken('#verify/xkjG24V-a_-3k-Q_pXkRd5nzzurTgAUqP4X_T5vvanA'), 'xkjG24V-a_-3k-Q_pXkRd5nzzurTgAUqP4X_T5vvanA');
});

test('the verify screen itself and any other address are no verification link', () => {
  assert.equal(verifyToken('#verify'), null);
  assert.equal(verifyToken('#verify/'), null);
  assert.equal(verifyToken('#join/xkjG24V'), null);
  assert.equal(verifyToken('#verify/abc/def'), null);
  assert.equal(verifyToken(''), null);
});
