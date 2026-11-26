import { requirePair } from '../lib/access.js';
import { markMessagesRead } from '../lib/store.js';

export default async function (input, ctx) {
  const auth = await requirePair(ctx, input?.campaignId);
  const upToId = Number(input?.upToId) || null;
  await markMessagesRead(auth.assignment.id, auth.tgId, upToId);
  return { ok: true };
}
