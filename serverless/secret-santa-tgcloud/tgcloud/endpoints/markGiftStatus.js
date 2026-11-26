import { APP_URL } from '../lib/config.js';
import { fail, requirePair } from '../lib/access.js';
import { setAssignmentGift } from '../lib/store.js';
import { sendMiniApp } from '../lib/telegram.js';

export default async function (input, ctx) {
  const auth = await requirePair(ctx, input?.campaignId);
  if (!['drawn', 'gifting'].includes(auth.campaign.status)) {
    fail('CAMPAIGN_CLOSED', 'Кампания уже завершена.');
  }
  const status = String(input?.status ?? '');
  const current = auth.assignment.giftStatus;

  if (status === 'sent') {
    if (auth.role !== 'santa') fail('NOT_SANTA', 'Отметить отправку может только Санта.');
    if (!['pending', 'sent'].includes(current)) fail('INVALID_TRANSITION', 'Подарок уже получен.');
    if (current !== 'sent') {
      await setAssignmentGift(auth.assignment.id, { giftStatus: 'sent', sentAt: new Date() });
      try {
        await sendMiniApp(
          auth.otherUserId,
          '🎁 Твой Санта отправил подарок. Скоро он будет у тебя.',
          /^https:\/\//i.test(APP_URL) ? APP_URL : '',
        );
      } catch (error) {
        console.warn('gift notification failed', error instanceof Error ? error.message : error);
      }
    }
    return { status: 'sent' };
  }

  if (status === 'received') {
    if (auth.role !== 'recipient') fail('NOT_RECIPIENT', 'Отметить получение может только получатель.');
    if (current === 'received') return { status: 'received' };
    if (!['pending', 'sent'].includes(current)) fail('INVALID_TRANSITION', 'Нельзя отметить получение.');
    await setAssignmentGift(auth.assignment.id, { giftStatus: 'received', receivedAt: new Date() });
    try {
      await sendMiniApp(
        auth.otherUserId,
        '✅ Подарок доставлен. Спасибо за участие!',
        /^https:\/\//i.test(APP_URL) ? APP_URL : '',
      );
    } catch (error) {
      console.warn('gift notification failed', error instanceof Error ? error.message : error);
    }
    return { status: 'received' };
  }

  fail('INVALID_TRANSITION', 'Неизвестный статус подарка.');
}
