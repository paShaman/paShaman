import { fail, requireMember } from '../lib/access.js';
import { logAudit, updateMembership } from '../lib/store.js';

export default async function (input, ctx) {
  const auth = await requireMember(ctx, input?.campaignId);
  if (auth.campaign.organizerId === auth.tgId) {
    fail('ORGANIZER_CANNOT_LEAVE', 'Организатор не может выйти из своей кампании. Отмените её.');
  }
  if (auth.campaign.status !== 'recruiting') {
    fail('CAMPAIGN_LOCKED', 'Набор уже закрыт, выйти из кампании нельзя.');
  }
  await updateMembership(auth.member.id, { status: 'declined' });
  await logAudit(auth.campaign.id, auth.tgId, 'campaign.leave', {});
  return { ok: true };
}
