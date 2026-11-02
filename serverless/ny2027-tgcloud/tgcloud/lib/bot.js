import {
  ADMIN_IDS,
  BROADCAST_TEXT_MAX,
  MAX_BROADCAST,
  SIZES,
} from './config.js';
import {
  adminDeleteAllKeyboard,
  adminDeleteKeyboard,
  broadcastConfirmKeyboard,
  buildCsv,
  btn,
  confirmHtml,
  confirmKeyboard,
  deleteConfirmKeyboard,
  editKeyboard,
  esc,
  formatBroadcastHtml,
  inline,
  normalizeSpaces,
  recordSummaryHtml,
  recordVersion,
  registerKeyboard,
  sizeKeyboard,
  successHtml,
  validCompany,
  validFio,
  welcomeKeyboard,
} from './utils.js';
import {
  answerCallback,
  broadcastSend,
  editText,
  sendCsv,
  sendHtml,
  sendWelcome,
  setBotCommands,
} from './telegram.js';
import {
  broadcastRecipients,
  clearAllSessions,
  clearBroadcast,
  clearSession,
  deleteAllRecords,
  deleteRecord,
  findRecord,
  getBroadcast,
  getSession,
  loadArchive,
  loadRegistrations,
  markUsersDelivery,
  rememberUser,
  saveRecord,
  setBroadcast,
  setSession,
  statsData,
} from './store.js';

const COMMANDS = new Set([
  '/start',
  '/help',
  '/my',
  '/cancel',
  '/id',
  '/stats',
  '/list',
  '/export',
  '/broadcast',
  '/delete',
  '/deleteall',
  '/setcommands',
]);

function isAdmin(userId) {
  const ids = ADMIN_IDS.map(String);
  return ids.includes(String(userId));
}

function nowSec() {
  return Math.floor(Date.now() / 1000);
}

// ---------------------------------------------------------------------------
// Сообщения
// ---------------------------------------------------------------------------

export async function handleMessage(message) {
  const chat = message.chat ?? {};
  const from = message.from ?? {};
  const chatId = String(chat.id ?? '');
  const userId = String(from.id ?? '');
  const chatType = String(chat.type ?? 'private');
  const text = String(message.text ?? '').trim();

  if (!chatId || !userId) return;

  if (chatType !== 'private') {
    await sendHtml(chatId, '🤖 Бот работает только в личных сообщениях.');
    return;
  }

  await rememberUser(chatId, from);

  if (!text) {
    const session = await getSession(userId);
    const draft = isAdmin(userId) ? await getBroadcast(userId) : null;
    if (session || draft) {
      await sendHtml(chatId, 'Пожалуйста, отправьте ответ текстом.');
    }
    return;
  }

  const first = text.split(/\s+/)[0] ?? '';
  const command = first.toLowerCase().split('@')[0];
  const arg = text.slice(first.length).trim();

  if (command === '/id') {
    await sendHtml(chatId, `🆔 Ваш Telegram ID: <code>${userId}</code>`);
    return;
  }

  if (isAdmin(userId)) {
    const draft = await getBroadcast(userId);
    if (draft && !text.startsWith('/')) {
      await saveBroadcastText(chatId, userId, text);
      return;
    }
  }

  if (!COMMANDS.has(command)) {
    const session = await getSession(userId);
    if (session && (await handleSessionText(chatId, userId, session, text))) return;
  }

  switch (command) {
    case '/start':
      await cmdStart(chatId, userId);
      return;
    case '/help':
      await cmdHelp(chatId, userId);
      return;
    case '/setcommands':
      await cmdSetCommands(chatId, userId);
      return;
    case '/my':
      await cmdMy(chatId, userId);
      return;
    case '/cancel': {
      const hadBroadcast = (await getBroadcast(userId)) !== null;
      await clearSession(userId);
      await clearBroadcast(userId);
      await sendHtml(
        chatId,
        hadBroadcast ? 'Рассылка отменена.' : 'Заполнение отменено. Вернуться к анкете: /start',
      );
      return;
    }
    case '/broadcast':
      await startBroadcast(chatId, userId);
      return;
    case '/stats':
      await cmdStats(chatId, userId);
      return;
    case '/list':
      await cmdList(chatId, userId);
      return;
    case '/export':
      await cmdExport(chatId, userId);
      return;
    case '/delete':
      await cmdAdminDelete(chatId, userId, arg);
      return;
    case '/deleteall':
      await cmdAdminDeleteAll(chatId, userId);
      return;
    default:
      if (command.startsWith('/')) {
        await sendHtml(chatId, 'Неизвестная команда. Список команд: /help');
      }
  }
}

// ---------------------------------------------------------------------------
// Callback-кнопки
// ---------------------------------------------------------------------------

export async function handleCallback(callbackQuery) {
  const callbackId = String(callbackQuery.id ?? '');
  const from = callbackQuery.from ?? {};
  const userId = String(from.id ?? '');
  const data = String(callbackQuery.data ?? '');
  const message = callbackQuery.message ?? {};
  const chat = message.chat ?? {};
  const chatId = String(chat.id ?? userId);
  const messageId = Number(message.message_id ?? 0);

  await answerCallback(callbackId);
  if (!userId || !chatId) return;

  await rememberUser(chatId, from);

  const parts = data.split(':');
  const scope = parts[0] ?? '';

  if (scope === 'bc') {
    if (!isAdmin(userId)) {
      await sendHtml(chatId, '⛔ Команда доступна только администратору.');
      return;
    }
    switch (parts[1] ?? '') {
      case 'start':
        await startBroadcast(chatId, userId);
        return;
      case 'send':
        await sendBroadcast(chatId, userId, messageId);
        return;
      case 'cancel':
        await clearBroadcast(userId);
        await editText(chatId, messageId, 'Рассылка отменена.');
        return;
    }
    return;
  }

  if (scope === 'adm') {
    if (!isAdmin(userId)) {
      await sendHtml(chatId, '⛔ Команда доступна только администратору.');
      return;
    }
    switch (parts[1] ?? '') {
      case 'del':
        await adminDeleteOne(chatId, userId, parts[2] ?? '', parts[3] ?? '', messageId);
        return;
      case 'delall':
        if ((parts[2] ?? '') === 'confirm') {
          await adminDeleteAllConfirm(chatId, userId, messageId);
        }
        return;
      case 'cancel':
        await finishAdminAction(chatId, messageId, 'Удаление отменено.');
        return;
    }
    return;
  }

  if (scope !== 'reg') return;

  switch (parts[1] ?? '') {
    case 'start':
    case 'edit':
      if (messageId && !message.photo) {
        await editText(chatId, messageId, '✏️ Заполняем заново.');
      }
      await startRegistration(chatId, userId);
      return;
    case 'my':
      await cmdMy(chatId, userId);
      return;
    case 'size':
      await chooseSize(chatId, userId, parts[2] ?? '', messageId);
      return;
    case 'save':
      await finishRegistration(chatId, userId, from, messageId);
      return;
    case 'delete':
      await confirmDelete(chatId, userId);
      return;
    case 'delete_confirm':
      await deleteApplication(chatId, userId, messageId);
      return;
    case 'delete_cancel':
      await editText(chatId, messageId, 'Удаление отменено.');
      return;
    case 'cancel':
      await clearSession(userId);
      await editText(chatId, messageId, '❌ Заполнение отменено.');
      return;
  }
}

// ---------------------------------------------------------------------------
// Анкета
// ---------------------------------------------------------------------------

async function askFio(chatId, data) {
  const current = String(data.fio ?? '').trim();
  const hint = current
    ? `\n\nСейчас: <b>${esc(current)}</b>\nЧтобы оставить без изменений, отправьте /skip.`
    : '';
  await sendHtml(
    chatId,
    `👤 <b>Шаг 1 из 3</b>\n\nВведите ваше ФИО полностью:\n<i>Например: Иванов Иван Иванович</i>${hint}`,
  );
}

async function askCompany(chatId, data) {
  const current = String(data.company ?? '').trim();
  const hint = current
    ? `\n\nСейчас: <b>${esc(current)}</b>\nЧтобы оставить без изменений, отправьте /skip.`
    : '';
  await sendHtml(chatId, `🏢 <b>Шаг 2 из 3</b>\n\nУкажите компанию:${hint}`);
}

async function askSize(chatId) {
  await sendHtml(chatId, '👕 <b>Шаг 3 из 3</b>\n\nВыберите размер одежды:', sizeKeyboard());
}

async function startRegistration(chatId, userId) {
  await clearBroadcast(userId);

  const existing = await findRecord(userId);
  const data = existing
    ? { fio: existing.fio, company: existing.company, size: existing.size }
    : {};

  await setSession(userId, { step: 'fio', data, updatedAt: nowSec() });
  await askFio(chatId, data);
}

async function handleSessionText(chatId, userId, session, text) {
  const step = session.step;
  const data = { ...(session.data ?? {}) };
  const normalized = normalizeSpaces(text);

  if (step === 'confirm') {
    await sendHtml(chatId, 'Подтвердите анкету или измените данные кнопками ниже 👇');
    return true;
  }
  if (step !== 'fio' && step !== 'company') {
    await sendHtml(chatId, 'Выберите размер кнопкой ниже 👇');
    return true;
  }

  if (normalized.startsWith('/') && normalized.toLowerCase() !== '/skip') return false;

  const field = step === 'fio' ? 'fio' : 'company';
  const current = String(data[field] ?? '').trim();

  if (normalized.toLowerCase() === '/skip') {
    if (!current) {
      await sendHtml(chatId, 'Здесь нечего пропускать — введите значение текстом.');
      return true;
    }
  } else if (step === 'fio') {
    if (!validFio(normalized)) {
      await sendHtml(
        chatId,
        'Похоже, это не ФИО. Введите фамилию, имя и отчество текстом:\n<i>Например: Иванов Иван Иванович</i>',
      );
      return true;
    }
    data.fio = normalized;
  } else {
    if (!validCompany(normalized)) {
      await sendHtml(chatId, 'Укажите компанию текстом (от 2 до 120 символов).');
      return true;
    }
    data.company = normalized;
  }

  if (step === 'fio') {
    await setSession(userId, { step: 'company', data, updatedAt: nowSec() });
    await askCompany(chatId, data);
    return true;
  }

  await setSession(userId, { step: 'size', data, updatedAt: nowSec() });
  await askSize(chatId);
  return true;
}

async function chooseSize(chatId, userId, size, messageId) {
  const normalized = String(size).toUpperCase().trim();
  if (!SIZES.includes(normalized)) {
    await sendHtml(chatId, 'Неизвестный размер. Выберите кнопкой из списка.');
    return;
  }

  const session = await getSession(userId);
  const step = session?.step ?? '';
  if (!session || (step !== 'size' && step !== 'confirm')) {
    const record = await findRecord(userId);
    await sendHtml(
      chatId,
      record
        ? 'Вы уже заполнили анкету. Чтобы изменить данные, нажмите «✏️ Изменить данные».'
        : 'Сессия устарела. Начните заново: /start',
    );
    return;
  }

  const data = { ...(session.data ?? {}), size: normalized };
  await setSession(userId, { step: 'confirm', data, updatedAt: nowSec() });

  const edited = await editText(chatId, messageId, confirmHtml(data), 'HTML', confirmKeyboard());
  if (!edited) await sendHtml(chatId, confirmHtml(data), confirmKeyboard());
}

async function finishRegistration(chatId, userId, from, messageId) {
  const session = await getSession(userId);

  if (!session) {
    const record = await findRecord(userId);
    if (record) {
      await sendHtml(chatId, `✅ Вы уже заполнили анкету.\n\n${recordSummaryHtml(record)}`, editKeyboard());
    } else {
      await sendHtml(chatId, 'Анкета устарела. Начните заново: /start');
    }
    return;
  }

  if (session.step !== 'confirm') {
    await sendHtml(chatId, 'Анкета устарела. Начните заново: /start');
    return;
  }

  const data = session.data ?? {};
  const fio = String(data.fio ?? '').trim();
  const company = String(data.company ?? '').trim();
  const size = String(data.size ?? '').toUpperCase().trim();

  if (!validFio(fio) || !validCompany(company) || !SIZES.includes(size)) {
    await clearSession(userId);
    await sendHtml(chatId, 'Не хватает данных анкеты. Начните заново: /start');
    return;
  }

  const record = await saveRecord(userId, from, fio, company, size);
  await clearSession(userId);
  await editText(chatId, messageId, '✅ Анкета отправлена.');
  await sendHtml(chatId, successHtml(record), editKeyboard());
}

// ---------------------------------------------------------------------------
// Команды
// ---------------------------------------------------------------------------

async function cmdStart(chatId, userId) {
  await clearSession(userId);
  await clearBroadcast(userId);

  const record = await findRecord(userId);
  const keyboard = welcomeKeyboard(Boolean(record));
  await sendWelcome(chatId, Boolean(record), keyboard);

  if (record) {
    await sendHtml(chatId, `✅ <b>Вы уже заполнили анкету.</b>\n\n${recordSummaryHtml(record)}`);
  }
}

async function cmdHelp(chatId, userId) {
  let text =
    '🐐 <b>Анкета на корпоратив</b>\n\n' +
    '/start — приглашение и анкета\n' +
    '/my — моя анкета\n' +
    '/cancel — отменить заполнение\n' +
    '/id — мой Telegram ID';

  let keyboard = null;
  if (isAdmin(userId)) {
    text +=
      '\n\n<b>Админ:</b>\n/stats — статистика\n/list — список\n/export — CSV для Excel\n/broadcast — рассылка\n/delete &lt;номер|ID&gt; — удалить одну анкету\n/deleteall — удалить все анкеты\n/setcommands — обновить меню команд';
    keyboard = inline([[btn('✉️ Рассылка', 'bc:start')]]);
  }

  await sendHtml(chatId, text, keyboard);
}

async function cmdSetCommands(chatId, userId) {
  if (!isAdmin(userId)) {
    await sendHtml(chatId, '⛔ Команда доступна только администратору.');
    return;
  }

  const ok = await setBotCommands();
  await sendHtml(
    chatId,
    ok
      ? '✅ Меню команд обновлено: /start, /my, /help, /cancel.'
      : 'Не удалось обновить меню команд. Проверьте логи.',
  );
}

async function cmdMy(chatId, userId) {
  const record = await findRecord(userId);
  if (!record) {
    await sendHtml(
      chatId,
      'Вы ещё не заполнили анкету.\n\nНажмите кнопку ниже или отправьте /start.',
      registerKeyboard(),
    );
    return;
  }
  await sendHtml(chatId, `📋 <b>Ваша анкета</b>\n\n${recordSummaryHtml(record)}`, editKeyboard());
}

async function cmdStats(chatId, userId) {
  if (!isAdmin(userId)) {
    await sendHtml(chatId, '⛔ Команда доступна только администратору.');
    return;
  }

  const stats = await statsData();
  const lines = [
    '📊 <b>Статистика анкет</b>',
    '',
    `Активных анкет: <b>${stats.total}</b>`,
    `Удалённых анкет: <b>${stats.deleted}</b>`,
    '',
    ...SIZES.map((size) => `${size}: ${stats.sizes[size] ?? 0}`),
  ];

  await sendHtml(chatId, lines.join('\n'));
}

async function cmdList(chatId, userId) {
  if (!isAdmin(userId)) {
    await sendHtml(chatId, '⛔ Команда доступна только администратору.');
    return;
  }

  const active = await loadRegistrations();
  const deleted = (await loadArchive()).length;

  if (active.length === 0) {
    await sendHtml(
      chatId,
      deleted > 0
        ? `Активных анкет нет. Удалённых анкет: ${deleted} — они есть в выгрузке /export.`
        : 'Пока нет заполненных анкет.',
    );
    return;
  }

  const lines = [`👥 <b>Заполненные анкеты</b> (${active.length}):`, ''];
  active.forEach((record, index) => {
    lines.push(
      `${index + 1}. ${esc(record.fio)} — ${esc(record.company)} — <b>${esc(record.size)}</b>`,
    );
  });
  if (deleted > 0) {
    lines.push('', `Удалённых анкет: ${deleted} (в выгрузке /export)`);
  }
  lines.push('', 'Удалить одну: /delete &lt;номер&gt;, все: /deleteall');

  await sendHtml(chatId, lines.join('\n'));
}

async function cmdExport(chatId, userId) {
  if (!isAdmin(userId)) {
    await sendHtml(chatId, '⛔ Команда доступна только администратору.');
    return;
  }

  const active = await loadRegistrations();
  const archive = await loadArchive();
  const csv = buildCsv(active, archive);
  const caption = `Анкеты NY2027: активных ${active.length}, удалённых ${archive.length}`;

  if (!(await sendCsv(chatId, csv, caption))) {
    await sendHtml(chatId, 'Не удалось отправить CSV.');
  }
}

async function confirmDelete(chatId, userId) {
  const record = await findRecord(userId);
  if (!record) {
    await sendHtml(chatId, 'Активной анкеты нет — удалять нечего.', registerKeyboard());
    return;
  }

  await sendHtml(
    chatId,
    `🗑 <b>Удалить анкету?</b>\n\n${recordSummaryHtml(
      record,
    )}\n\nПосле удаления вы сможете заполнить анкету заново.`,
    deleteConfirmKeyboard(),
  );
}

async function deleteApplication(chatId, userId, messageId) {
  const deleted = await deleteRecord(userId);

  if (!deleted) {
    const edited = await editText(
      chatId,
      messageId,
      'Активной анкеты уже нет.',
      undefined,
      registerKeyboard(),
    );
    if (!edited) await sendHtml(chatId, 'Активной анкеты уже нет.', registerKeyboard());
    return;
  }

  await clearSession(userId);
  await editText(chatId, messageId, '🗑 <b>Анкета удалена.</b>', 'HTML');
  await sendHtml(chatId, 'Вы можете заполнить анкету заново:', registerKeyboard());
}

// ---------------------------------------------------------------------------
// Админское удаление
// ---------------------------------------------------------------------------

async function cmdAdminDelete(chatId, userId, arg) {
  if (!isAdmin(userId)) {
    await sendHtml(chatId, '⛔ Команда доступна только администратору.');
    return;
  }

  const value = String(arg ?? '').trim();
  if (!value) {
    await sendHtml(
      chatId,
      'Использование: <code>/delete &lt;номер из /list&gt;</code> или <code>/delete &lt;Telegram ID&gt;</code>.',
    );
    return;
  }

  const records = await loadRegistrations();
  if (records.length === 0) {
    await sendHtml(chatId, 'Нет активных анкет.');
    return;
  }

  let record = null;
  if (/^\d+$/.test(value)) {
    const number = Number(value);
    if (number >= 1 && number <= records.length) record = records[number - 1];
  }
  if (!record && /^\d+$/.test(value)) record = await findRecord(value);

  if (!record) {
    await sendHtml(
      chatId,
      'Анкета не найдена. /list — номера активных анкет; также можно указать Telegram ID.',
    );
    return;
  }

  const targetId = String(record.chatId);
  const token = recordVersion(record);

  await sendHtml(
    chatId,
    `🗑 <b>Удалить анкету?</b>\n\n${recordSummaryHtml(
      record,
    )}\nTelegram ID: <code>${esc(targetId)}</code>\n\nАнкета исчезнет у пользователя, в выгрузке останется со статусом «Удалена».`,
    adminDeleteKeyboard(targetId, token),
  );
}

async function cmdAdminDeleteAll(chatId, userId) {
  if (!isAdmin(userId)) {
    await sendHtml(chatId, '⛔ Команда доступна только администратору.');
    return;
  }

  const count = (await loadRegistrations()).length;
  if (count === 0) {
    await sendHtml(chatId, 'Нет активных анкет.');
    return;
  }

  await sendHtml(
    chatId,
    `🗑 <b>Удалить все анкеты?</b>\n\nАктивных анкет: <b>${count}</b>.\n\nВсе они переместятся в архив со статусом «Удалена» и останутся в /export. Пользователи смогут заполнить анкету заново.`,
    adminDeleteAllKeyboard(count),
  );
}

async function adminDeleteOne(chatId, adminId, targetId, token, messageId) {
  if (!isAdmin(adminId)) {
    await sendHtml(chatId, '⛔ Команда доступна только администратору.');
    return;
  }

  const record = await findRecord(targetId);
  if (!record) {
    await finishAdminAction(chatId, messageId, 'Анкета уже удалена.');
    return;
  }

  if (recordVersion(record) !== String(token)) {
    await finishAdminAction(chatId, messageId, 'Анкета изменилась. Проверьте /list и повторите.');
    return;
  }

  const deleted = await deleteRecord(targetId);
  await clearSession(targetId);

  if (!deleted) {
    await finishAdminAction(chatId, messageId, 'Анкета уже удалена.');
    return;
  }

  console.log(`Админ ${adminId} удалил анкету: ID=${targetId}`);
  const fio = String(deleted.fio ?? '').trim();
  await finishAdminAction(
    chatId,
    messageId,
    fio ? `🗑 <b>Анкета удалена:</b> ${esc(fio)}` : '🗑 <b>Анкета удалена.</b>',
  );
}

async function adminDeleteAllConfirm(chatId, adminId, messageId) {
  if (!isAdmin(adminId)) {
    await sendHtml(chatId, '⛔ Команда доступна только администратору.');
    return;
  }

  const count = await deleteAllRecords();
  await clearAllSessions();

  if (count === 0) {
    await finishAdminAction(chatId, messageId, 'Активных анкет уже нет.');
    return;
  }

  console.log(`Админ ${adminId} удалил все анкеты: ${count}`);
  await finishAdminAction(
    chatId,
    messageId,
    `🗑 <b>Удалено анкет: ${count}.</b>\n\nВсе перемещены в архив со статусом «Удалена».`,
  );
}

async function finishAdminAction(chatId, messageId, html) {
  const edited = await editText(chatId, messageId, html, 'HTML');
  if (!edited) await sendHtml(chatId, html);
}

// ---------------------------------------------------------------------------
// Рассылка
// ---------------------------------------------------------------------------

async function startBroadcast(chatId, userId) {
  if (!isAdmin(userId)) {
    await sendHtml(chatId, '⛔ Команда доступна только администратору.');
    return;
  }

  await clearSession(userId);
  await setBroadcast(userId, { step: 'text', body: '', updatedAt: nowSec() });

  await sendHtml(
    chatId,
    '✉️ <b>Рассылка</b>\n\n' +
      'Отправьте текст сообщения — я покажу превью и спрошу подтверждение.\n\n' +
      `Максимум ${BROADCAST_TEXT_MAX} символов. Эмодзи и ссылки поддерживаются. Отмена: /cancel.`,
  );
}

async function saveBroadcastText(chatId, userId, text) {
  if (!isAdmin(userId)) return;

  const body = String(text).trim();
  if (!body) {
    await sendHtml(chatId, 'Текст пустой. Отправьте сообщение текстом.');
    return;
  }
  if (body.length > BROADCAST_TEXT_MAX) {
    await sendHtml(
      chatId,
      `Слишком длинное сообщение: ${body.length} символов. Максимум ${BROADCAST_TEXT_MAX} — сократите текст.`,
    );
    return;
  }

  await setBroadcast(userId, { step: 'confirm', body, updatedAt: nowSec() });

  const total = (await broadcastRecipients(userId)).length;
  const warning =
    total > MAX_BROADCAST
      ? `\n\n⚠️ Лимит Serverless-версии: ${MAX_BROADCAST} получателей. Рассылка не запустится — поднимите MAX_BROADCAST.`
      : '';

  await sendHtml(
    chatId,
    `✉️ <b>Проверьте сообщение</b>\n\nПолучателей: <b>${total}</b>. Ниже — то, что увидят пользователи:${warning}`,
  );
  await sendHtml(chatId, formatBroadcastHtml(body));
  await sendHtml(chatId, 'Отправить всем?', broadcastConfirmKeyboard(total));
}

async function sendBroadcast(chatId, userId, messageId) {
  if (!isAdmin(userId)) {
    await sendHtml(chatId, '⛔ Команда доступна только администратору.');
    return;
  }

  const state = await getBroadcast(userId);
  const text = state ? String(state.body ?? '').trim() : '';

  if (!text) {
    await clearBroadcast(userId);
    const message = 'Черновик рассылки не найден. Начните заново: /broadcast';
    const edited = await editText(chatId, messageId, message);
    if (!edited) await sendHtml(chatId, message);
    return;
  }

  const recipients = await broadcastRecipients(userId);
  const total = recipients.length;

  if (total === 0) {
    await clearBroadcast(userId);
    const message = 'Нет получателей — кроме вас, никто ещё не запускал бота.';
    const edited = await editText(chatId, messageId, message);
    if (!edited) await sendHtml(chatId, message);
    return;
  }

  if (total > MAX_BROADCAST) {
    await clearBroadcast(userId);
    const message = `Слишком много получателей: ${total} (лимит Serverless-версии ${MAX_BROADCAST}). Поднимите MAX_BROADCAST.`;
    const edited = await editText(chatId, messageId, message);
    if (!edited) await sendHtml(chatId, message);
    return;
  }

  await clearBroadcast(userId);

  const started = `✉️ Рассылка началась. Получателей: <b>${total}</b>.`;
  const startedEdited = await editText(chatId, messageId, started, 'HTML');
  if (!startedEdited) await sendHtml(chatId, started);

  const formatted = formatBroadcastHtml(text);
  let sent = 0;
  let blocked = 0;
  let failed = 0;
  const delivery = {};

  for (const recipient of recipients) {
    const status = await broadcastSend(recipient.chatId, formatted);
    if (status === 'ok') {
      sent++;
      delivery[recipient.chatId] = false;
    } else if (status === 'blocked') {
      blocked++;
      delivery[recipient.chatId] = true;
    } else {
      failed++;
    }
  }

  await markUsersDelivery(delivery);

  let report = `✅ <b>Рассылка завершена.</b>\n\nОтправлено: <b>${sent}</b>\nВсего получателей: ${total}`;
  if (blocked > 0) report += `\nЗаблокировали бота: ${blocked}`;
  if (failed > 0) report += `\nОшибок: ${failed}`;

  await sendHtml(chatId, report);
  console.log(
    `broadcast: admin=${userId}, total=${total}, sent=${sent}, blocked=${blocked}, failed=${failed}`,
  );
}
