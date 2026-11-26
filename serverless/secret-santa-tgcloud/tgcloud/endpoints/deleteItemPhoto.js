import { fail, requireMember } from '../lib/access.js';
import { canEditWishlist } from '../lib/domain/campaign.js';
import { clearWishlistPhoto, getWishlistItem } from '../lib/store.js';

export default async function (input, ctx) {
  const auth = await requireMember(ctx, input?.campaignId);
  if (!canEditWishlist(auth.campaign)) {
    fail('CAMPAIGN_CLOSED', 'Кампания завершена, фото больше нельзя менять.');
  }
  const item = await getWishlistItem(input?.itemId);
  if (!item || item.userId !== auth.tgId || item.campaignId !== auth.campaign.id) {
    fail('NOT_FOUND', 'Желание не найдено.');
  }
  await clearWishlistPhoto(item.id);
  return { ok: true };
}
