import { BROADCAST_PRESETS, MAX_BROADCAST, MAX_BROADCAST_TEXT } from '../config.js';
import {
  getMembership,
  listConfirmedMembers,
  logAudit,
  logBroadcast,
  setUserBlocked,
} from '../store.js';
import {
  broadcastMenuKeyboard,
  broadcastPreviewKeyboard,
} from '../keyboards.js';
import {
  broadcastHtml,
  broadcastMenuText,
  broadcastPreviewText,
  broadcastReport,
} from '../texts.js';
import { sendBroadcastMessage, sendHtml } from '../telegram.js';
import { esc } from '../utils.js';
import { loadOrganizerCampaign } from './common.js';
import { clear, getState, previewBroadcast, setStep } from './session.js';

export async function openBroadcast(chatId, userId, campaignId) {
  const loaded = await loadOrganizerCampaign(userId, campaignId);
  if (loaded.error) {
    await sendHtml(chatId, loaded.error);
    return;
  }
  if (['completed', 'cancelled'].includes(loaded.campaign.status)) {
    await sendHtml(chatId, 'Кампания уже завершена, рассылка недоступна.');
    return;
  }
  const recipients = await listConfirmedMembers(campaignId);
  const count = recipients.filter((member) => Number(member.userId) !== Number(userId)).length;
  await sendHtml(
    chatId,
    broadcastMenuText(loaded.campaign, count),
    broadcastMenuKeyboard(campaignId, BROADCAST_PRESETS),
  );
}

export async function previewPreset(chatId, userId, campaignId, key) {
  const loaded = await loadOrganizerCampaign(userId, campaignId);
  if (loaded.error) {
    await sendHtml(chatId, loaded.error);
    return;
  }
  const preset = BROADCAST_PRESETS.find((item) => item.key === key);
  if (!preset) {
    await sendHtml(chatId, 'Неизвестный шаблон рассылки.');
    return;
  }
  await previewBroadcast(userId, campaignId, preset.text, 'preset');
  const count = (await listConfirmedMembers(campaignId)).filter(
    (member) => Number(member.userId) !== Number(userId),
  ).length;
  await sendHtml(
    chatId,
    broadcastPreviewText(preset.text, count),
    broadcastPreviewKeyboard(campaignId),
  );
}

export async function startCustom(chatId, userId, campaignId) {
  const loaded = await loadOrganizerCampaign(userId, campaignId);
  if (loaded.error) {
    await sendHtml(chatId, loaded.error);
    return;
  }
  await setStep(userId, 'broadcast:text', { campaignId });
  await sendHtml(
    chatId,
    `Отправьте текст рассылки одним сообщением. Максимум ${MAX_BROADCAST_TEXT} символов.`,
  );
}

export async function handleBroadcastText(chatId, userId, session, text) {
  const body = String(text ?? '').trim();
  if (!body) {
    await sendHtml(chatId, 'Текст пустой. Отправьте сообщение или /cancel.');
    return true;
  }
  if (body.length > MAX_BROADCAST_TEXT) {
    await sendHtml(chatId, `Слишком длинно: максимум ${MAX_BROADCAST_TEXT} символов.`);
    return true;
  }
  const campaignId = session.data?.campaignId;
  await previewBroadcast(userId, campaignId, body, 'custom');
  const count = (await listConfirmedMembers(campaignId)).filter(
    (member) => Number(member.userId) !== Number(userId),
  ).length;
  await sendHtml(chatId, broadcastPreviewText(body, count), broadcastPreviewKeyboard(campaignId));
  return true;
}

export async function sendBroadcast(chatId, userId, campaignId) {
  const loaded = await loadOrganizerCampaign(userId, campaignId);
  if (loaded.error) {
    await sendHtml(chatId, loaded.error);
    return;
  }
  const session = await getState(userId);
  if (
    !session ||
    session.step !== 'broadcast:preview' ||
    Number(session.data?.campaignId) !== Number(campaignId)
  ) {
    await sendHtml(chatId, 'Превью рассылки устарело. Откройте рассылку заново.');
    return;
  }
  const body = String(session.data?.text ?? '');
  if (['completed', 'cancelled'].includes(loaded.campaign.status)) {
    await sendHtml(chatId, 'Кампания уже завершена, рассылка недоступна.');
    return;
  }
  const members = await listConfirmedMembers(campaignId);
  const recipients = members.filter((member) => Number(member.userId) !== Number(userId));
  if (recipients.length === 0) {
    await sendHtml(chatId, 'Нет получателей: в кампании нет подтверждённых участников.');
    return;
  }
  if (recipients.length > MAX_BROADCAST) {
    await sendHtml(
      chatId,
      `Получателей ${recipients.length}, а лимит одной рассылки — ${MAX_BROADCAST}. Разделите кампанию или отправьте позже.`,
    );
    return;
  }

  const html = broadcastHtml(loaded.campaign, body);
  const stats = { recipients: recipients.length, sent: 0, blocked: 0, failed: 0, limited: 0 };
  for (const member of recipients) {
    const result = await sendBroadcastMessage(member.userId, html);
    if (result === 'ok') stats.sent += 1;
    else if (result === 'blocked') {
      stats.blocked += 1;
      await setUserBlocked(member.userId, true);
    } else if (result === 'limited') stats.limited += 1;
    else stats.failed += 1;
  }

  await logBroadcast({
    campaignId,
    authorId: userId,
    kind: session.data?.kind ?? 'custom',
    text: body,
    recipients: stats.recipients,
    sent: stats.sent,
    blocked: stats.blocked,
    failed: stats.failed + stats.limited,
  });
  await logAudit(campaignId, userId, 'broadcast.send', stats);
  await clear(userId);
  await sendHtml(chatId, broadcastReport(stats));
}

export async function cancelBroadcast(chatId, userId, campaignId) {
  await clear(userId);
  await sendHtml(chatId, 'Рассылка отменена.');
  const loaded = await loadOrganizerCampaign(userId, campaignId);
  if (!loaded.error) {
    await sendHtml(chatId, `Кампания «${esc(loaded.campaign.title)}» осталась без изменений.`);
  }
}

export async function broadcastRecipientCount(campaignId, organizerId) {
  const members = await listConfirmedMembers(campaignId);
  return members.filter((member) => Number(member.userId) !== Number(organizerId)).length;
}

export async function isBroadcastMember(campaignId, userId) {
  const member = await getMembership(campaignId, userId);
  return Boolean(member);
}
