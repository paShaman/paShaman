import { requirePair } from '../lib/access.js';
import { getUser } from '../lib/store.js';
import { displayName } from '../lib/utils.js';

export default async function (input, ctx) {
  const auth = await requirePair(ctx, input?.campaignId);
  const other = await getUser(auth.otherUserId);
  const reveal = auth.campaign.status === 'completed' && auth.campaign.revealSanta;
  return {
    role: auth.role,
    giftStatus: auth.assignment.giftStatus,
    chatBlocked: Boolean(auth.assignment.chatBlocked),
    campaignStatus: auth.campaign.status,
    campaign: {
      id: auth.campaign.id,
      title: auth.campaign.title,
      status: auth.campaign.status,
    },
    partnerName:
      auth.role === 'santa' || reveal ? displayName(other) : null,
    revealAvailable: reveal,
  };
}
