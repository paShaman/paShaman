import { toBase36, truncate } from './utils.js';

export function btn(text, data) {
  return { text, callback_data: data };
}

export function inline(rows) {
  return { inline_keyboard: rows };
}

export function webBtn(text, url) {
  return url ? { text, web_app: { url } } : null;
}

export function appRow(url) {
  const button = webBtn('Открыть приложение', url);
  return button ? [button] : [];
}

export function myCampaignsKeyboard(items) {
  const rows = items.slice(0, 10).map(({ campaign, membership }) => [
    btn(
      `${campaign.status === 'completed' ? '✅' : campaign.status === 'cancelled' ? '🚫' : '🎄'} ${truncate(campaign.title, 24)}${membership.role === 'organizer' ? ' ⚙️' : ''}`,
      `ss:co:${toBase36(campaign.id)}`,
    ),
  ]);
  rows.push([btn('Создать кампанию', 'ss:nw'), btn('Помощь', 'ss:hl')]);
  return inline(rows);
}

export function welcomeKeyboard() {
  return inline([
    [btn('Мои кампании', 'ss:my'), btn('Создать кампанию', 'ss:nw')],
    [btn('Помощь', 'ss:hl')],
  ]);
}

export function campaignKeyboard({ campaign, isOrganizer, appUrl }) {
  const id = toBase36(campaign.id);
  const rows = [];
  const app = appRow(appUrl);
  if (app.length) rows.push(app);
  if (!isOrganizer) {
    rows.push([btn('Правила', `ss:cr:${id}`), btn('Мои кампании', 'ss:my')]);
    return inline(rows);
  }
  rows.push([btn('Участники', `ss:mp:${id}:1`), btn('Исключения', `ss:xl:${id}`)]);
  if (campaign.status === 'recruiting') {
    rows.push([btn('Закрыть набор', `ss:dl:${id}`)]);
  }
  if (campaign.status === 'locked') {
    rows.push([btn('Собрать пары', `ss:dv:${id}`)]);
  }
  if (campaign.status === 'drawn') {
    rows.push([btn('Начать вручение', `ss:gi:${id}`)]);
  }
  if (['recruiting', 'locked', 'drawn', 'gifting'].includes(campaign.status)) {
    rows.push([btn('Рассылка', `ss:bl:${id}`), btn('Статистика', `ss:st:${id}`)]);
    rows.push([btn('Логотип', `ss:lg:${id}`)]);
    rows.push([btn('Завершить', `ss:cm:${id}`), btn('Отменить', `ss:cn:${id}`)]);
  } else {
    rows.push([btn('Статистика', `ss:st:${id}`)]);
  }
  rows.push([btn('Мои кампании', 'ss:my')]);
  return inline(rows);
}

export function membersKeyboard(campaignId, page, totalPages, members, isOrganizer, pageSize = 10) {
  const cid = toBase36(campaignId);
  const rows = [];
  for (const member of members) {
    const name = truncate(
      member.user?.firstName || member.user?.username || `ID ${member.userId}`,
      24,
    );
    const status =
      member.status === 'confirmed'
        ? '✅'
        : member.status === 'rules_accepted'
          ? '📜'
          : member.status === 'removed'
            ? '🚫'
            : '⏳';
    const row = [btn(`${status} ${name}`, `ss:mi:${cid}:${toBase36(member.userId)}`)];
    if (
      isOrganizer &&
      member.role !== 'organizer' &&
      !['removed', 'declined'].includes(member.status)
    ) {
      row.push(btn('Удалить', `ss:mx:${cid}:${toBase36(member.userId)}`));
    }
    rows.push(row);
  }
  const nav = [];
  if (page > 1) nav.push(btn('←', `ss:mp:${cid}:${page - 1}`));
  if (page < totalPages) nav.push(btn('→', `ss:mp:${cid}:${page + 1}`));
  if (nav.length) rows.push(nav);
  rows.push([btn('Назад к кампании', `ss:co:${cid}`)]);
  return inline(rows);
}

export function memberProfileKeyboard(campaignId, { canRemove = false, memberId = null } = {}) {
  const cid = toBase36(campaignId);
  const rows = [];
  if (canRemove && memberId != null) {
    rows.push([btn('Удалить участника', `ss:mx:${cid}:${toBase36(memberId)}`)]);
  }
  rows.push([btn('К участникам', `ss:mp:${cid}:1`)]);
  return inline(rows);
}

export function removeMemberConfirmKeyboard(campaignId, userId, version) {
  const cid = toBase36(campaignId);
  const uid = toBase36(userId);
  return inline([
    [btn('Да, удалить', `ss:mz:${cid}:${uid}:${toBase36(version)}`), btn('Отмена', `ss:co:${cid}`)],
  ]);
}

export function exclusionsKeyboard(campaignId, rows) {
  const cid = toBase36(campaignId);
  const keyboard = [];
  for (const row of rows) {
    keyboard.push([btn('Удалить исключение', `ss:xr:${cid}:${toBase36(row.id)}`)]);
  }
  keyboard.push([btn('Добавить исключение', `ss:xa:${cid}:1`)]);
  keyboard.push([btn('Назад к кампании', `ss:co:${cid}`)]);
  return inline(keyboard);
}

export function exclusionPickOneKeyboard(campaignId, members, page, totalPages) {
  const cid = toBase36(campaignId);
  const rows = members.map((member) => [
    btn(
      truncate(member.user?.firstName || member.user?.username || `ID ${member.userId}`, 28),
      `ss:xb:${cid}:${toBase36(member.userId)}:1`,
    ),
  ]);
  const nav = [];
  if (page > 1) nav.push(btn('←', `ss:xa:${cid}:${page - 1}`));
  if (page < totalPages) nav.push(btn('→', `ss:xa:${cid}:${page + 1}`));
  if (nav.length) rows.push(nav);
  rows.push([btn('Отмена', `ss:xl:${cid}`)]);
  return inline(rows);
}

export function exclusionPickTwoKeyboard(campaignId, members, firstId, page, totalPages) {
  const cid = toBase36(campaignId);
  const rows = members.map((member) => [
    btn(
      truncate(member.user?.firstName || member.user?.username || `ID ${member.userId}`, 28),
      `ss:xc:${cid}:${toBase36(firstId)}:${toBase36(member.userId)}`,
    ),
  ]);
  const nav = [];
  if (page > 1) nav.push(btn('←', `ss:xb:${cid}:${toBase36(firstId)}:${page - 1}`));
  if (page < totalPages) nav.push(btn('→', `ss:xb:${cid}:${toBase36(firstId)}:${page + 1}`));
  if (nav.length) rows.push(nav);
  rows.push([btn('Отмена', `ss:xl:${cid}`)]);
  return inline(rows);
}

export function drawKeyboard(campaignId, pairs, names, organizerId, page, totalPages, version) {
  const cid = toBase36(campaignId);
  const rows = [];
  const visible = pairs.filter((pair) => Number(pair.giverId) !== Number(organizerId));
  const start = (page - 1) * 8;
  for (const pair of visible.slice(start, start + 8)) {
    const name = truncate(names?.get(pair.giverId) ?? String(pair.giverId), 20);
    rows.push([btn(`Заменить: ${name}`, `ss:dr:${cid}:${toBase36(pair.giverId)}`)]);
  }
  const nav = [];
  if (page > 1) nav.push(btn('←', `ss:dv:${cid}:${page - 1}`));
  if (page < totalPages) nav.push(btn('→', `ss:dv:${cid}:${page + 1}`));
  if (nav.length) rows.push(nav);
  rows.push([btn('Пересобрать всё', `ss:dg:${cid}`)]);
  rows.push([
    btn('Подтвердить', `ss:dk:${cid}:${toBase36(version)}`),
    btn('Назад', `ss:co:${cid}`),
  ]);
  return inline(rows);
}

export function confirmKeyboard(campaignId, action, version) {
  const cid = toBase36(campaignId);
  return inline([
    [
      btn('Да, подтверждаю', `ss:${action}c:${cid}:${toBase36(version)}`),
      btn('Отмена', `ss:co:${cid}`),
    ],
  ]);
}

export function broadcastMenuKeyboard(campaignId, presets) {
  const cid = toBase36(campaignId);
  const rows = presets.map((preset) => [btn(preset.label, `ss:bp:${cid}:${preset.key}`)]);
  rows.push([btn('Свой текст', `ss:bc:${cid}`)]);
  rows.push([btn('Назад к кампании', `ss:co:${cid}`)]);
  return inline(rows);
}

export function broadcastPreviewKeyboard(campaignId) {
  const cid = toBase36(campaignId);
  return inline([
    [btn('Отправить', `ss:bs:${cid}`), btn('Отмена', `ss:bx:${cid}`)],
  ]);
}

export function rulesKeyboard(campaignId) {
  const cid = toBase36(campaignId);
  return inline([[btn('Назад к кампании', `ss:co:${cid}`)]]);
}

export function reportsKeyboard(campaignId, reports) {
  const rows = reports.map((report) => [
    btn(
      `#${report.id} ${truncate(report.text, 24)}`,
      `ss:rv:${toBase36(report.id)}`,
    ),
  ]);
  if (campaignId) rows.push([btn('Назад к кампании', `ss:co:${toBase36(campaignId)}`)]);
  rows.push([btn('Мои кампании', 'ss:my')]);
  return inline(rows);
}

export function reportActionsKeyboard(messageId, assignmentId, senderUserId, isAdmin) {
  const mid = toBase36(messageId);
  const rows = [
    [btn('Скрыть сообщение', `ss:rh:${mid}`), btn('Заблокировать чат', `ss:rb:${toBase36(assignmentId)}`)],
  ];
  if (isAdmin) rows.push([btn('Забанить автора', `ss:ru:${toBase36(senderUserId)}`)]);
  rows.push([btn('К жалобам', 'ss:rl:1')]);
  return inline(rows);
}

export function adminKeyboard() {
  return inline([
    [btn('Жалобы', 'ss:rl:1'), btn('Статистика бота', 'ss:as')],
    [btn('Мои кампании', 'ss:my')],
  ]);
}

export function cancelOnlyKeyboard() {
  return inline([[btn('Отмена', 'ss:hl')]]);
}
