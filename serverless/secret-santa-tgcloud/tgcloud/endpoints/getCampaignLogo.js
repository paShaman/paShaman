import { requireMember } from '../lib/access.js';
import { photoDataUrl } from '../lib/telegram.js';

export default async function (input, ctx) {
  const auth = await requireMember(ctx, input?.campaignId);
  const version = Number(auth.campaign.logoVersion) || 0;
  if (!auth.campaign.logoFileId) return { photo: '', version };
  try {
    const photo = await photoDataUrl(auth.campaign.logoFileId);
    return { photo, version };
  } catch (error) {
    console.warn('getCampaignLogo failed', error instanceof Error ? error.message : error);
    return { photo: '', version };
  }
}
