import { fail, requireMember } from '../lib/access.js';
import { canEditWishlist } from '../lib/domain/campaign.js';
import { deleteWishlistItem, getWishlistItem } from '../lib/store.js';

export default async function (input, ctx) {
  const auth = await requireMember(ctx, input?.campaignId);
  if (!canEditWishlist(auth.campaign)) {
    fail('CAMPAIGN_CLOSED', 'Кампания завершена, вишлист больше нельзя менять.');
  }
  const item = await getWishlistItem(input?.itemId);
  if (!item || item.userId !== auth.tgId || item.campaignId !== auth.campaign.id) {
    fail('NOT_FOUND', 'Желание не найдено.');
  }
  await deleteWishlistItem(item.id);
  return { ok: true };
}
