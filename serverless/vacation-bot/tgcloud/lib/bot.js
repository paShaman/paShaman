import {
  ADMIN_ID,
  ABSENCE_TYPES,
  QUOTA_TYPES,
  COMMANDS,
} from './config.js';
import {
  answerCallback,
  sendHtml,
  sendRich,
} from './telegram.js';
import {
  addUser,
  activateUser,
  addRecords,
  balanceFor,
  clearSession,
  deactivateUser,
  deleteRecord,
  findOverlaps,
  findRecord,
  findUser,
  finishRecord,
  getSession,
  isAdmin,
  isAllowed,
  loadRecords,
  loadUsers,
  numberOfRecord,
  numberOfRecordById,
  planYears,
  plannedRecords,
  recordByNumber,
  recordsForUserYear,
  setSession,
  touchUser,
  updateRecordComment,
  updateRecordDates,
} from './store.js';
import {
  btn,
  daysInRange,
  displayName,
  effectiveTo,
  esc,
  effectiveDays,
  formatInterval,
  inline,
  isActiveOn,
  menuKeyboard,
  normalizeSpaces,
  parseRanges,
  parseYear,
  shortDate,
  splitMessage,
  typeLabel,
} from './utils.js';

export async function handleMessage(message, ctx) {
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

  await touchUser(userId, from);

  if (!text) {
    const session = await getSession(userId);
    if (session) {
      await sendHtml(chatId, 'Пожалуйста, отправьте ответ текстом.');
    }
    return;
  }

  const parts = text.split(/\s+/);
  const rawCommand = parts[0] ?? '';
  const arg = text.slice(rawCommand.length).trim();
  const command = rawCommand.toLowerCase().split('@')[0];

  if (command === '/id') {
    await sendHtml(chatId, `🆔 Ваш Telegram ID: <code>${userId}</code>`);
    return;
  }

  if (!(await isAllowed(userId))) {
    await sendHtml(
      chatId,
      `🔒 <b>Доступа нет</b>\n\nВаш Telegram ID: <code>${userId}</code>\n`
      + 'Передайте его администратору, чтобы он добавил вас в список.',
    );
    return;
  }

  if (COMMANDS.includes(command)) {
    switch (command) {
      case '/start':
      case '/help':
        await cmdHelp(chatId, userId);
        return;
      case '/add':
        await cmdAdd(chatId, userId, arg);
        return;
      case '/my':
        await cmdMy(chatId, userId, arg);
        return;
      case '/left':
        await cmdLeft(chatId, userId, arg);
        return;
      case '/cancel':
        await cmdCancel(chatId, userId, arg);
        return;
      case '/edit':
        await cmdEdit(chatId, userId, arg);
        return;
      case '/finish':
        await cmdFinish(chatId, userId, arg);
        return;
      case '/today':
        await cmdToday(chatId, userId);
        return;
      case '/week':
        await cmdWeek(chatId, userId);
        return;
      case '/month':
        await cmdMonth(chatId, userId, arg);
        return;
      case '/soon':
        await cmdSoon(chatId, userId, arg);
        return;
      case '/user':
        await cmdUser(chatId, userId, arg);
        return;
      case '/users':
        await cmdUsers(chatId, userId);
        return;
      case '/adduser':
        await cmdAddUser(chatId, userId, arg);
        return;
      case '/deluser':
        await cmdDelUser(chatId, userId, arg);
        return;
      default:
        if (command.startsWith('/')) {
          await sendHtml(chatId, 'Неизвестная команда. Список команд: /help');
        }
    }
    return;
  }

  const session = await getSession(userId);
  if (session && await handleSessionText(chatId, userId, session, text)) return;
}

// ---------------------------------------------------------------------------
// Callback-кнопки
// ---------------------------------------------------------------------------

export async function handleCallback(callbackQuery, ctx) {
  const callbackId = String(callbackQuery.id ?? '');
  const from = callbackQuery.from ?? {};
  const userId = String(from.id ?? '');
  const data = String(callbackQuery.data ?? '');
  const message = callbackQuery.message ?? {};
  const chat = message.chat ?? {};
  const chatId = String(chat.id ?? userId);

  await answerCallback(callbackId);
  if (!userId || !chatId) return;

  await touchUser(chatId, from);

  if (!(await isAllowed(userId))) {
    await sendHtml(chatId, '🔒 Доступа нет.');
    return;
  }

  const parts = data.split(':');
  const scope = parts[0] ?? '';

  switch (scope) {
    case 'menu':
      await menuAction(chatId, userId, String(parts[1] ?? ''));
      return;
    case 'add':
      await addAction(chatId, userId, parts);
      return;
    case 'rec':
      await recAction(chatId, userId, parts, Number(message.message_id ?? 0));
      return;
    case 'adm':
      await adminAction(chatId, userId, parts);
      return;
    case 'usr':
      await cmdUser(chatId, userId, String(parts[1] ?? ''));
      return;
  }
}

// ---------------------------------------------------------------------------
// Сессии диалога
// ---------------------------------------------------------------------------

async function handleSessionText(chatId, userId, session, text) {
  const step = session.step ?? '';
  const data = { ...(session.data ?? {}) };
  const normalized = normalizeSpaces(text);

  if (step === 'dates' || step === 'edit_dates') {
    let ranges;
    try {
      ranges = parseRanges(normalized);
    } catch (error) {
      await sendHtml(chatId, `⚠️ ${esc(error.message)}`);
      return true;
    }

    if (step === 'edit_dates') {
      const id = Number(data.target ?? 0);
      await clearSession(userId);
      if (ranges.length !== 1) {
        await sendHtml(
          chatId,
          '⚠️ Для переноса укажите один диапазон, например <code>01.07-14.07</code>.',
        );
        return true;
      }
      const result = await updateRecordDates(id, userId, ranges[0]);
      if (!result.ok) {
        await sendHtml(chatId, `⚠️ ${errText(result.err)}`);
        return true;
      }
      const num = await numberOfRecordById(id, userId);
      await sendHtml(
        chatId,
        `✏️ ${num !== null ? `Запись №${num}` : 'Запись'} перенесена: `
        + `${esc(formatInterval(ranges[0].from, ranges[0].to))}`
        + ` (${daysInRange(ranges[0].from, ranges[0].to)} к.д.)`,
      );
      return true;
    }

    data.ranges = ranges;
    data.step = 'confirm';
    await setSession(userId, data);
    await sendAddConfirm(chatId, userId, data);
    return true;
  }

  if (step === 'comment') {
    data.comment = normalized === '-' ? '' : normalized;
    data.step = 'confirm';
    await setSession(userId, data);
    await sendAddConfirm(chatId, userId, data);
    return true;
  }

  if (step === 'finish_date') {
    const id = Number(data.target ?? 0);
    await clearSession(userId);
    let ranges;
    try {
      ranges = parseRanges(normalized);
    } catch (error) {
      await sendHtml(chatId, `⚠️ ${esc(error.message)}`);
      return true;
    }
    const date = ranges[0].from;
    const result = await finishRecord(id, userId, date);
    if (!result.ok) {
      await sendHtml(chatId, `⚠️ ${errText(result.err)}`);
      return true;
    }
    const number = await numberOfRecordById(id, userId);
    await sendHtml(
      chatId,
      `🏁 ${number !== null ? `Запись №${number}` : 'Запись'} завершена `
      + `${esc(shortDate(date))}.`,
    );
    return true;
  }

  if (step === 'admin_adduser') {
    if (!(await isAdmin(userId))) {
      await clearSession(userId);
      return true;
    }
    if (!/^\d+\s+.+$/u.test(normalized.trim())) {
      await sendHtml(chatId, 'Формат: <code>123456 Иван Петров</code>. Или нажмите /users.');
      return true;
    }
    await clearSession(userId);
    const [tgId, ...nameParts] = normalized.trim().split(/\s+/);
    await cmdAddUser(chatId, userId, `${tgId} ${nameParts.join(' ')}`);
    await cmdAdminUsers(chatId, userId);
    return true;
  }

  return false;
}

// ---------------------------------------------------------------------------
// Команды
// ---------------------------------------------------------------------------

async function cmdHelp(chatId, userId) {
  const types = [];
  for (const [key, emoji] of Object.entries(ABSENCE_TYPES)) {
    types.push(`${emoji} ${key}`);
  }

  let text =
    '<b>🏖 Бот учёта отсутствий</b>\n\n'
    + `Типы: ${types.join(', ')}.\n`
    + `Остаток отпуска считают типы: ${QUOTA_TYPES.join(', ')}.\n\n`
    + '<b>Свои записи</b>\n'
    + '• /add — добавить отсутствие (кнопками)\n'
    + '• /my [год] — мои отсутствия (без года — текущий и будущие годы)\n'
    + '• /left [год] — остаток отпуска (без года — текущий и будущие годы)\n'
    + '• /cancel <номер> — отменить (номер из /my)\n'
    + '• /edit <номер> <даты> — перенести (комментарий — кнопкой 💬 в /my)\n'
    + '• /finish <номер> [дата|сегодня] — досрочно завершить\n\n'
    + '<b>Общая картина</b>\n'
    + '• /today — кто отсутствует сегодня\n'
    + '• /week — кто отсутствует на этой неделе\n'
    + '• /month [ММ.ГГГГ] — календарь на месяц\n'
    + '• /soon [N] — кто уходит в ближайшие N дней\n'
    + '• /user <@username|имя|id> [год] — отсутствия сотрудника\n'
    + '• /users — список сотрудников (кнопкой выбрать и посмотреть текущий год)\n\n'
    + 'Даты: <code>01.07.2026-14.07.2026</code>, <code>01.07-14.07</code>, '
    + '<code>01.07</code> или <code>01.07 +14</code> (старт и число дней).';

  if (await isAdmin(userId)) {
    text += '\n\n<b>Админ</b>\n'
      + '• /adduser <tg_id> <Имя> — добавить сотрудника\n'
      + '• /deluser <tg_id> — отключить доступ\n'
      + '• кнопка «⚙️ Админ» в меню — список с включением/отключением';
  }

  await sendHtml(chatId, text, menuKeyboard(userId, await isAdmin(userId)));
}

async function cmdAdd(chatId, userId, arg) {
  if (arg !== '') {
    const ap = arg.split(/\s+/);
    const typeKey = String(ap[0] ?? '').toLowerCase().trim();
    if (typeKey !== '' && typeKey in ABSENCE_TYPES && ap[1] !== undefined) {
      let ranges;
      try {
        ranges = parseRanges(ap[1]);
      } catch (error) {
        await sendHtml(chatId, `⚠️ ${esc(error.message)}`);
        return;
      }
      await setSession(userId, { step: 'confirm', type: typeKey, ranges, comment: '' });
      await sendAddConfirm(chatId, userId, { step: 'confirm', type: typeKey, ranges, comment: '' });
      return;
    }
  }

  await startAddDialog(chatId, userId);
}

async function startAddDialog(chatId, userId) {
  await setSession(userId, { step: 'type', type: '', ranges: [], comment: '' });

  const rows = [];
  let row = [];
  for (const [key, emoji] of Object.entries(ABSENCE_TYPES)) {
    row.push(btn(`${emoji} ${key.charAt(0).toUpperCase()}${key.slice(1)}`, `add:type:${key}`));
    if (row.length === 2) {
      rows.push(row);
      row = [];
    }
  }
  if (row.length > 0) rows.push(row);
  rows.push([btn('❌ Отмена', 'add:cancel')]);

  await sendHtml(chatId, '➕ <b>Новое отсутствие</b>. Выберите тип:', inline(rows));
}

async function sendAddConfirm(chatId, userId, session) {
  const ranges = Array.isArray(session.ranges) ? session.ranges : [];
  if (ranges.length === 0) {
    await sendHtml(chatId, '⚠️ Нет дат. Начните заново: /add');
    return;
  }

  const type = String(session.type ?? '');
  const comment = String(session.comment ?? '').trim();

  const lines = ['<b>Проверьте запись</b>', ''];
  lines.push(`Тип: ${esc(typeLabel(type))}`);

  let total = 0;
  for (const r of ranges) {
    const days = daysInRange(r.from, r.to);
    total += days;
    lines.push(`• ${esc(formatInterval(r.from, r.to))} — ${days} к.д.`);
  }
  if (ranges.length > 1) lines.push(`Итого: <b>${total}</b> к.д.`);
  if (comment !== '') lines.push(`Комментарий: ${esc(comment)}`);
  if (QUOTA_TYPES.includes(type)) {
    lines.push(`Спишется с отпуска: <b>${total}</b> дн.`);
  }

  const overlaps = await findOverlaps(userId, ranges);
  if (overlaps.length > 0) {
    lines.push('');
    lines.push('⚠️ <b>Пересечения:</b>');
    for (const o of overlaps) {
      lines.push(`• ${esc(o.name)} (${esc(o.interval)})${o.own ? ' — ваша запись' : ''}`);
    }
  }

  await sendHtml(
    chatId,
    lines.join('\n'),
    inline([
      [btn('✅ Подтвердить', 'add:confirm'), btn('✏️ Даты', 'add:edit')],
      [btn('💬 Комментарий', 'add:comment'), btn('❌ Отмена', 'add:cancel')],
    ]),
  );
}

async function confirmAdd(chatId, userId) {
  const session = await getSession(userId);
  if (!session || session.step !== 'confirm') {
    await sendHtml(chatId, '⚠️ Заявка устарела. Начните заново: /add');
    return;
  }

  const ranges = Array.isArray(session.ranges) ? session.ranges : [];
  const type = String(session.type ?? '');
  const comment = String(session.comment ?? '');

  if (ranges.length === 0 || !(type in ABSENCE_TYPES)) {
    await clearSession(userId);
    await sendHtml(chatId, '⚠️ Некорректная заявка. Начните заново: /add');
    return;
  }

  const created = await addRecords(userId, type, ranges, comment);
  await clearSession(userId);

  let totalDays = 0;
  for (const r of created) totalDays += r.days;

  const lines = ['✅ <b>Записано</b>'];
  let num = 0;
  for (const r of created) {
    num++;
    lines.push(`${num}. ${esc(typeLabel(r.type))} · ${esc(formatInterval(r.date_from, effectiveTo(r)))} (${r.days} к.д.)`);
  }

  if (QUOTA_TYPES.includes(type)) {
    const year = Number(String(ranges[0].from).slice(0, 4));
    const balance = await balanceFor(userId, year);
    lines.push('');
    lines.push(`Остаток отпуска в ${year}: <b>${balance.remaining}</b> из ${balance.norm}`);
  }

  await sendHtml(chatId, lines.join('\n'), menuKeyboard(userId, await isAdmin(userId)));
}

// ---------------------------------------------------------------------------
// Личные записи
// ---------------------------------------------------------------------------

async function cmdMy(chatId, userId, arg) {
  const explicitYear = parseYear(arg);
  const recordsList = explicitYear !== null
    ? await recordsForUserYear(userId, explicitYear)
    : await plannedRecords(userId, new Date().getFullYear());

  const lines = explicitYear !== null
    ? [`<b>📋 Мои отсутствия за ${explicitYear}</b>`, '']
    : ['<b>📋 Мои отсутствия · текущий и будущие годы</b>', ''];

  if (recordsList.length === 0) {
    lines.push(explicitYear !== null ? 'За этот год записей нет.' : 'Записей нет.');
  } else {
    let num = 0;
    for (const r of recordsList) {
      num++;
      let line = `${num}. ${esc(typeLabel(r.type))} · ${esc(formatInterval(r.date_from, effectiveTo(r)))} (${effectiveDays(r)} к.д.)`;
      if (r.finished_at) {
        line += ` <i>· завершён ${esc(shortDate(r.finished_at))}</i>`;
      }
      lines.push(line);
      const comment = String(r.comment ?? '').trim();
      if (comment !== '') lines.push(`    💬 ${esc(comment)}`);
    }
  }

  lines.push('');
  if (explicitYear !== null) {
    const balance = await balanceFor(userId, explicitYear);
    lines.push(`🏖 Остаток отпуска: <b>${balance.remaining}</b> из ${balance.norm} (использовано ${balance.used})`);
  } else {
    lines.push('🏖 <b>Остаток отпуска:</b>');
    for (const y of await planYears(userId)) {
      const balance = await balanceFor(userId, y);
      lines.push(`• ${y}: <b>${balance.remaining}</b> из ${balance.norm} (использовано ${balance.used})`);
    }
  }

  const rows = [];
  const slice = recordsList.slice(0, 8);
  for (let i = 0; i < slice.length; i++) {
    const r = slice[i];
    const num = i + 1;
    rows.push([
      btn(`✏️ ${num}`, `rec:edit:${r.id}`),
      btn(`💬 ${num}`, `rec:comment:${r.id}`),
      btn(`🏁 ${num}`, `rec:finish:${r.id}`),
      btn(`🗑 ${num}`, `rec:cancel:${r.id}`),
    ]);
  }
  const mk = menuKeyboard(userId, await isAdmin(userId));
  for (const mr of mk.inline_keyboard) rows.push(mr);

  await sendHtml(chatId, lines.join('\n'), inline(rows));
}

async function cmdLeft(chatId, userId, arg) {
  const explicitYear = parseYear(arg);

  if (explicitYear !== null) {
    const balance = await balanceFor(userId, explicitYear);
    const lines = [
      `<b>🏖 Остаток отпуска за ${explicitYear}</b>`,
      '',
      `Норма: <b>${balance.norm}</b> к.д.${balance.default_norm ? ' (по умолчанию)' : ''}`,
      `Использовано: <b>${balance.used}</b> к.д.`,
      `Осталось: <b>${balance.remaining}</b> к.д.`,
    ];
    if (balance.records.length > 0) {
      lines.push('');
      lines.push(`<b>Списания (тип: ${QUOTA_TYPES.join(', ')}):</b>`);
      for (const r of balance.records) {
        lines.push(`• ${esc(formatInterval(r.date_from, effectiveTo(r)))} — ${effectiveDays(r)} дн.`);
      }
    }
    await sendHtml(chatId, lines.join('\n'), menuKeyboard(userId, await isAdmin(userId)));
    return;
  }

  const lines = ['<b>🏖 Остаток отпуска · текущий и будущие годы</b>', ''];
  for (const y of await planYears(userId)) {
    const balance = await balanceFor(userId, y);
    lines.push(`<b>${y}</b>: осталось <b>${balance.remaining}</b> из ${balance.norm} (использовано ${balance.used})`);
  }

  const allRecords = await loadRecords();
  const quota = [];
  for (const r of allRecords.records) {
    if (String(r.user_id) !== String(userId)) continue;
    if (QUOTA_TYPES.includes(String(r.type))) quota.push(r);
  }
  if (quota.length > 0) {
    lines.push('');
    lines.push(`<b>Списания (тип: ${QUOTA_TYPES.join(', ')}):</b>`);
    for (const r of quota) {
      lines.push(`• ${esc(formatInterval(r.date_from, effectiveTo(r)))} — ${effectiveDays(r)} дн.`);
    }
  }

  await sendHtml(chatId, lines.join('\n'), menuKeyboard(userId, await isAdmin(userId)));
}

async function cmdCancel(chatId, userId, arg) {
  const number = Number(String(arg).trim());
  if (!number || number <= 0) {
    await sendHtml(chatId, 'Использование: <code>/cancel &lt;номер из /my&gt;</code>. Список: /my');
    return;
  }

  const record = await recordByNumber(userId, number);
  if (!record) {
    await sendHtml(chatId, `⚠️ В /my нет записи №${number}.`);
    return;
  }

  const result = await deleteRecord(Number(record.id), userId);
  if (!result.ok) {
    await sendHtml(chatId, `⚠️ ${errText(result.err, '⛔ Можно отменять только свои записи.')}`);
    return;
  }

  await sendHtml(
    chatId,
    `🗑 Запись №${number} отменена: ${esc(typeLabel(record.type))} · ${esc(formatInterval(record.date_from, effectiveTo(record)))}`,
  );
}

async function cmdEdit(chatId, userId, arg) {
  const m = /^(\d+)\s+(.+)$/u.exec(String(arg));
  if (!m) {
    await sendHtml(
      chatId,
      'Использование: <code>/edit &lt;номер из /my&gt; &lt;даты&gt;</code>\n'
      + 'Например: <code>/edit 2 01.08-10.08</code>',
    );
    return;
  }

  const number = Number(m[1]);
  let ranges;
  try {
    ranges = parseRanges(m[2]);
  } catch (error) {
    await sendHtml(chatId, `⚠️ ${esc(error.message)}`);
    return;
  }
  if (ranges.length !== 1) {
    await sendHtml(chatId, '⚠️ Для переноса укажите один диапазон.');
    return;
  }

  const record = await recordByNumber(userId, number);
  if (!record) {
    await sendHtml(chatId, `⚠️ В /my нет записи №${number}.`);
    return;
  }

  const result = await updateRecordDates(Number(record.id), userId, ranges[0]);
  if (!result.ok) {
    await sendHtml(chatId, `⚠️ ${errText(result.err)}`);
    return;
  }

  await sendHtml(
    chatId,
    `✏️ Запись №${number} перенесена: ${esc(formatInterval(ranges[0].from, ranges[0].to))} (${daysInRange(ranges[0].from, ranges[0].to)} к.д.)`,
  );
}

async function cmdFinish(chatId, userId, arg) {
  const parts = String(arg).split(/\s+/);
  const number = Number(parts[0] ?? 0);
  if (!number || number <= 0) {
    await sendHtml(chatId, 'Использование: <code>/finish &lt;номер из /my&gt; [дата|сегодня]</code>');
    return;
  }

  const dateInput = String(parts[1] ?? '').trim();
  let date;
  if (dateInput === '' || dateInput.toLowerCase() === 'сегодня') {
    date = new Date().toISOString().slice(0, 10);
  } else {
    let ranges;
    try {
      ranges = parseRanges(dateInput);
    } catch (error) {
      await sendHtml(chatId, `⚠️ ${esc(error.message)}`);
      return;
    }
    date = ranges[0].from;
  }

  const record = await recordByNumber(userId, number);
  if (!record) {
    await sendHtml(chatId, `⚠️ В /my нет записи №${number}.`);
    return;
  }

  const result = await finishRecord(Number(record.id), userId, date);
  if (!result.ok) {
    await sendHtml(chatId, `⚠️ ${errText(result.err)}`);
    return;
  }

  await sendHtml(chatId, `🏁 Запись №${number} завершена ${esc(shortDate(date))}.`);
}

// ---------------------------------------------------------------------------
// Общая картина
// ---------------------------------------------------------------------------

async function cmdToday(chatId, requesterId) {
  const date = new Date().toISOString().slice(0, 10);
  const allRecords = await loadRecords();
  const rows = [];
  for (const r of allRecords.records) {
    if (!isActiveOn(r, date)) continue;
    rows.push({ name: '', from: String(r.date_from), to: effectiveTo(r), user_id: String(r.user_id) });
  }

  const usersList = await loadUsers();
  for (const row of rows) {
    const u = usersList.find((u) => String(u.id) === row.user_id);
    row.name = u ? displayName(u) : `ID ${row.user_id}`;
  }
  rows.sort((a, b) => a.name.localeCompare(b.name, undefined, { sensitivity: 'base' }));

  const lines = [`<b>📌 Отсутствуют сегодня, ${esc(shortDate(date))}</b>`, ''];
  if (rows.length === 0) {
    lines.push('✅ Все на месте.');
  } else {
    for (const row of rows) {
      lines.push(`• ${esc(row.name)} — ${esc(formatInterval(row.from, row.to))}`);
    }
  }

  await sendHtml(chatId, lines.join('\n'), menuKeyboard(requesterId, await isAdmin(requesterId)));
}

async function cmdWeek(chatId, requesterId) {
  const now = new Date();
  const dow = now.getDay() === 0 ? 7 : now.getDay();
  const monday = new Date(now);
  monday.setDate(now.getDate() - (dow - 1));
  monday.setHours(0, 0, 0, 0);

  const dows = ['Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс'];
  const allRecords = await loadRecords();
  const usersList = await loadUsers();
  const lines = [
    `<b>📅 Неделя ${esc(shortDate(monday.toISOString().slice(0, 10)))} – ${esc(shortDate(addDays(monday, 6).toISOString().slice(0, 10)))}</b>`,
    '',
  ];

  for (let i = 0; i < 7; i++) {
    const day = addDays(monday, i);
    const ymd = day.toISOString().slice(0, 10);
    const names = [];
    for (const r of allRecords.records) {
      if (isActiveOn(r, ymd)) {
        const u = usersList.find((u) => String(u.id) === String(r.user_id));
        names.push(u ? displayName(u) : `ID ${r.user_id}`);
      }
    }
    const unique = [...new Set(names)].sort((a, b) => a.localeCompare(b, undefined, { sensitivity: 'base' }));
    lines.push(`${dows[i]} ${day.toISOString().slice(5, 7).replace('-', '.')}: ${unique.length === 0 ? '—' : esc(unique.join(', '))}`);
  }

  await sendHtml(chatId, lines.join('\n'), menuKeyboard(requesterId, await isAdmin(requesterId)));
}

async function cmdMonth(chatId, requesterId, arg) {
  arg = String(arg ?? '').trim();
  let month, year;
  if (arg !== '') {
    const m = /^(\d{1,2})\.(\d{4})$/.exec(arg);
    if (!m) {
      await sendHtml(chatId, '⚠️ Формат: <code>/month 07.2026</code>');
      return;
    }
    month = Number(m[1]);
    year = Number(m[2]);
  } else {
    const now = new Date();
    month = now.getMonth() + 1;
    year = now.getFullYear();
  }

  if (month < 1 || month > 12) {
    await sendHtml(chatId, '⚠️ Формат: <code>/month 07.2026</code>');
    return;
  }

  const allRecords = await loadRecords();
  const usersList = await loadUsers();
  const table = buildMonthTable(year, month, allRecords.records, usersList);
  await sendRich(chatId, table);
  await sendHtml(chatId, '⌨️ Выберите действие:', menuKeyboard(requesterId, await isAdmin(requesterId)));
}

async function cmdSoon(chatId, requesterId, arg) {
  let n = Number(String(arg).trim());
  if (!n || n <= 0) n = 14;
  n = Math.min(n, 365);

  const from = new Date().toISOString().slice(0, 10);
  const to = addDays(new Date(), n).toISOString().slice(0, 10);

  const allRecords = await loadRecords();
  const items = [];
  for (const r of allRecords.records) {
    const start = String(r.date_from);
    if (start >= from && start <= to) items.push(r);
  }
  items.sort((a, b) => String(a.date_from).localeCompare(String(b.date_from)));

  const usersList = await loadUsers();
  const lines = [`<b>🔜 Уходят в ближайшие ${n} дн.</b>`, ''];
  if (items.length === 0) {
    lines.push('Никто не планирует отсутствие в этом периоде.');
  } else {
    for (const r of items) {
      const u = usersList.find((u) => String(u.id) === String(r.user_id));
      lines.push(`• ${esc(formatInterval(r.date_from, effectiveTo(r)))} — ${esc(displayName(u ?? { id: r.user_id }))}`);
    }
  }

  await sendHtml(chatId, lines.join('\n'), menuKeyboard(requesterId, await isAdmin(requesterId)));
}

async function cmdUser(chatId, requesterId, arg) {
  arg = String(arg ?? '').trim();
  let year = null;
  const m = /^(.+?)\s+(\d{4})$/u.exec(arg);
  if (m) {
    arg = m[1].trim();
    year = Number(m[2]);
  }
  if (year === null) year = new Date().getFullYear();

  const user = await resolveUser(arg);
  if (!user) {
    await sendHtml(chatId, 'Сотрудник не найден. Список: /users');
    return;
  }

  const targetId = String(user.id);
  const isSelf = targetId === requesterId;
  const recordsList = await recordsForUserYear(targetId, year);

  const lines = [`<b>👤 ${esc(displayName(user))} · ${year}</b>`, ''];
  if (recordsList.length === 0) {
    lines.push('Записей нет.');
  } else {
    let num = 0;
    for (const r of recordsList) {
      num++;
      const interval = esc(formatInterval(r.date_from, effectiveTo(r)));
      const days = effectiveDays(r);
      lines.push(
        isSelf
          ? `${num}. ${esc(typeLabel(r.type))} · ${interval} (${days})`
          : `${num}. ${interval} (${days})`,
      );
      if (isSelf && String(r.comment ?? '').trim() !== '') {
        lines.push(`    💬 ${esc(r.comment)}`);
      }
    }
  }

  if (isSelf) {
    const balance = await balanceFor(targetId, year);
    lines.push('');
    lines.push(`🏖 Остаток отпуска: <b>${balance.remaining}</b> из ${balance.norm}`);
  }

  await sendHtml(chatId, lines.join('\n'), menuKeyboard(requesterId, await isAdmin(requesterId)));
}

async function cmdUsers(chatId, requesterId) {
  const usersList = await loadUsers();
  if (usersList.length === 0) {
    await sendHtml(chatId, 'Список пуст.');
    return;
  }

  const items = [...usersList].sort((a, b) => String(a.name).localeCompare(String(b.name), undefined, { sensitivity: 'base' }));

  const lines = ['<b>👥 Сотрудники</b>', '', 'Выберите сотрудника, чтобы посмотреть записи за текущий год:'];
  for (const u of items) {
    const active = u.active === true ? '' : ' <i>(отключён)</i>';
    const uname = String(u.username ?? '').trim();
    lines.push(`• ${esc(displayName(u))}${uname !== '' ? ` (@${esc(uname)})` : ''} <code>${esc(String(u.id))}</code>${active}`);
  }

  await sendHtml(chatId, lines.join('\n'), menuKeyboard(requesterId, await isAdmin(requesterId)));
}

// ---------------------------------------------------------------------------
// Админ: пользователи
// ---------------------------------------------------------------------------

async function cmdAddUser(chatId, requesterId, arg) {
  if (!(await isAdmin(requesterId))) {
    await sendHtml(chatId, '⛔ Команда только для администратора.');
    return;
  }

  const m = /^(\d+)\s+(.+)$/u.exec(String(arg ?? '').trim());
  if (!m) {
    await sendHtml(
      chatId,
      'Использование: <code>/adduser &lt;tg_id&gt; &lt;Имя&gt;</code>\n'
      + 'Сотрудник узнаёт свой ID командой /id.',
    );
    return;
  }

  const id = String(Number(m[1]));
  const name = String(m[2]).trim();

  await addUser(id, name, '', 'user', null);
  await sendHtml(chatId, `✅ Добавлен: ${esc(name)} <code>${esc(id)}</code>`);
}

async function cmdDelUser(chatId, requesterId, arg) {
  if (!(await isAdmin(requesterId))) {
    await sendHtml(chatId, '⛔ Команда только для администратора.');
    return;
  }

  const id = String(Number(String(arg ?? '').trim()));
  if (!id || id === '0') {
    await sendHtml(chatId, 'Использование: <code>/deluser &lt;tg_id&gt;</code>');
    return;
  }

  const removed = await deactivateUser(id);
  await sendHtml(chatId, removed
    ? `🚫 Доступ отключён для <code>${esc(id)}</code>. Историю записей сохранил.`
    : '⚠️ Пользователь не найден.');
}

// ---------------------------------------------------------------------------
// Админские inline-действия
// ---------------------------------------------------------------------------

async function adminAction(chatId, userId, parts) {
  if (!(await isAdmin(userId))) {
    await sendHtml(chatId, '⛔ Раздел только для администратора.');
    return;
  }

  switch (String(parts[1] ?? '')) {
    case 'add':
      await setSession(userId, { step: 'admin_adduser' });
      await sendHtml(
        chatId,
        '➕ <b>Новый сотрудник</b>\n\nОтправьте одним сообщением:\n'
        + '<code>&lt;tg_id&gt; Имя Фамилия</code>\n\n'
        + 'Сотрудник узнаёт свой ID командой /id.',
        inline([[btn('❌ Отмена', 'adm:list')]]),
      );
      return;
    case 'toggle':
      await cmdAdminToggle(chatId, userId, String(parts[2] ?? ''));
      return;
    case 'list':
    default:
      await clearSession(userId);
      await cmdAdminUsers(chatId, userId);
      return;
  }
}

async function cmdAdminUsers(chatId, requesterId) {
  if (!(await isAdmin(requesterId))) {
    await sendHtml(chatId, '⛔ Раздел только для администратора.');
    return;
  }

  const usersList = await loadUsers();
  const items = [...usersList].sort((a, b) => String(a.name).localeCompare(String(b.name), undefined, { sensitivity: 'base' }));

  const lines = ['⚙️ <b>Админ: сотрудники</b>', '', 'Нажмите на сотрудника, чтобы включить/отключить доступ:'];
  const rows = [];
  for (const u of items) {
    const id = String(u.id);
    const active = u.active === true;
    const mark = active ? '✅' : '🚫';
    lines.push(`${mark} ${esc(displayName(u))} <code>${esc(id)}</code>${id === ADMIN_ID ? ' <i>(админ)</i>' : ''}`);
    rows.push([btn(`${mark} ${esc(displayName(u))}`, `adm:toggle:${id}`)]);
  }
  rows.push([btn('➕ Добавить сотрудника', 'adm:add')]);
  rows.push([btn('🔄 Обновить', 'adm:list')]);

  await sendHtml(chatId, lines.join('\n'), inline(rows));
}

async function cmdAdminToggle(chatId, requesterId, targetId) {
  if (!(await isAdmin(requesterId))) {
    await sendHtml(chatId, '⛔ Раздел только для администратора.');
    return;
  }

  const id = String(Number(String(targetId).trim()));
  if (!id || id === '0') {
    await cmdAdminUsers(chatId, requesterId);
    return;
  }

  const user = await findUser(id);
  if (!user) {
    await sendHtml(chatId, '⚠️ Пользователь не найден.');
    return;
  }

  const nowActive = user.active !== true;
  if (nowActive) {
    await activateUser(id);
  } else {
    await deactivateUser(id);
  }

  await sendHtml(chatId, `${nowActive ? '✅ Доступ включён: ' : '🚫 Доступ отключён: <code>'}${esc(id)}</code>`);
  await cmdAdminUsers(chatId, requesterId);
}

// ---------------------------------------------------------------------------
// Inline-меню
// ---------------------------------------------------------------------------

async function menuAction(chatId, userId, action) {
  switch (action) {
    case 'add':
      await startAddDialog(chatId, userId);
      return;
    case 'my':
      await cmdMy(chatId, userId, '');
      return;
    case 'left':
      await cmdLeft(chatId, userId, '');
      return;
    case 'today':
      await cmdToday(chatId, userId);
      return;
    case 'week':
      await cmdWeek(chatId, userId);
      return;
    case 'month':
      await cmdMonth(chatId, userId, '');
      return;
    case 'soon':
      await cmdSoon(chatId, userId, '');
      return;
    case 'users':
      await cmdUsers(chatId, userId);
      return;
    case 'admin':
      await cmdAdminUsers(chatId, userId);
      return;
    case 'help':
    default:
      await cmdHelp(chatId, userId);
      return;
  }
}

async function addAction(chatId, userId, parts) {
  const sub = String(parts[1] ?? '');
  const value = String(parts[2] ?? '');

  switch (sub) {
    case 'type':
      if (!(value in ABSENCE_TYPES)) {
        await sendHtml(chatId, '⚠️ Неизвестный тип. Начните заново: /add');
        return;
      }
      await setSession(userId, { step: 'dates', type: value, ranges: [], comment: '' });
      await sendHtml(
        chatId,
        '📅 <b>Введите даты</b> отсутствия (' + esc(typeLabel(value)) + '):\n'
        + '• <code>01.07.2026-14.07.2026</code> — диапазон с годом\n'
        + '• <code>01.07-14.07</code> — диапазон в текущем году\n'
        + '• <code>01.07</code> — один день\n'
        + '• <code>01.07 +14</code> — дата старта и количество дней\n'
        + '• можно несколько через запятую: <code>01.07-14.07, 01.09-05.09</code>',
        inline([[btn('❌ Отмена', 'add:cancel')]]),
      );
      return;
    case 'comment':
      await setSession(userId, { step: 'comment' });
      await sendHtml(
        chatId,
        '💬 Введите комментарий (или «-», чтобы очистить):',
        inline([[btn('❌ Отмена', 'add:cancel')]]),
      );
      return;
    case 'skipcomment': {
      const session = await getSession(userId);
      if (session) {
        await setSession(userId, { ...session, comment: '', step: 'confirm' });
        await sendAddConfirm(chatId, userId, { ...session, comment: '', step: 'confirm' });
      }
      return;
    }
    case 'edit': {
      const session = await getSession(userId);
      if (session) {
        await setSession(userId, { ...session, step: 'dates' });
        await sendHtml(chatId, '📅 Введите новые даты:');
      }
      return;
    }
    case 'confirm':
      await confirmAdd(chatId, userId);
      return;
    case 'cancel':
    default:
      await clearSession(userId);
      await sendHtml(chatId, '❌ Отменено.');
      return;
  }
}

async function recAction(chatId, userId, parts, messageId) {
  const action = String(parts[1] ?? '');
  const id = Number(parts[2] ?? 0);
  if (!id || id <= 0) return;

  const record = await findRecord(id);
  if (!record) {
    const verb = action === 'cancel' ? '⛔ Можно отменять только свои записи.'
      : action === 'finish' ? '⛔ Можно завершать только свои записи.'
      : '⛔ Можно редактировать только свои записи.';
    await sendHtml(chatId, verb);
    return;
  }

  if (String(record.user_id) !== String(userId) && !(await isAdmin(userId))) {
    const verb = action === 'cancel' ? '⛔ Можно отменять только свои записи.'
      : action === 'finish' ? '⛔ Можно завершать только свои записи.'
      : '⛔ Можно редактировать только свои записи.';
    await sendHtml(chatId, verb);
    return;
  }

  const number = await numberOfRecordById(id, userId);
  const label = number !== null ? `Запись №${number}` : 'Запись';

  switch (action) {
    case 'cancel': {
      const result = await deleteRecord(id, userId);
      if (!result.ok) {
        await sendHtml(chatId, `⚠️ ${errText(result.err, '⛔ Можно отменять только свои записи.')}`);
        return;
      }
      await sendHtml(chatId, `🗑 ${label} отменена.`);
      return;
    }
    case 'edit': {
      const comment = String(record.comment ?? '').trim();
      await sendHtml(
        chatId,
        `✏️ <b>${label}</b>\n\nТип: ${esc(typeLabel(record.type))}\n`
        + `Даты: ${esc(formatInterval(record.date_from, effectiveTo(record)))} (${effectiveDays(record)} к.д.)\n`
        + `Комментарий: ${comment === '' ? '—' : esc(comment)}\n\n`
        + 'Что изменить?',
        inline([
          [btn('✏️ Даты', `rec:editdates:${id}`), btn('💬 Комментарий', `rec:comment:${id}`)],
        ]),
      );
      return;
    }
    case 'editdates':
      await setSession(userId, { step: 'edit_dates', target: id });
      await sendHtml(chatId, `📅 Введите новые даты для ${label.charAt(0).toLowerCase()}${label.slice(1)}:`);
      return;
    case 'comment':
      await setSession(userId, { step: 'edit_comment', target: id });
      await sendHtml(
        chatId,
        `💬 Введите комментарий для ${label.charAt(0).toLowerCase()}${label.slice(1)} (или «-», чтобы очистить):`,
        inline([[btn('❌ Отмена', 'add:cancel')]]),
      );
      return;
    case 'finish':
      await setSession(userId, { step: 'finish_date', target: id });
      await sendHtml(
        chatId,
        `🏁 Введите дату фактического выхода для ${label.charAt(0).toLowerCase()}${label.slice(1)}\n(или «сегодня»):`,
        inline([[btn('❌ Отмена', 'add:cancel')]]),
      );
      return;
  }
}

// ---------------------------------------------------------------------------
// Вспомогательное
// ---------------------------------------------------------------------------

function errText(err, notOwner = '⛔ Это не ваша запись.') {
  switch (err) {
    case 'not_owner': return notOwner;
    case 'out_of_range': return '⚠️ Дата выхода должна быть внутри периода отсутствия.';
    default: return '⚠️ Запись не найдена.';
  }
}

function addDays(date, days) {
  const result = new Date(date);
  result.setDate(result.getDate() + days);
  return result;
}

async function resolveUser(query) {
  query = String(query ?? '').trim();
  if (!query) return null;

  const usersList = await loadUsers();
  const byId = usersList.find((u) => String(u.id) === query);
  if (byId) return byId;

  const needle = query.replace(/^@/, '').toLowerCase();
  if (!needle) return null;

  for (const u of usersList) {
    if (String(u.username ?? '').toLowerCase() === needle) return u;
  }
  for (const u of usersList) {
    if (String(u.name ?? '').toLowerCase().includes(needle)) return u;
  }

  return null;
}

function buildMonthTable(year, month, recordsList, usersList) {
  const first = new Date(`${String(year).padStart(4, '0')}-${String(month).padStart(2, '0')}-01T00:00:00`);
  const daysInMonth = new Date(year, month, 0).getDate();
  const monthEnd = `${String(year).padStart(4, '0')}-${String(month).padStart(2, '0')}-${String(daysInMonth).padStart(2, '0')}`;
  const monthStart = `${String(year).padStart(4, '0')}-${String(month).padStart(2, '0')}-01`;

  const marks = {};
  for (const r of recordsList) {
    if (String(r.date_from) > monthEnd || effectiveTo(r) < monthStart) continue;
    const uid = String(r.user_id);
    if (!marks[uid]) marks[uid] = {};
    for (let d = 1; d <= daysInMonth; d++) {
      const ymd = `${String(year).padStart(4, '0')}-${String(month).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
      if (isActiveOn(r, ymd)) marks[uid][d] = true;
    }
  }

  const mNames = ['январь', 'февраль', 'март', 'апрель', 'май', 'июнь', 'июль', 'август', 'сентябрь', 'октябрь', 'ноябрь', 'декабрь'];
  const title = `🗓 <b>${mNames[month - 1].charAt(0).toUpperCase()}${mNames[month - 1].slice(1)} ${year}</b>`;

  if (Object.keys(marks).length === 0) {
    return `${title}\n\nЗа этот месяц отсутствий нет.`;
  }

  const sortedUids = Object.keys(marks).sort((a, b) => {
    const ua = usersList.find((u) => String(u.id) === a);
    const ub = usersList.find((u) => String(u.id) === b);
    const na = ua ? displayName(ua) : `ID ${a}`;
    const nb = ub ? displayName(ub) : `ID ${b}`;
    return na.localeCompare(nb, undefined, { sensitivity: 'base' });
  });

  const html = [title, '', '<table bordered>'];
  let header = '<tr><th>Сотрудник</th>';
  for (let d = 1; d <= daysInMonth; d++) header += `<th>${d}</th>`;
  header += '</tr>';
  html.push(header);

  for (const uid of sortedUids) {
    const u = usersList.find((uu) => String(uu.id) === uid);
    const name = u ? displayName(u) : `ID ${uid}`;
    let row = `<tr><td>${esc(name)}</td>`;
    for (let d = 1; d <= daysInMonth; d++) {
      row += `<td>${marks[uid][d] ? '•' : ''}</td>`;
    }
    row += '</tr>';
    html.push(row);
  }
  html.push('</table>', '', '<i>• — сотрудник отсутствует (тип не раскрывается).</i>');
  return html.join('\n');
}
