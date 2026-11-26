import { fail, requireMember } from '../lib/access.js';
import { getMembershipById } from '../lib/store.js';
import { profilePhotoDataUrl } from '../lib/telegram.js';

export default async function (input, ctx) {
  const auth = await requireMember(ctx, input?.campaignId);
  const member = await getMembershipById(input?.memberId);
  if (
    !member ||
    Number(member.campaignId) !== Number(auth.campaign.id) ||
    ['removed', 'declined'].includes(member.status)
  ) {
    fail('NOT_FOUND', 'Участник не найден.');
  }

  if (Number(member.userId) === Number(auth.tgId)) {
    const photoUrl = ctx?.initData?.user?.photo_url;
    if (photoUrl) return { photo: photoUrl };
  }

  try {
    const photo = await profilePhotoDataUrl(member.userId);
    return { photo };
  } catch (error) {
    console.warn('getMemberAvatar failed', error instanceof Error ? error.message : error);
    return { photo: '' };
  }
}
