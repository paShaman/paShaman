import { fail, requirePair } from '../lib/access.js';
import { isMessageVisible } from '../lib/domain/chat.js';
import { listChatMessages, markMessagesRead } from '../lib/store.js';
import { serializeThreadMessage } from '../lib/serialize.js';

export default async function (input, ctx) {
  const auth = await requirePair(ctx, input?.campaignId);
  if (auth.campaign.status === 'cancelled') fail('CAMPAIGN_CANCELLED', 'Кампания отменена.');
  const afterId = Number(input?.afterId) || 0;
  const rows = (await listChatMessages(auth.assignment.id, afterId)).filter(isMessageVisible);
  if (input?.markRead === true) {
    const upToId = Number(input?.upToId);
    await markMessagesRead(
      auth.assignment.id,
      auth.tgId,
      Number.isSafeInteger(upToId) && upToId > 0 ? upToId : null,
    );
  }
  return {
    role: auth.role,
    chatBlocked: Boolean(auth.assignment.chatBlocked),
    campaignStatus: auth.campaign.status,
    messages: rows.map((message) => serializeThreadMessage(message, auth.tgId)),
  };
}
