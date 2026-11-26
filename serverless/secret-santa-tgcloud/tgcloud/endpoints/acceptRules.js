import { fail, requireMember } from '../lib/access.js';
import { updateMembership } from '../lib/store.js';

export default async function (input, ctx) {
  const auth = await requireMember(ctx, input?.campaignId);
  if (auth.campaign.status !== 'recruiting') {
    fail('CAMPAIGN_LOCKED', 'Правила можно принять только до закрытия набора.');
  }
  const version = Number(input?.rulesVersion);
  if (!Number.isInteger(version) || version !== Number(auth.campaign.rulesVersion)) {
    fail('RULES_CHANGED', 'Правила изменились. Обновите экран и прочитайте их заново.', {
      rulesVersion: auth.campaign.rulesVersion,
    });
  }
  const status = auth.member.status === 'confirmed' ? 'confirmed' : 'rules_accepted';
  const member = await updateMembership(auth.member.id, {
    status,
    rulesAcceptedAt: new Date(),
    rulesVersionAccepted: version,
  });
  return {
    status: member?.status,
    rulesVersionAccepted: version,
  };
}
