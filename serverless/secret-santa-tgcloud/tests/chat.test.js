import test from 'node:test';
import assert from 'node:assert/strict';
import {
  canReportMessage,
  checkChatRate,
  normalizeChatText,
  roleForAssignment,
  visibleMessages,
} from '../tgcloud/lib/domain/chat.js';
import { progress, canConfirm } from '../tgcloud/lib/domain/membership.js';

test('normalizes chat text', () => {
  assert.deepEqual(normalizeChatText('  привет  '), { ok: true, text: 'привет' });
  assert.equal(normalizeChatText('   ').code, 'EMPTY');
  assert.equal(normalizeChatText('x'.repeat(1001)).code, 'LONG');
});

test('enforces chat rate limits', () => {
  assert.equal(checkChatRate(9, 59).ok, true);
  assert.equal(checkChatRate(10, 0).retryAfter, 60);
  assert.equal(checkChatRate(0, 60).retryAfter, 3600);
});

test('derives the role in an assignment', () => {
  const assignment = { giverId: 1, receiverId: 2 };
  assert.equal(roleForAssignment(assignment, 1), 'santa');
  assert.equal(roleForAssignment(assignment, 2), 'recipient');
  assert.equal(roleForAssignment(assignment, 3), null);
});

test('filters hidden messages', () => {
  assert.equal(visibleMessages([{ id: 1 }, { id: 2, hiddenAt: new Date() }]).length, 1);
});

test('checks participation confirmation', () => {
  const campaign = { status: 'recruiting', rulesVersion: 2 };
  const member = { status: 'rules_accepted', rulesVersionAccepted: 2 };
  assert.equal(canConfirm({ campaign, member, wishlistCount: 1 }).ok, true);
  assert.equal(
    canConfirm({ campaign, member: { ...member, rulesVersionAccepted: 1 }, wishlistCount: 1 }).code,
    'RULES_NOT_ACCEPTED',
  );
  assert.equal(canConfirm({ campaign, member, wishlistCount: 0 }).code, 'WISHLIST_EMPTY');
});

test('requires a delivery method for hybrid campaigns', () => {
  const campaign = { status: 'recruiting', rulesVersion: 2, deliveryMode: 'hybrid' };
  const member = { status: 'rules_accepted', rulesVersionAccepted: 2 };
  assert.equal(canConfirm({ campaign, member, wishlistCount: 1 }).code, 'DELIVERY_METHOD_REQUIRED');
  assert.equal(canConfirm({ campaign, member, wishlistCount: 1, delivery: { method: 'meetup' } }).ok, true);
  assert.equal(
    canConfirm({ campaign, member, wishlistCount: 1, delivery: { method: 'shipping' } }).code,
    'DELIVERY_REQUIRED',
  );
  assert.equal(
    canConfirm({
      campaign,
      member,
      wishlistCount: 1,
      delivery: { method: 'shipping', recipientName: 'Аня', address: 'ПВЗ, ул. Ленина, 1' },
    }).ok,
    true,
  );
});

test('requires delivery details for shipping campaigns', () => {
  const campaign = { status: 'recruiting', rulesVersion: 2, deliveryMode: 'shipping' };
  const member = { status: 'rules_accepted', rulesVersionAccepted: 2 };
  assert.equal(canConfirm({ campaign, member, wishlistCount: 1 }).code, 'DELIVERY_REQUIRED');
  assert.equal(
    canConfirm({
      campaign,
      member,
      wishlistCount: 1,
      delivery: { recipientName: 'Аня', address: 'ПВЗ, ул. Ленина, 1' },
    }).ok,
    true,
  );
});

test('counts progress without removed members', () => {
  const result = progress([
    { status: 'confirmed' },
    { status: 'joined' },
    { status: 'removed' },
  ]);
  assert.deepEqual(result, { total: 2, confirmed: 1, accepted: 1 });
});

test('does not allow reporting system gift messages', () => {
  assert.deepEqual(canReportMessage({ id: 1, senderRole: 'system', senderUserId: 2 }, 1), {
    ok: false,
    code: 'NOT_ALLOWED',
  });
  assert.deepEqual(canReportMessage({ id: 2, senderRole: 'santa', senderUserId: 2 }, 1), {
    ok: true,
  });
});
