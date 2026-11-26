import { requireSanta } from '../lib/access.js';
import {
  getDelivery,
  listClaims,
  listWishlist,
} from '../lib/store.js';
import { displayName } from '../lib/utils.js';
import { serializeItem } from '../lib/serialize.js';

export default async function (input, ctx) {
  const auth = await requireSanta(ctx, input?.campaignId);
  const items = await listWishlist(auth.campaign.id, auth.assignment.receiverId);
  const claims = await listClaims(auth.assignment.id);
  const claimed = new Set(claims.map((claim) => Number(claim.itemId)));
  const delivery = await getDelivery(auth.campaign.id, auth.assignment.receiverId);
  return {
    campaign: {
      id: auth.campaign.id,
      title: auth.campaign.title,
      status: auth.campaign.status,
      revealSanta: Boolean(auth.campaign.revealSanta),
    },
    receiver: {
      name: displayName(auth.receiver),
    },
    wishlist: items.map((item) => serializeItem(item, { claimed: claimed.has(Number(item.id)) })),
    delivery: delivery
      ? {
          method: delivery.method,
          recipientName: delivery.recipientName,
          address: delivery.address,
          phone: delivery.phone,
          comment: delivery.comment,
        }
      : null,
    giftStatus: auth.assignment.giftStatus,
    chatBlocked: Boolean(auth.assignment.chatBlocked),
    chatAvailable: !auth.assignment.chatBlocked && ['drawn', 'gifting'].includes(auth.campaign.status),
  };
}
