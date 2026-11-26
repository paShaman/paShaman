import { fail, requireMember } from '../lib/access.js';
import { canConfirm } from '../lib/domain/membership.js';
import {
  countWishlist,
  getDelivery,
  logAudit,
  updateMembership,
} from '../lib/store.js';
import { notifyUser } from '../lib/bot/common.js';
import { displayName, esc } from '../lib/utils.js';

export default async function (input, ctx) {
  const auth = await requireMember(ctx, input?.campaignId);
  const wishlistCount = await countWishlist(auth.campaign.id, auth.tgId);
  const delivery = await getDelivery(auth.campaign.id, auth.tgId);
  const check = canConfirm({
    campaign: auth.campaign,
    member: auth.member,
    wishlistCount,
    delivery,
  });
  if (!check.ok) {
    const messages = {
      CAMPAIGN_LOCKED: 'Набор уже закрыт.',
      RULES_NOT_ACCEPTED: 'Сначала примите правила кампании.',
      WISHLIST_EMPTY: 'Добавьте хотя бы один пункт в вишлист.',
      DELIVERY_REQUIRED: 'Заполните адрес доставки и укажите почту или пункт выдачи.',
      NOT_MEMBER: 'Вы не участвуете в кампании.',
    };
    fail(check.code, messages[check.code] ?? 'Не удалось подтвердить участие.');
  }
  if (check.already) return { status: 'confirmed' };
  const member = await updateMembership(auth.member.id, {
    status: 'confirmed',
    confirmedAt: new Date(),
  });
  await logAudit(auth.campaign.id, auth.tgId, 'member.confirm', {});
  if (auth.campaign.organizerId !== auth.tgId) {
    await notifyUser(
      auth.campaign.organizerId,
      `✅ Участник ${esc(displayName(auth.user))} подтвердил участие в кампании «${esc(auth.campaign.title)}».`,
    );
  }
  return { status: member?.status ?? 'confirmed' };
}
