import { fail, requireMember } from '../lib/access.js';
import { canEditWishlist } from '../lib/domain/campaign.js';
import { canAddItem, nextSortOrder, normalizeWishlistInput, WishlistError } from '../lib/domain/wishlist.js';
import {
  countWishlist,
  createWishlistItem,
  getWishlistItem,
  hitRate,
  listWishlist,
  updateWishlistItem,
} from '../lib/store.js';
import { serializeItem } from '../lib/serialize.js';

export default async function (input, ctx) {
  const auth = await requireMember(ctx, input?.campaignId);
  if (!canEditWishlist(auth.campaign)) {
    fail('CAMPAIGN_CLOSED', 'Кампания завершена, вишлист больше нельзя менять.');
  }
  const limiter = await hitRate(`wish:${auth.tgId}`, 30, 60);
  if (!limiter.ok) fail('RATE_LIMITED', 'Слишком часто. Попробуйте через минуту.', { retryAfter: limiter.retryAfter });

  let value;
  try {
    value = normalizeWishlistInput(input);
  } catch (error) {
    if (error instanceof WishlistError) {
      fail(error.code, 'Проверьте название, ссылку и цену.', { field: error.details?.field });
    }
    throw error;
  }

  const itemId = Number(input?.itemId) || null;
  if (itemId) {
    const existing = await getWishlistItem(itemId);
    if (!existing || existing.userId !== auth.tgId || existing.campaignId !== auth.campaign.id) {
      fail('NOT_FOUND', 'Желание не найдено.');
    }
    const item = await updateWishlistItem(itemId, value);
    return { item: serializeItem(item) };
  }

  const count = await countWishlist(auth.campaign.id, auth.tgId);
  if (!canAddItem(count)) fail('LIMIT_REACHED', 'В вишлисте уже максимум 50 пунктов.');
  const items = await listWishlist(auth.campaign.id, auth.tgId);
  const item = await createWishlistItem(auth.campaign.id, auth.tgId, value, nextSortOrder(items));
  return { item: serializeItem(item) };
}
