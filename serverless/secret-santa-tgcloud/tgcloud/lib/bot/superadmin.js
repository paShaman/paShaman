import { globalStats, listReports, setUserBanned, getUser, countCampaigns, countUsers } from '../store.js';
import { adminKeyboard } from '../keyboards.js';
import { sendHtml } from '../telegram.js';
import { isSuperAdmin } from './common.js';
import { displayName, esc, parsePositiveInt } from '../utils.js';

export async function openAdmin(chatId, userId) {
  if (!isSuperAdmin(userId)) {
    await sendHtml(chatId, 'Админка доступна только супер-админу.');
    return;
  }
  const stats = await globalStats();
  const reports = await listReports(null, 100);
  const text = `🛠 <b>Админка</b>\n\nПользователей: ${stats.users}\nКампаний: ${stats.campaigns}\nЖалоб: ${reports.length}`;
  await sendHtml(chatId, text, adminKeyboard());
}

export async function openGlobalStats(chatId, userId) {
  if (!isSuperAdmin(userId)) {
    await sendHtml(chatId, 'Статистика доступна только супер-админу.');
    return;
  }
  const users = await countUsers();
  const campaigns = await countCampaigns();
  const reports = await listReports(null, 100);
  await sendHtml(
    chatId,
    `📊 <b>Бот</b>\n\nПользователей: ${users}\nКампаний: ${campaigns}\nЖалоб: ${reports.length}`,
    adminKeyboard(),
  );
}

export async function banById(chatId, userId, rawId) {
  if (!isSuperAdmin(userId)) {
    await sendHtml(chatId, 'Бан доступен только супер-админу.');
    return;
  }
  const id = parsePositiveInt(rawId);
  if (!id) {
    await sendHtml(chatId, 'Укажите Telegram ID: /ban 123456789');
    return;
  }
  const target = await getUser(id);
  if (!target) {
    await sendHtml(chatId, 'Пользователь ещё не писал боту.');
    return;
  }
  await setUserBanned(id, true);
  await sendHtml(chatId, `Пользователь ${esc(displayName(target))} заблокирован.`);
}

export async function unbanById(chatId, userId, rawId) {
  if (!isSuperAdmin(userId)) {
    await sendHtml(chatId, 'Разбан доступен только супер-админу.');
    return;
  }
  const id = parsePositiveInt(rawId);
  if (!id) {
    await sendHtml(chatId, 'Укажите Telegram ID: /unban 123456789');
    return;
  }
  await setUserBanned(id, false);
  await sendHtml(chatId, `Пользователь ${id} разблокирован.`);
}

export async function openCampaignsSummary(chatId, userId) {
  if (!isSuperAdmin(userId)) {
    await sendHtml(chatId, 'Недостаточно прав.');
    return;
  }
  const stats = await globalStats();
  await sendHtml(chatId, `Кампаний всего: ${stats.campaigns}`, adminKeyboard());
}
