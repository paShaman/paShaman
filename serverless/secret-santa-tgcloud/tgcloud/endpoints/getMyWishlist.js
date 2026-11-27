import { requireMember } from '../lib/access.js';
import { listWishlist } from '../lib/store.js';
import { serializeItem } from '../lib/serialize.js';
import { canEditWishlist } from '../lib/domain/campaign.js';
import { MAX_WISHLIST_ITEMS } from '../lib/config.js';

export default async function (input, ctx) {
  const auth = await requireMember(ctx, input?.campaignId);
  const items = await listWishlist(auth.campaign.id, auth.tgId);
  const budget = { min: auth.campaign.budgetMin, max: auth.campaign.budgetMax };
  return {
    items: items.map((item) => serializeItem(item, { budget })),
    canEdit: canEditWishlist(auth.campaign),
    limit: MAX_WISHLIST_ITEMS,
  };
}
