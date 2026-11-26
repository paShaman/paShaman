import { APP_URL } from '../config.js';
import { assertTransition } from '../domain/campaign.js';
import { progress } from '../domain/membership.js';
import {
  campaignShippingStats,
  campaignStats,
  getCampaign,
  getCampaignByCode,
  joinCampaign,
  listConfirmedMembers,
  listMembersDetailed,
  listUserCampaigns,
  logAudit,
  purgeClaims,
  purgeDelivery,
  revealAssignments,
  setCampaignLogo,
  updateCampaign,
} from '../store.js';
import {
  campaignKeyboard,
  confirmKeyboard,
  myCampaignsKeyboard,
  rulesKeyboard,
} from '../keyboards.js';
import {
  campaignCard,
  campaignStatsText,
  rulesText,
  STATUS_LABELS,
} from '../texts.js';
import { render, sendHtml, sendMiniApp } from '../telegram.js';
import { codeFromStartPayload, esc, normalizeCode } from '../utils.js';
import { notifySuperAdminsCampaignFinished } from './admin-notify.js';
import { isSuperAdmin, loadCampaignFor, loadOrganizerCampaign } from './common.js';
import { clear, setStep } from './session.js';

function appLink() {
  return /^https:\/\//i.test(APP_URL) ? APP_URL : '';
}

export async function openCampaign(chatId, userId, campaignId, messageId = null, prefix = '') {
  let loaded = await loadCampaignFor(userId, campaignId);
  if (loaded.error && isSuperAdmin(userId)) {
    const campaign = await getCampaign(campaignId);
    if (campaign) loaded = { campaign, member: { status: 'joined' } };
  }
  if (loaded.error) {
    await sendHtml(chatId, loaded.error);
    return false;
  }
  const { campaign, member } = loaded;
  if (['cancelled'].includes(campaign.status)) {
    await sendHtml(chatId, `Кампания «${esc(campaign.title)}» отменена.`);
    return false;
  }
  const members = await listMembersDetailed(campaign.id);
  const stats = await campaignStats(campaign.id);
  const counts = progress(members);
  const isOrganizer = campaign.organizerId === Number(userId) || isSuperAdmin(userId);
  const text = `${prefix ? `${prefix}\n\n` : ''}${campaignCard(campaign, {
    progress: counts,
    stats,
    isOrganizer,
    isConfirmed: member.status === 'confirmed',
  })}`;
  const keyboard = campaignKeyboard({
    campaign,
    isOrganizer,
    appUrl: appLink(),
  });
  await render(chatId, messageId, text, keyboard);
  return true;
}

export async function openMyCampaigns(chatId, userId, messageId = null) {
  const items = await listUserCampaigns(userId);
  if (items.length === 0) {
    await render(
      chatId,
      messageId,
      '🎄 <b>Мои кампании</b>\n\nПока пусто. Создайте кампанию или вступите по коду от организатора.',
      myCampaignsKeyboard([]),
    );
    return;
  }
  const lines = ['🎄 <b>Мои кампании</b>', ''];
  for (const { campaign, membership } of items.slice(0, 10)) {
    lines.push(
      `${membership.role === 'organizer' ? '⚙️' : '🎁'} <b>${esc(campaign.title)}</b> — ${
        STATUS_LABELS[campaign.status] ?? campaign.status
      }${membership.status === 'confirmed' ? ' · вы участвуете' : ''}`,
    );
  }
  if (items.length > 10) lines.push('', `И ещё ${items.length - 10}. Показываю первые 10.`);
  await render(chatId, messageId, lines.join('\n'), myCampaignsKeyboard(items));
}

export async function openRules(chatId, userId, campaignId, messageId = null) {
  const loaded = await loadCampaignFor(userId, campaignId);
  if (loaded.error) {
    await sendHtml(chatId, loaded.error);
    return;
  }
  await render(chatId, messageId, rulesText(loaded.campaign), rulesKeyboard(campaignId));
}

export async function askLogo(chatId, userId, campaignId) {
  const loaded = await loadOrganizerCampaign(userId, campaignId);
  if (loaded.error) {
    await sendHtml(chatId, loaded.error);
    return;
  }
  if (['completed', 'cancelled'].includes(loaded.campaign.status)) {
    await sendHtml(chatId, 'Кампания уже завершена.');
    return;
  }
  await setStep(userId, `logo:${loaded.campaign.id}`, { campaignId: loaded.campaign.id });
  await sendHtml(
    chatId,
    `🖼 <b>Логотип кампании</b>\n\nПришлите фото-логотип или /skip, чтобы убрать текущий. /cancel — отмена.`,
  );
}

export async function handleCampaignLogoPhoto(chatId, userId, session, message) {
  const campaignId = Number(String(session?.step ?? '').split(':')[1]);
  const loaded = await loadOrganizerCampaign(userId, campaignId);
  if (loaded.error) {
    await clear(userId);
    await sendHtml(chatId, loaded.error);
    return true;
  }
  if (['completed', 'cancelled'].includes(loaded.campaign.status)) {
    await clear(userId);
    await sendHtml(chatId, 'Кампания уже завершена.');
    return true;
  }
  const photos = Array.isArray(message?.photo) ? message.photo : [];
  if (photos.length === 0) return false;
  const fileId = photos[photos.length - 1].file_id;
  await setCampaignLogo(loaded.campaign.id, fileId);
  await clear(userId);
  await sendHtml(chatId, 'Логотип обновлён.');
  await openCampaign(chatId, userId, loaded.campaign.id);
  return true;
}

export async function handleCampaignLogoText(chatId, userId, session, text) {
  const campaignId = Number(String(session?.step ?? '').split(':')[1]);
  const loaded = await loadOrganizerCampaign(userId, campaignId);
  if (loaded.error) {
    await clear(userId);
    await sendHtml(chatId, loaded.error);
    return true;
  }
  if (String(text).trim().toLowerCase() === '/skip') {
    await setCampaignLogo(loaded.campaign.id, '');
    await clear(userId);
    await sendHtml(chatId, 'Логотип убран.');
    await openCampaign(chatId, userId, loaded.campaign.id);
    return true;
  }
  await sendHtml(chatId, 'Пришлите фото-логотип или /skip, чтобы убрать текущий.');
  return true;
}

export async function startFromPayload(chatId, userId, payload) {
  const code = codeFromStartPayload(payload);
  if (!code) {
    await openMyCampaigns(chatId, userId);
    return;
  }
  const campaign = await getCampaignByCode(normalizeCode(code));
  if (!campaign) {
    await sendHtml(chatId, 'Код приглашения не найден. Проверьте ссылку или попросите новый код.');
    return;
  }
  if (campaign.status !== 'recruiting') {
    await sendHtml(chatId, `Кампания «${esc(campaign.title)}» уже закрыта для новых участников.`);
    if (campaign.status !== 'cancelled') {
      await openCampaign(chatId, userId, campaign.id);
    }
    return;
  }
  await joinCampaign(campaign.id, userId);
  await logAudit(campaign.id, userId, 'campaign.join', {});
  await openCampaign(chatId, userId, campaign.id, null, 'Вы вступили в кампанию.');
}

export async function handleLock(chatId, userId, campaignId, messageId = null) {
  const loaded = await loadOrganizerCampaign(userId, campaignId);
  if (loaded.error) {
    await sendHtml(chatId, loaded.error);
    return;
  }
  if (loaded.campaign.status !== 'recruiting') {
    await openCampaign(chatId, userId, campaignId, messageId, 'Набор уже закрыт.');
    return;
  }
  assertTransition(loaded.campaign.status, 'locked');
  await updateCampaign(campaignId, { status: 'locked', lockedAt: new Date() });
  await logAudit(campaignId, userId, 'campaign.lock', {});
  await openCampaign(chatId, userId, campaignId, messageId, 'Набор закрыт. Теперь можно собрать пары.');
}

export async function handleStartGifting(chatId, userId, campaignId, messageId = null) {
  const loaded = await loadOrganizerCampaign(userId, campaignId);
  if (loaded.error) {
    await sendHtml(chatId, loaded.error);
    return;
  }
  if (loaded.campaign.status !== 'drawn') {
    await openCampaign(chatId, userId, campaignId, messageId, 'Вручение можно начать после жеребьёвки.');
    return;
  }
  await updateCampaign(campaignId, { status: 'gifting' });
  await logAudit(campaignId, userId, 'campaign.gifting', {});
  const members = await listConfirmedMembers(campaignId);
  for (const member of members) {
    await sendMiniApp(
      member.userId,
      '🎁 Вручение началось. Отметьте отправку и получение подарка в приложении.',
      appLink(),
    );
  }
  await openCampaign(chatId, userId, campaignId, messageId, 'Вручение началось. Участникам отправлено напоминание.');
}

export async function handleStats(chatId, userId, campaignId, messageId = null) {
  const loaded = await loadCampaignFor(userId, campaignId);
  if (loaded.error) {
    await sendHtml(chatId, loaded.error);
    return;
  }
  if (loaded.campaign.organizerId !== Number(userId) && !isSuperAdmin(userId)) {
    await sendHtml(chatId, 'Статистика доступна организатору.');
    return;
  }
  const members = await listMembersDetailed(campaignId);
  const stats = await campaignStats(campaignId);
  const counts = progress(members);
  await render(
    chatId,
    messageId,
    campaignStatsText(loaded.campaign, stats, counts),
    campaignKeyboard({ campaign: loaded.campaign, isOrganizer: true, appUrl: appLink() }),
  );
}

export async function handleCompleteConfirm(chatId, userId, campaignId, messageId = null) {
  const loaded = await loadOrganizerCampaign(userId, campaignId);
  if (loaded.error) {
    await sendHtml(chatId, loaded.error);
    return;
  }
  if (!['drawn', 'gifting'].includes(loaded.campaign.status)) {
    await sendHtml(chatId, 'Завершить можно только кампанию с собранными парами.');
    return;
  }
  await render(
    chatId,
    messageId,
    'Завершить кампанию?\n\nАдреса доставки и отметки «куплено» будут удалены, а Санты раскроются участникам. Это действие нельзя отменить.',
    confirmKeyboard(campaignId, 'm', loaded.campaign.version),
  );
}

export async function handleCancelConfirm(chatId, userId, campaignId, messageId = null) {
  const loaded = await loadOrganizerCampaign(userId, campaignId);
  if (loaded.error) {
    await sendHtml(chatId, loaded.error);
    return;
  }
  if (['completed', 'cancelled'].includes(loaded.campaign.status)) {
    await sendHtml(chatId, 'Кампания уже завершена.');
    return;
  }
  await render(
    chatId,
    messageId,
    'Отменить кампанию?\n\nУчастники получат уведомление, доступ к чату закроется, адреса и отметки будут удалены. Это действие нельзя отменить.',
    confirmKeyboard(campaignId, 'n', loaded.campaign.version),
  );
}

export async function handleFinal(chatId, userId, campaignId, kind, token) {
  const loaded = await loadOrganizerCampaign(userId, campaignId);
  if (loaded.error) {
    await sendHtml(chatId, loaded.error);
    return;
  }
  if (Number(token) !== Number(loaded.campaign.version)) {
    await sendHtml(chatId, 'Данные кампании изменились. Откройте карточку заново.');
    return;
  }
  const campaign = loaded.campaign;
  const members = await listConfirmedMembers(campaignId);

  if (kind === 'm') {
    if (campaign.status === 'drawn') {
      await updateCampaign(campaignId, { status: 'gifting' });
    }
    assertTransition(campaign.status === 'drawn' ? 'gifting' : campaign.status, 'completed');
    await updateCampaign(campaignId, { status: 'completed', completedAt: new Date() });
    await purgeDelivery(campaignId);
    await purgeClaims(campaignId);
    await revealAssignments(campaignId);
    await logAudit(campaignId, userId, 'campaign.complete', {});
    let summary;
    if (campaign.deliveryMode === 'meetup') {
      summary = { tracked: false };
    } else if (campaign.deliveryMode === 'hybrid') {
      const stats = await campaignShippingStats(campaignId);
      summary = { tracked: true, confirmed: stats.shipping, received: stats.received };
    } else {
      const stats = await campaignStats(campaignId);
      summary = { tracked: true, confirmed: stats.confirmed, received: stats.received };
    }
    await notifySuperAdminsCampaignFinished(campaign, summary);
    const allMembers = await listMembersDetailed(campaignId);
    const greeting = [
      `🎁 <b>Кампания «${esc(campaign.title)}» завершена</b>`,
      '',
      'Всем спасибо за участие! Увидимся снова!',
      campaign.revealSanta
        ? 'Санты раскрыты, личные данные удалены.'
        : 'Личные данные удалены.',
    ].join('\n');
    for (const member of allMembers) {
      if (['removed', 'declined'].includes(member.status)) continue;
      try {
        await sendMiniApp(member.userId, greeting, appLink());
      } catch (error) {
        console.warn(
          `completion notify ${member.userId} failed`,
          error instanceof Error ? error.message : error,
        );
      }
    }
    await openCampaign(chatId, userId, campaignId, null, 'Кампания завершена.');
    return;
  }

  assertTransition(campaign.status, 'cancelled');
  await updateCampaign(campaignId, { status: 'cancelled', cancelledAt: new Date() });
  await purgeDelivery(campaignId);
  await purgeClaims(campaignId);
  await logAudit(campaignId, userId, 'campaign.cancel', {});
  for (const member of members) {
    await sendMiniApp(
      member.userId,
      '🚫 <b>Кампания отменена</b>\n\nОрганизатор отменил кампанию. Спасибо, что были рядом.',
      appLink(),
    );
  }
  await openCampaign(chatId, userId, campaignId, null, 'Кампания отменена.');
}

export { appLink };
