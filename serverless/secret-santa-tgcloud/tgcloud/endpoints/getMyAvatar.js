import { requireUser } from '../lib/access.js';
import { profilePhotoDataUrl } from '../lib/telegram.js';

export default async function (input, ctx) {
  const auth = await requireUser(ctx);
  const photoUrl = ctx?.initData?.user?.photo_url;
  if (photoUrl) return { photo: photoUrl };
  try {
    const photo = await profilePhotoDataUrl(auth.tgId);
    return { photo };
  } catch (error) {
    console.warn('getMyAvatar failed', error instanceof Error ? error.message : error);
    return { photo: '' };
  }
}
