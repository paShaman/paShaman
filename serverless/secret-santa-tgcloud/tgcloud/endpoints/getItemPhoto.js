import { fail, requireMember } from '../lib/access.js';
import {
  getAssignmentForGiver,
  getWishlistItem,
} from '../lib/store.js';
import { photoDataUrl } from '../lib/telegram.js';

export default async function (input, ctx) {
  const auth = await requireMember(ctx, input?.campaignId);
  const item = await getWishlistItem(input?.itemId);
  if (!item || item.campaignId !== auth.campaign.id) {
    fail('NOT_FOUND', 'Желание не найдено.');
  }
  if (!item.photoFileId) fail('NO_PHOTO', 'У этого желания нет фото.');

  let allowed = item.userId === auth.tgId;
  if (!allowed) {
    const assignment = await getAssignmentForGiver(auth.campaign.id, auth.tgId);
    allowed = Boolean(assignment) && Number(assignment.receiverId) === Number(item.userId);
  }
  if (!allowed) fail('NOT_ALLOWED', 'Фото доступно только владельцу и его Санте.');

  try {
    const dataUrl = await photoDataUrl(item.photoFileId);
    return { dataUrl, version: item.photoVersion };
  } catch (error) {
    console.warn('getItemPhoto failed', error instanceof Error ? error.message : error);
    fail('PHOTO_UNAVAILABLE', 'Фото временно недоступно. Попросите обновить его.');
  }
}
