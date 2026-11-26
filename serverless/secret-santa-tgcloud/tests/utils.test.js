import test from 'node:test';
import assert from 'node:assert/strict';
import {
  base64ToBytes,
  bytesToBase64,
  callbackLength,
  detectImageMime,
  esc,
  formatBudget,
  fromBase36,
  normalizeCode,
  parseDateRu,
  splitMessage,
  toBase36,
} from '../tgcloud/lib/utils.js';

test('escapes html', () => {
  assert.equal(esc('<b>a & b</b>'), '&lt;b&gt;a &amp; b&lt;/b&gt;');
});

test('splits long messages', () => {
  const chunks = splitMessage('a'.repeat(50), 20);
  assert.equal(chunks.length, 3);
  assert.equal(chunks.join(''), 'a'.repeat(50));
});

test('parses and formats dates in Moscow time', () => {
  const date = parseDateRu('25.12.2026');
  assert.ok(date instanceof Date);
  assert.equal(date.toISOString(), '2026-12-24T21:00:00.000Z');
  assert.equal(parseDateRu('31.02.2026'), null);
});

test('formats budgets', () => {
  assert.match(formatBudget(1000, 1000), /1\s?000/);
  assert.match(formatBudget(1000, 2000), /1\s?000.*2\s?000/);
});

test('converts base36 ids and callback lengths', () => {
  assert.equal(fromBase36(toBase36(12345)), 12345);
  assert.equal(fromBase36('0'), null);
  assert.ok(callbackLength('ss:co:1') <= 64);
});

test('round-trips base64 and detects images', () => {
  const bytes = Uint8Array.from([0xff, 0xd8, 0xff, 0x00, 0x01]);
  const encoded = bytesToBase64(bytes);
  assert.deepEqual([...base64ToBytes(encoded)], [...bytes]);
  assert.equal(detectImageMime(bytes), 'image/jpeg');
  assert.equal(detectImageMime(Uint8Array.from([0x89, 0x50, 0x4e, 0x47])), 'image/png');
  assert.equal(detectImageMime(Uint8Array.from([1, 2, 3, 4])), null);
});

test('normalizes codes', () => {
  assert.equal(normalizeCode(' ab c-1 '), 'ABC1');
});
