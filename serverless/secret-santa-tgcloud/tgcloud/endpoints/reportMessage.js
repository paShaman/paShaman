import { fail, requirePair } from '../lib/access.js';
import { canReportMessage } from '../lib/domain/chat.js';
import { getChatMessage, reportChatMessage } from '../lib/store.js';
import { notifyNewReport } from '../lib/bot/moderation.js';

export default async function (input, ctx) {
  const auth = await requirePair(ctx, input?.campaignId, input?.role);
  const message = await getChatMessage(input?.messageId);
  if (!message || Number(message.assignmentId) !== Number(auth.assignment.id)) {
    fail('NOT_FOUND', 'Сообщение не найдено.');
  }
  const check = canReportMessage(message, auth.tgId);
  if (!check.ok) fail(check.code, 'Нельзя пожаловаться на это сообщение.');
  if (check.already) return { ok: true };
  const reason = String(input?.reason ?? '').trim().slice(0, 500);
  await reportChatMessage(message.id, auth.tgId, reason);
  try {
    await notifyNewReport(message.id);
  } catch (error) {
    console.warn('report notification failed', error instanceof Error ? error.message : error);
  }
  return { ok: true };
}
