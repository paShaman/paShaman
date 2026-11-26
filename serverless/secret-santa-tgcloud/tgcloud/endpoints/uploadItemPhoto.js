import { fail, requireMember } from '../lib/access.js';
import { canEditWishlist } from '../lib/domain/campaign.js';
import { base64ToBytes, detectImageMime } from '../lib/utils.js';
import { MAX_PHOTO_BYTES } from '../lib/config.js';
import {
  getWishlistItem,
  hitRate,
  setWishlistPhoto,
} from '../lib/store.js';
import { uploadPhotoToTelegram } from '../lib/telegram.js';

export default async function (input, ctx) {
  const auth = await requireMember(ctx, input?.campaignId);
  if (!canEditWishlist(auth.campaign)) {
    fail('CAMPAIGN_CLOSED', 'Кампания завершена, фото больше нельзя менять.');
  }
  const item = await getWishlistItem(input?.itemId);
  if (!item || item.userId !== auth.tgId || item.campaignId !== auth.campaign.id) {
    fail('NOT_FOUND', 'Желание не найдено.');
  }
  const limiter = await hitRate(`photo:${auth.tgId}`, 5, 60);
  if (!limiter.ok) fail('RATE_LIMITED', 'Слишком часто. Попробуйте через минуту.', { retryAfter: limiter.retryAfter });

  const raw = String(input?.dataBase64 ?? '');
  if (!raw) fail('VALIDATION', 'Файл не передан.');
  if (raw.length > Math.ceil((MAX_PHOTO_BYTES * 4) / 3) + 16) {
    fail('PHOTO_TOO_BIG', 'Фото больше допустимого размера.');
  }
  const bytes = base64ToBytes(raw);
  if (!bytes || bytes.length === 0) fail('VALIDATION', 'Не удалось прочитать файл.');
  if (bytes.length > MAX_PHOTO_BYTES) fail('PHOTO_TOO_BIG', 'Фото больше допустимого размера.');
  const mime = detectImageMime(bytes);
  if (!mime) fail('UNSUPPORTED_TYPE', 'Поддерживаются только JPEG и PNG.');

  let fileId;
  try {
    fileId = await uploadPhotoToTelegram(auth.tgId, bytes, mime);
  } catch (error) {
    console.warn('uploadItemPhoto failed', error instanceof Error ? error.message : error);
    fail('PHOTO_UPLOAD_FAILED', 'Не удалось загрузить фото. Попробуйте ещё раз.');
  }
  const updated = await setWishlistPhoto(item.id, fileId);
  return { version: updated?.photoVersion ?? item.photoVersion + 1 };
}
