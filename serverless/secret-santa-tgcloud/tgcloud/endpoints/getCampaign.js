import { requireMember } from '../lib/access.js';
import {
  countWishlist,
  getDelivery,
  listMembersDetailed,
} from '../lib/store.js';
import { serializeCampaign } from '../lib/serialize.js';

export default async function (input, ctx) {
  const auth = await requireMember(ctx, input?.campaignId);
  const members = await listMembersDetailed(auth.campaign.id);
  const wishlistCount = await countWishlist(auth.campaign.id, auth.tgId);
  const delivery = await getDelivery(auth.campaign.id, auth.tgId);
  return serializeCampaign(auth.campaign, {
    members,
    membership: auth.member,
    viewerId: auth.tgId,
    wishlistCount,
    delivery,
    isOrganizer: auth.campaign.organizerId === auth.tgId,
  });
}
