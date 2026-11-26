import { pairRoles, requireMember } from '../lib/access.js';
import { countUnreadMessages } from '../lib/store.js';

export default async function (input, ctx) {
  const auth = await requireMember(ctx, input?.campaignId);
  const pairs = await pairRoles(auth.campaign.id, auth.tgId);
  const [santa, recipient] = await Promise.all([
    pairs.santa ? countUnreadMessages(pairs.santa.id, auth.tgId) : 0,
    pairs.recipient ? countUnreadMessages(pairs.recipient.id, auth.tgId) : 0,
  ]);
  return {
    roles: ['santa', 'recipient'].filter((role) => Boolean(pairs[role])),
    unread: {
      santa: Number(santa) || 0,
      recipient: Number(recipient) || 0,
    },
  };
}
