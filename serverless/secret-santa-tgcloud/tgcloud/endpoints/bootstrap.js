import {
  APP_URL,
  MAX_BROADCAST,
  MAX_CHAT_TEXT,
  MAX_PARTICIPANTS,
  MAX_PHOTO_BYTES,
  MAX_WISHLIST_ITEMS,
} from '../lib/config.js';
import { listUserCampaigns } from '../lib/store.js';
import { requireUser } from '../lib/access.js';
import { isSuperAdmin } from '../lib/bot/common.js';
import { displayName } from '../lib/utils.js';

export default async function (input, ctx) {
  const auth = await requireUser(ctx);
  const campaigns = await listUserCampaigns(auth.tgId);
  return {
    user: {
      name: displayName(auth.user),
      lang: auth.user.lang,
      isSuperAdmin: isSuperAdmin(auth.tgId),
    },
    startParam: ctx?.initData?.start_param ?? null,
    appUrl: /^https:\/\//i.test(APP_URL) ? APP_URL : '',
    photoMaxBytes: MAX_PHOTO_BYTES,
    limits: {
      participants: MAX_PARTICIPANTS,
      wishlistItems: MAX_WISHLIST_ITEMS,
      broadcast: MAX_BROADCAST,
      chatText: MAX_CHAT_TEXT,
    },
    campaigns: campaigns.map(({ campaign, membership }) => ({
      id: campaign.id,
      code: campaign.code,
      title: campaign.title,
      status: campaign.status,
      role: membership.role,
      membershipStatus: membership.status,
      registrationEndsAt: campaign.registrationEndsAt?.toISOString?.() ?? null,
      giftDate: campaign.giftDate?.toISOString?.() ?? null,
      budget: { min: campaign.budgetMin, max: campaign.budgetMax, currency: campaign.currency },
      confirmed: membership.status === 'confirmed',
    })),
  };
}
