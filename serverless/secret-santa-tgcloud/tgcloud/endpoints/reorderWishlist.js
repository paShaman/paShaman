import { fail, requireMember } from '../lib/access.js';
import { canEditWishlist } from '../lib/domain/campaign.js';
import { normalizeOrder, WishlistError } from '../lib/domain/wishlist.js';
import { listWishlist, setWishlistOrder } from '../lib/store.js';

export default async function (input, ctx) {
  const auth = await requireMember(ctx, input?.campaignId);
  if (!canEditWishlist(auth.campaign)) {
    fail('CAMPAIGN_CLOSED', 'Кампания завершена, вишлист больше нельзя менять.');
  }
  const items = await listWishlist(auth.campaign.id, auth.tgId);
  let updates;
  try {
    updates = normalizeOrder(items, input?.itemIds);
  } catch (error) {
    if (error instanceof WishlistError) fail('VALIDATION', 'Порядок желаний некорректен.');
    throw error;
  }
  await setWishlistOrder(updates);
  return { ok: true };
}
