import test from 'node:test';
import assert from 'node:assert/strict';
import {
  CODE_ALPHABET,
  makeCode,
  validateDraft,
  validateRules,
} from '../tgcloud/lib/domain/campaign.js';
import { normalizeCode } from '../tgcloud/lib/utils.js';

test('generates six-character codes from the safe alphabet', () => {
  const code = makeCode(() => 0.5, 6);
  assert.equal(code.length, 6);
  for (const char of code) assert.ok(CODE_ALPHABET.includes(char));
  assert.equal(makeCode(() => 0, 6), 'AAAAAA');
  assert.equal(makeCode(() => 0.999, 6), '999999');
});

test('normalizes user-entered codes', () => {
  assert.equal(normalizeCode(' ab-cd 12 '), 'ABCD12');
  assert.equal(normalizeCode('join_abc123'), 'JOINABC123');
});

test('validates a campaign draft', () => {
  const now = new Date('2026-11-26T00:00:00+03:00');
  const result = validateDraft({
    title: '  Новый год  ',
    description: 'Друзья',
    budgetMin: 1000,
    budgetMax: 3000,
    registrationEndsAt: new Date('2026-12-10T00:00:00+03:00'),
    giftDate: new Date('2026-12-25T00:00:00+03:00'),
    now,
  });
  assert.equal(result.ok, true);
  assert.equal(result.value.title, 'Новый год');
});

test('rejects invalid budgets and dates', () => {
  const now = new Date('2026-11-26T00:00:00+03:00');
  const base = {
    title: 'Кампания',
    description: '',
    budgetMin: 1000,
    budgetMax: 500,
    registrationEndsAt: new Date('2026-12-10T00:00:00+03:00'),
    giftDate: new Date('2026-12-25T00:00:00+03:00'),
    now,
  };
  assert.equal(validateDraft(base).code, 'BUDGET_ORDER');
  assert.equal(
    validateDraft({
      ...base,
      budgetMax: 2000,
      registrationEndsAt: new Date('2026-11-01T00:00:00+03:00'),
    }).code,
    'REGISTRATION_PAST',
  );
  assert.equal(
    validateDraft({
      ...base,
      budgetMax: 2000,
      giftDate: new Date('2026-12-01T00:00:00+03:00'),
    }).code,
    'GIFT_BEFORE_REGISTRATION',
  );
});

test('validates rules text', () => {
  assert.equal(validateRules('').ok, false);
  assert.equal(validateRules('Правила').value, 'Правила');
});
