import { ADMIN_IDS } from '../config.js';
import {
  blockAssignmentChat,
  getAssignmentById,
  getCampaign,
  getChatMessage,
  getUser,
  hideChatMessage,
  listReports,
  logAudit,
  setUserBanned,
} from '../store.js';
import { displayName, esc } from '../utils.js';
import { reportText } from '../texts.js';
import { reportActionsKeyboard } from '../keyboards.js';
import { render, sendHtml } from '../telegram.js';
import { isSuperAdmin, notifyUser } from './common.js';

async function reportContext(messageId) {
  const message = await getChatMessage(messageId);
  if (!message) return { error: 'Сообщение не найдено.' };
  const assignment = await getAssignmentById(message.assignmentId);
  if (!assignment) return { error: 'Пара не найдена.' };
  const campaign = await getCampaign(message.campaignId);
  if (!campaign) return { error: 'Кампания не найдена.' };
  const sender = await getUser(message.senderUserId);
  const otherId =
    Number(assignment.giverId) === Number(message.senderUserId)
      ? assignment.receiverId
      : assignment.giverId;
  const other = await getUser(otherId);
  return { message, assignment, campaign, sender, other };
}

export async function notifyNewReport(messageId) {
  const context = await reportContext(messageId);
  if (context.error) return;
  const text = reportText(context.message, context.assignment, displayName(context.sender), displayName(context.other));
  const recipients = new Set([context.campaign.organizerId, ...ADMIN_IDS.map(Number)]);
  for (const id of recipients) {
    await notifyUser(
      id,
      text,
      reportActionsKeyboard(
        context.message.id,
        context.assignment.id,
        context.message.senderUserId,
        isSuperAdmin(id),
      ),
    );
  }
}

export async function openReport(chatId, userId, messageId, messageIdToEdit = null) {
  const context = await reportContext(messageId);
  if (context.error) {
    await sendHtml(chatId, context.error);
    return;
  }
  if (
    context.campaign.organizerId !== Number(userId) &&
    !isSuperAdmin(userId)
  ) {
    await sendHtml(chatId, 'Жалоба доступна организатору кампании.');
    return;
  }
  await render(
    chatId,
    messageIdToEdit,
    reportText(context.message, context.assignment, displayName(context.sender), displayName(context.other)),
    reportActionsKeyboard(
      context.message.id,
      context.assignment.id,
      context.message.senderUserId,
      isSuperAdmin(userId),
    ),
  );
}

export async function hideReported(chatId, userId, messageId) {
  const context = await reportContext(messageId);
  if (context.error) {
    await sendHtml(chatId, context.error);
    return;
  }
  if (context.campaign.organizerId !== Number(userId) && !isSuperAdmin(userId)) {
    await sendHtml(chatId, 'Недостаточно прав.');
    return;
  }
  await hideChatMessage(messageId, userId);
  await logAudit(context.campaign.id, userId, 'report.hide', { messageId: Number(messageId) });
  await sendHtml(chatId, 'Сообщение скрыто у обеих сторон.');
}

export async function blockReportedChat(chatId, userId, assignmentId) {
  const assignment = await getAssignmentById(assignmentId);
  if (!assignment) {
    await sendHtml(chatId, 'Пара не найдена.');
    return;
  }
  const campaign = await getCampaign(assignment.campaignId);
  if (!campaign) {
    await sendHtml(chatId, 'Кампания не найдена.');
    return;
  }
  if (campaign.organizerId !== Number(userId) && !isSuperAdmin(userId)) {
    await sendHtml(chatId, 'Недостаточно прав.');
    return;
  }
  await blockAssignmentChat(assignment.id, true);
  await logAudit(campaign.id, userId, 'chat.block', { assignmentId: assignment.id });
  await notifyUser(assignment.giverId, 'Переписка в этой паре остановлена организатором.');
  await notifyUser(assignment.receiverId, 'Переписка в этой паре остановлена организатором.');
  await sendHtml(chatId, 'Переписка в паре заблокирована.');
}

export async function banUser(chatId, actorId, targetUserId) {
  if (!isSuperAdmin(actorId)) {
    await sendHtml(chatId, 'Бан доступен только супер-админу.');
    return;
  }
  const target = await getUser(targetUserId);
  if (!target) {
    await sendHtml(chatId, 'Пользователь не найден.');
    return;
  }
  await setUserBanned(targetUserId, true);
  await logAudit(null, actorId, 'user.ban', { userId: Number(targetUserId) });
  await sendHtml(chatId, `Пользователь ${esc(displayName(target))} заблокирован.`);
}

export async function openReports(chatId, userId, page = 1, messageIdToEdit = null) {
  if (!isSuperAdmin(userId)) {
    await sendHtml(chatId, 'Жалобы доступны супер-админу.');
    return;
  }
  const reports = await listReports(null, 100);
  const pageSize = 10;
  const totalPages = Math.max(1, Math.ceil(reports.length / pageSize));
  const safePage = Math.min(Math.max(1, Number(page) || 1), totalPages);
  const slice = reports.slice((safePage - 1) * pageSize, safePage * pageSize);
  const lines = ['⚠️ <b>Жалобы</b>', `Страница ${safePage} из ${totalPages}`, ''];
  if (slice.length === 0) lines.push('Жалоб нет.');
  for (const report of slice) {
    lines.push(
      `#${report.id} · ${report.reportedAt ? 'есть' : '—'} · ${String(report.text).slice(0, 60)}`,
    );
  }
  const keyboardRows = slice.map((report) => [
    { text: `#${report.id}`, callback_data: `ss:rv:${report.id.toString(36)}` },
  ]);
  keyboardRows.push([{ text: 'Мои кампании', callback_data: 'ss:my' }]);
  await render(chatId, messageIdToEdit, lines.join('\n'), { inline_keyboard: keyboardRows });
}

export async function reportedPair(messageId) {
  return reportContext(messageId);
}
