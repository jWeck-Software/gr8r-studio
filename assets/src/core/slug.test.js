import { test } from 'node:test';
import assert from 'node:assert/strict';
import { slug } from './slug.js';

test('a Workspace URL is made of lowercase letters and digits with single hyphens between them', () => {
  assert.equal(slug('Gr8r Studio'), 'gr8r-studio');
  assert.equal(slug('  Acme Labs! '), 'acme-labs');
  assert.equal(slug('a - b'), 'a-b');
  assert.equal(slug('--studio--'), 'studio');
  assert.equal(slug('Café Ünïcode'), 'caf-n-code');
});

test('text without a letter or digit makes no URL', () => {
  assert.equal(slug('!!!'), '');
});
