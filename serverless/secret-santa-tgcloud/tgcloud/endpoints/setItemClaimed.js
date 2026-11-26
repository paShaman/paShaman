import { fail, requireSanta } from '../lib/access.js';
import { getWishlistItem, setClaim } from '../lib/store.js';

export default async function (input, ctx) {
  const auth = await requireSanta(ctx, input?.campaignId);
  if (!['drawn', 'gifting'].includes(auth.campaign.status)) {
    fail('CAMPAIGN_CLOSED', 'Кампания уже завершена.');
  }
  const item = await getWishlistItem(input?.itemId);
  if (!item || item.campaignId !== auth.campaign.id || item.userId !== auth.assignment.receiverId) {
    fail('NOT_FOUND', 'Желание не найдено.');
  }
  const claimed = Boolean(input?.claimed);
  await setClaim(item.id, auth.campaign.id, auth.tgId, auth.assignment.id, claimed);
  return { claimed };
}
