import { MAX_PARTICIPANTS } from '../lib/config.js';
import { fail, requireUser } from '../lib/access.js';
import {
  countMembers,
  getCampaignByCode,
  hitRate,
  joinCampaign,
  logAudit,
} from '../lib/store.js';
import { normalizeCode, esc } from '../lib/utils.js';
import { notifyUser } from '../lib/bot/common.js';

export default async function (input, ctx) {
  const auth = await requireUser(ctx);
  const limiter = await hitRate(`join:${auth.tgId}`, 5, 60);
  if (!limiter.ok) fail('RATE_LIMITED', 'Слишком много попыток. Попробуйте через минуту.', { retryAfter: limiter.retryAfter });

  const code = normalizeCode(input?.code);
  if (!code || code.length !== 6) fail('BAD_CODE', 'Код должен состоять из 6 символов.');
  const campaign = await getCampaignByCode(code);
  if (!campaign) fail('NOT_FOUND', 'Кампания с таким кодом не найдена.');
  if (campaign.status !== 'recruiting') fail('CAMPAIGN_CLOSED', 'Набор в эту кампанию уже закрыт.');

  const confirmed = await countMembers(campaign.id, 'confirmed');
  if (Number(confirmed) >= MAX_PARTICIPANTS) fail('CAMPAIGN_FULL', 'В кампании уже максимум участников.');

  const member = await joinCampaign(campaign.id, auth.tgId);
  await logAudit(campaign.id, auth.tgId, 'campaign.join', {});
  if (campaign.organizerId !== auth.tgId) {
    await notifyUser(
      campaign.organizerId,
      `👋 В кампанию «${esc(campaign.title)}» вступил новый участник.`,
    );
  }
  return {
    campaignId: campaign.id,
    status: member?.status ?? 'joined',
    campaign: {
      id: campaign.id,
      title: campaign.title,
      status: campaign.status,
    },
  };
}
