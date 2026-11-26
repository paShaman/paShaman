import { APP_URL, BOT_USERNAME, MAX_BROADCAST } from './config.js';
import {
  displayName,
  esc,
  formatBudget,
  formatDateRu,
  formatPriceRange,
  pluralRu,
  truncate,
} from './utils.js';
import { formatMoney } from './utils.js';

export const STATUS_LABELS = {
  recruiting: 'Набор участников',
  locked: 'Набор закрыт',
  drawn: 'Пары собраны',
  gifting: 'Вручение подарков',
  completed: 'Завершена',
  cancelled: 'Отменена',
};

export const DELIVERY_LABELS = {
  meetup: 'Встреча',
  shipping: 'Доставка',
  hybrid: 'Встреча или доставка',
};

export function appUrl() {
  return /^https:\/\//i.test(APP_URL) ? APP_URL : '';
}

export function inviteUrl(code) {
  return BOT_USERNAME
    ? `https://t.me/${BOT_USERNAME}?start=join_${code}`
    : '';
}

export function welcomeText(name) {
  return `🎄 <b>Тайный Санта</b>

Привет${name ? `, ${esc(name)}` : ''}! Здесь можно собрать друзей, семью или команду и обменяться подарками, не раскрывая себя раньше времени.

Организатор создаёт кампанию, участники заполняют вишлист, бот собирает пары, а переписка идёт анонимно внутри приложения.

Начни с «Мои кампании» или создай новую.`;
}

export function helpText(isAdmin) {
  return `🎄 <b>Помощь</b>

/start — главное меню
/new — создать кампанию
/my — мои кампании
/privacy — что храним и как удалить
/cancel — отменить текущий шаг

Организатор управляет кампанией кнопками под карточкой: участники, исключения, жеребьёвка, рассылка, завершение.

Мини-приложение открывается кнопкой «Открыть приложение». Если кнопки нет, значит, организатор ещё не настроил адрес — используйте код кампании в приложении${isAdmin ? '\n\nАдмин: /admin, /stats, /setcommands, /ban &lt;id&gt;, /unban &lt;id&gt;' : ''}`;
}

export function privacyText() {
  return `🔒 <b>Приватность</b>

Что храним:
• имя и username Telegram — чтобы участники узнавали друг друга в кампании;
• вишлист и фото желаний — их видит только ваш Санта;
• адрес и телефон — только ваш Санта, если выбран способ доставки;
• сообщения чата — для модерации жалоб;
• отметки «куплено» — только ваш Санта.

Что не храним:
• пароли и платёжные данные;
• данные из других кампаний.

Сроки:
• адрес и отметки «куплено» удаляются при завершении кампании;
• сообщения чата остаются для разбора жалоб и не показываются после завершения.

Удалить свои данные можно через организатора или администратора бота.`;
}

export function campaignStatusHint(campaign, progress, now = new Date()) {
  if (campaign.status === 'recruiting') {
    if (new Date(campaign.registrationEndsAt).getTime() < now.getTime()) {
      return 'Дата окончания набора прошла. Организатору пора закрыть набор.';
    }
    if (progress.confirmed < 3) {
      return `Нужно ещё ${3 - progress.confirmed} ${pluralRu(
        3 - progress.confirmed,
        'подтверждение',
        'подтверждения',
        'подтверждений',
      )}, чтобы собрать пары.`;
    }
    return 'Можно закрывать набор, когда все подтвердят участие и заполнят вишлисты.';
  }
  if (campaign.status === 'locked') return 'Набор закрыт. Организатор собирает пары.';
  if (campaign.status === 'drawn') return 'Пары готовы. Загляните в приложение за получателем.';
  if (campaign.status === 'gifting') return 'Подарки в пути. Отмечайте статусы в приложении.';
  if (campaign.status === 'completed') return 'Кампания завершена. Спасибо за участие!';
  return 'Кампания отменена.';
}

export function campaignCard(campaign, { progress, stats, isOrganizer, isConfirmed, now } = {}) {
  const invite = inviteUrl(campaign.code);
  const lines = [
    `🎄 <b>${esc(campaign.title)}</b>`,
    `<i>${STATUS_LABELS[campaign.status] ?? campaign.status}</i>`,
    '',
    `🏷 Код: <code>${campaign.code}</code>`,
    `💰 Бюджет: ${formatBudget(campaign.budgetMin, campaign.budgetMax, campaign.currency)}`,
    `📅 Набор до: ${formatDateRu(campaign.registrationEndsAt)}`,
    `🎁 Вручение: ${formatDateRu(campaign.giftDate)}`,
    `🚚 Формат: ${DELIVERY_LABELS[campaign.deliveryMode] ?? campaign.deliveryMode}`,
  ];
  if (campaign.description) lines.push('', esc(campaign.description));
  if (progress) {
    lines.push(
      '',
      `👥 Подтверждено: ${progress.confirmed} из ${progress.total}`,
      `📝 Вишлистов: ${stats?.wishlist ?? 0}`,
    );
  }
  if (isOrganizer) {
    if (stats) {
      lines.push(
        `📦 Отправлено: ${stats.sent ?? 0}, получено: ${stats.received ?? 0}`,
        `⚠️ Жалоб: ${stats.reports ?? 0}`,
      );
    }
    if (invite) {
      lines.push('', `Приглашение: ${invite}`);
    } else {
      lines.push('', `Приглашение: код <code>${campaign.code}</code>`);
    }
  } else if (!isConfirmed) {
    lines.push('', 'Вы ещё не подтвердили участие. Откройте приложение и заполните вишлист.');
  }
  lines.push('', `<i>${campaignStatusHint(campaign, progress ?? { confirmed: 0 }, now)}</i>`);
  return lines.join('\n');
}

export function membersText(campaign, members, page, pageSize = 10) {
  const totalPages = Math.max(1, Math.ceil(members.length / pageSize));
  const safePage = Math.min(Math.max(1, page), totalPages);
  const slice = members.slice((safePage - 1) * pageSize, safePage * pageSize);
  const lines = [
    `👥 <b>Участники</b> — ${esc(campaign.title)}`,
    `Страница ${safePage} из ${totalPages}`,
    '',
  ];
  if (slice.length === 0) lines.push('Пока никого нет.');
  for (const member of slice) {
    const name = displayName(member.user);
    const status =
      member.status === 'confirmed'
        ? '✅'
        : member.status === 'rules_accepted'
          ? '📜'
          : member.status === 'removed'
            ? '🚫'
            : '⏳';
    const role = member.role === 'organizer' ? ' · организатор' : '';
    lines.push(`${status} ${esc(name)}${role}`);
  }
  lines.push('', '✅ подтверждён · 📜 принял правила · ⏳ вступил · 🚫 удалён');
  return { text: lines.join('\n'), page: safePage, totalPages, slice };
}

export function exclusionsText(campaign, rows, names) {
  const lines = [`🚫 <b>Исключения</b> — ${esc(campaign.title)}`, ''];
  if (rows.length === 0) {
    lines.push('Пока нет исключений. Можно указать пары, которые не должны дарить друг другу.');
  } else {
    for (const row of rows) {
      lines.push(`${esc(names.get(row.userA) ?? row.userA)} ↔ ${esc(names.get(row.userB) ?? row.userB)}`);
    }
  }
  lines.push('', 'Исключения действуют только до жеребьёвки.');
  return lines.join('\n');
}

export function rulesText(campaign) {
  return `📜 <b>Правила кампании «${esc(campaign.title)}»</b>\n\n${esc(campaign.rules)}`;
}

export function drawText(pairs, names, organizerId, page, pageSize = 8) {
  const totalPages = Math.max(1, Math.ceil(pairs.length / pageSize));
  const safePage = Math.min(Math.max(1, page), totalPages);
  const visible = pairs.filter((pair) => Number(pair.giverId) !== Number(organizerId));
  const offset = (safePage - 1) * pageSize;
  const slice = visible.slice(offset, offset + pageSize);
  const lines = [
    '🎲 <b>Превью жеребьёвки</b>',
    `Пар: ${pairs.length}. Своя пара скрыта до подтверждения.`,
    '',
  ];
  if (slice.length === 0) lines.push('Кроме вашей пары здесь никого нет.');
  for (const pair of slice) {
    lines.push(
      `${esc(names.get(pair.giverId) ?? pair.giverId)} → ${esc(
        names.get(pair.receiverId) ?? pair.receiverId,
      )}`,
    );
  }
  lines.push('', 'Кнопка «Заменить» меняет получателя только у этой пары.');
  return { text: lines.join('\n'), page: safePage, totalPages, slice };
}

export function drawReadyText(names) {
  return `🎲 <b>Пары собраны</b>\n\nТеперь можно посмотреть получателя в приложении: ${
    names ? esc(names) : 'ваш Санта уже ждёт'
  }. Не раскрывайте себя раньше времени.`;
}

export function assignmentIntro() {
  return '🎁 <b>Ваш получатель готов</b>\n\nОткройте приложение, чтобы увидеть вишлист, адрес доставки (если он нужен) и написать анонимное сообщение.';
}

export function broadcastMenuText(campaign, count) {
  return `✉️ <b>Рассылка</b> — ${esc(campaign.title)}

Получателей: ${count} (максимум ${MAX_BROADCAST} за раз). Выберите готовый текст или отправьте свой.`;
}

export function broadcastPreviewText(text, count) {
  return `✉️ <b>Превью рассылки</b>\n\n${esc(text)}\n\n— — —\nПолучателей: ${count}`;
}

export function broadcastReport(stats) {
  return `✅ <b>Рассылка завершена</b>\n\nОтправлено: ${stats.sent}\nЗаблокировали бота: ${stats.blocked}\nОшибки: ${stats.failed}\nВсего: ${stats.recipients}`;
}

export function broadcastHtml(campaign, text) {
  return `🎄 <b>${esc(campaign.title)}</b>\n──────────\n\n${esc(text)}\n\n──────────\n<i>Тайный Санта</i>`;
}

export function memberRemovedText(campaign, member) {
  return `🚫 ${esc(displayName(member))} удалён(а) из кампании «${esc(campaign.title)}».`;
}

export function reportText(message, assignment, senderName, receiverName) {
  return `⚠️ <b>Жалоба на сообщение</b>\n\n${esc(message.text)}\n\nАвтор: ${esc(
    senderName,
  )}\nПара: ${esc(senderName)} → ${esc(receiverName)}\nПричина: ${esc(
    message.reportReason || 'не указана',
  )}`;
}

export function campaignStatsText(campaign, stats, progress) {
  return `📊 <b>Статистика</b> — ${esc(campaign.title)}

Участников: ${progress.total}
Подтверждено: ${progress.confirmed}
Пунктов вишлиста: ${stats.wishlist}
Адресов доставки: ${stats.deliveries}
Подарков отправлено: ${stats.sent}
Подарков получено: ${stats.received}
Жалоб: ${stats.reports}`;
}

export function priceText(item) {
  const price = formatPriceRange(item.priceMin, item.priceMax, item.currency);
  return price ? ` · ${price}` : '';
}

export function itemText(item) {
  const url = item.url ? `\n${item.url}` : '';
  return `• ${esc(truncate(item.title, 120))}${priceText(item)}${item.comment ? `\n  ${esc(truncate(item.comment, 120))}` : ''}${url}`;
}

export function moneyText(value, currency = 'RUB') {
  return formatMoney(value, currency);
}
