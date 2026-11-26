import { APP_URL } from '../lib/config.js';
import { fail, requirePair } from '../lib/access.js';
import { checkChatRate, normalizeChatText } from '../lib/domain/chat.js';
import {
  addChatMessage,
  countRecentMessages,
  hitRate,
} from '../lib/store.js';
import { sendMiniApp } from '../lib/telegram.js';

export default async function (input, ctx) {
  const auth = await requirePair(ctx, input?.campaignId);
  if (!['drawn', 'gifting'].includes(auth.campaign.status)) {
    fail('CAMPAIGN_CLOSED', 'Переписка доступна только во время вручения.');
  }
  if (auth.assignment.chatBlocked) {
    fail('CHAT_BLOCKED', 'Переписка в этой паре остановлена организатором.');
  }
  const normalized = normalizeChatText(input?.text);
  if (!normalized.ok) {
    fail('VALIDATION', normalized.code === 'LONG' ? 'Сообщение слишком длинное.' : 'Сообщение пустое.');
  }
  const clientMsgId = String(input?.clientMsgId ?? '').trim();
  if (!clientMsgId || clientMsgId.length > 64) {
    fail('VALIDATION', 'Не передан идентификатор сообщения.');
  }

  const minute = await hitRate(`chat1m:${auth.assignment.id}:${auth.tgId}`, 10, 60);
  if (!minute.ok) fail('RATE_LIMITED', 'Слишком много сообщений. Подождите минуту.', { retryAfter: minute.retryAfter });
  const hour = await hitRate(`chat1h:${auth.assignment.id}:${auth.tgId}`, 60, 3600);
  if (!hour.ok) fail('RATE_LIMITED', 'Лимит сообщений на час. Попробуйте позже.', { retryAfter: hour.retryAfter });

  const message = await addChatMessage({
    campaignId: auth.campaign.id,
    assignmentId: auth.assignment.id,
    senderRole: auth.role,
    senderUserId: auth.tgId,
    clientMsgId,
    text: normalized.text,
  });
  if (!message || Number(message.senderUserId) !== Number(auth.tgId)) {
    fail('VALIDATION', 'Не удалось отправить сообщение. Попробуйте ещё раз.');
  }

  try {
    await sendMiniApp(
      auth.otherUserId,
      '💬 У тебя новое сообщение в Тайном Санте.',
      /^https:\/\//i.test(APP_URL) ? APP_URL : '',
    );
  } catch (error) {
    console.warn('chat notification failed', error instanceof Error ? error.message : error);
  }

  return {
    message: {
      id: message.id,
      mine: true,
      text: message.text,
      createdAt: message.createdAt?.toISOString?.() ?? null,
    },
  };
}
