import { test } from 'node:test';
import assert from 'node:assert/strict';
import { weakPassword } from './password.js';

const WEAK = 'Use at least 10 characters, including a number or symbol';

test('a new password needs at least 10 characters, including a number or symbol', () => {
  assert.equal(weakPassword('nina-password-1'), null);
  assert.equal(weakPassword('longpassword7'), null);
  assert.equal(weakPassword('long password'), null);
});

test('one that is short, letters only or has only an underscore besides letters is too weak, as the backend says', () => {
  assert.equal(weakPassword('short-1'), WEAK);
  assert.equal(weakPassword('longpassword'), WEAK);
  assert.equal(weakPassword('under_score_x'), WEAK);
  assert.equal(weakPassword(''), WEAK);
});
