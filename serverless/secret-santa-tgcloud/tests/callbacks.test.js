import test from 'node:test';
import assert from 'node:assert/strict';
import {
  campaignKeyboard,
  drawKeyboard,
  membersKeyboard,
  exclusionsKeyboard,
  exclusionPickOneKeyboard,
  exclusionPickTwoKeyboard,
  confirmKeyboard,
  broadcastMenuKeyboard,
  broadcastPreviewKeyboard,
  reportActionsKeyboard,
  adminKeyboard,
} from '../tgcloud/lib/keyboards.js';

function allButtons(keyboard) {
  return (keyboard?.inline_keyboard ?? []).flat();
}

test('all callback data fits the 64-byte Telegram limit', () => {
  const campaign = {
    id: 123456,
    status: 'locked',
    code: 'XMAS26',
    title: 'Новогодняя кампания',
    version: 98765,
  };
  const keyboards = [
    campaignKeyboard({ campaign, isOrganizer: true, appUrl: 'https://example.com' }),
    membersKeyboard(
      123456,
      3,
      5,
      [{ userId: 900001, user: { firstName: 'Аня' }, status: 'confirmed', role: 'participant' }],
      true,
    ),
    exclusionsKeyboard(123456, [{ id: 321 }]),
    exclusionPickOneKeyboard(123456, [{ userId: 900001, user: { firstName: 'Аня' } }], 1, 2),
    exclusionPickTwoKeyboard(123456, [{ userId: 900001, user: { firstName: 'Аня' } }], 900002, 1, 2),
    drawKeyboard(
      123456,
      [{ giverId: 900001, receiverId: 900002 }],
      new Map([[900001, 'Аня']]),
      900001,
      1,
      2,
      98765,
    ),
    confirmKeyboard(123456, 'm', 98765),
    broadcastMenuKeyboard(123456, [{ key: 'wishlist', label: 'Заполните вишлист' }]),
    broadcastPreviewKeyboard(123456),
    reportActionsKeyboard(987654, 987654, 900001, true),
    adminKeyboard(),
  ];
  for (const keyboard of keyboards) {
    for (const button of allButtons(keyboard)) {
      if (!button.callback_data) continue;
      const bytes = new TextEncoder().encode(button.callback_data).length;
      assert.ok(bytes <= 64, `${button.callback_data} = ${bytes} bytes`);
      assert.match(button.callback_data, /^ss:/);
    }
  }
});

test('campaign callbacks encode ids in base36', () => {
  const keyboard = campaignKeyboard({
    campaign: { id: 12345, status: 'recruiting', title: 'T', version: 1 },
    isOrganizer: true,
    appUrl: '',
  });
  assert.ok(allButtons(keyboard).some((button) => button.callback_data === 'ss:dl:9ix'));
});

