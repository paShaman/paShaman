import { fail, pairRoles, requireMember } from '../lib/access.js';
import { getDelivery, getUser } from '../lib/store.js';
import { displayName } from '../lib/utils.js';

export default async function (input, ctx) {
  const auth = await requireMember(ctx, input?.campaignId);
  if (auth.campaign.status === 'cancelled') fail('CAMPAIGN_CANCELLED', 'Кампания отменена.');
  const pairs = await pairRoles(auth.campaign.id, auth.tgId);
  if (!pairs.santa && !pairs.recipient) {
    fail('NO_ASSIGNMENT', 'Для этой кампании у вас нет пары.');
  }

  const reveal = auth.campaign.status === 'completed' && auth.campaign.revealSanta;
  const build = async (assignment, otherId, role, hideName) => {
    if (!assignment) return null;
    const [other, delivery] = await Promise.all([
      getUser(otherId),
      getDelivery(auth.campaign.id, assignment.receiverId),
    ]);
    const deliveryMethod =
      delivery?.method ?? (auth.campaign.deliveryMode === 'shipping' ? 'shipping' : null);
    return {
      role,
      giftStatus: assignment.giftStatus,
      chatBlocked: Boolean(assignment.chatBlocked),
      deliveryMethod,
      partnerName: hideName ? null : displayName(other),
      revealAvailable: reveal,
    };
  };
  const [santa, recipient] = await Promise.all([
    build(pairs.santa, pairs.santa?.receiverId, 'santa', false),
    build(pairs.recipient, pairs.recipient?.giverId, 'recipient', !reveal),
  ]);
  const primary = santa ?? recipient;

  return {
    role: primary.role,
    giftStatus: primary.giftStatus,
    chatBlocked: primary.chatBlocked,
    campaignStatus: auth.campaign.status,
    campaign: {
      id: auth.campaign.id,
      title: auth.campaign.title,
      status: auth.campaign.status,
      revealSanta: Boolean(auth.campaign.revealSanta),
    },
    partnerName: primary.partnerName,
    revealAvailable: reveal,
    roles: { santa: Boolean(santa), recipient: Boolean(recipient) },
    santa,
    recipient,
  };
}
