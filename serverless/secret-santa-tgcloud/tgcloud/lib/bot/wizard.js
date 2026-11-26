import { DEFAULT_RULES, MAX_CAMPAIGN_DESCRIPTION, MAX_RULES } from '../config.js';
import {
  makeCode,
  validateDraft,
  validateRules,
} from '../domain/campaign.js';
import {
  createCampaign,
  getCampaignByCode,
  logAudit,
} from '../store.js';
import { inline, btn } from '../keyboards.js';
import {
  esc,
  formatBudget,
  formatDateRu,
  moscowToday,
  normalizeSpaces,
  parseDateRu,
  parsePositiveInt,
} from '../utils.js';
import { sendHtml } from '../telegram.js';
import { openCampaign } from './dashboard.js';
import {
  clear,
  getState,
  patchWizard,
  setStep,
  setWizardStep,
  startWizard,
} from './session.js';

const DELIVERY = {
  meetup: 'Встреча',
  shipping: 'Доставка',
  hybrid: 'Встреча или доставка',
};

function cancelRow() {
  return inline([[btn('Отмена', 'ss:wc')]]);
}

export async function beginWizard(chatId, userId) {
  await startWizard(userId);
  await sendHtml(
    chatId,
    '🎄 <b>Новая кампания</b>\n\nШаг 1 из 9. Как назовём кампанию?\n\n3–80 символов, например «Тайный Санта в офисе».',
    cancelRow(),
  );
}

export async function handleWizardText(chatId, userId, session, rawText) {
  const step = String(session.step).split(':')[1];
  const draft = session.data?.draft ?? {};
  const text = String(rawText ?? '').trim();

  if (step === 'title') {
    const title = normalizeSpaces(text);
    if (title.length < 3 || title.length > 80) {
      await sendHtml(chatId, 'Название должно быть от 3 до 80 символов. Попробуйте ещё раз.', cancelRow());
      return true;
    }
    await setWizardStep(userId, 'wizard:description', { title });
    await sendHtml(
      chatId,
      `Название: <b>${esc(title)}</b>\n\nШаг 2 из 9. Добавьте описание или отправьте /skip.`,
      cancelRow(),
    );
    return true;
  }

  if (step === 'description') {
    const description = text.toLowerCase() === '/skip' ? '' : text;
    if (description.length > MAX_CAMPAIGN_DESCRIPTION) {
      await sendHtml(chatId, `Описание слишком длинное: максимум ${MAX_CAMPAIGN_DESCRIPTION} символов.`, cancelRow());
      return true;
    }
    await setWizardStep(userId, 'wizard:budget_min', { description });
    await sendHtml(chatId, 'Шаг 3 из 9. Минимальный бюджет подарка в рублях (целое число).', cancelRow());
    return true;
  }

  if (step === 'budget_min') {
    const budgetMin = parsePositiveInt(text);
    if (budgetMin == null || budgetMin > 1000000) {
      await sendHtml(chatId, 'Введите целое число от 0 до 1 000 000.', cancelRow());
      return true;
    }
    await setWizardStep(userId, 'wizard:budget_max', { budgetMin });
    await sendHtml(chatId, 'Шаг 4 из 9. Максимальный бюджет подарка в рублях.', cancelRow());
    return true;
  }

  if (step === 'budget_max') {
    const budgetMax = parsePositiveInt(text);
    if (budgetMax == null || budgetMax < Number(draft.budgetMin) || budgetMax > 1000000) {
      await sendHtml(chatId, 'Максимум должен быть целым числом и не меньше минимума.', cancelRow());
      return true;
    }
    await setWizardStep(userId, 'wizard:registration_end', { budgetMax });
    await sendHtml(
      chatId,
      `Бюджет: <b>${formatBudget(draft.budgetMin, budgetMax)}</b>\n\nШаг 5 из 9. До какого числа принимаем участников? Формат: ДД.ММ.ГГГГ.`,
      cancelRow(),
    );
    return true;
  }

  if (step === 'registration_end') {
    const registrationEndsAt = parseDateRu(text);
    if (!registrationEndsAt) {
      await sendHtml(chatId, 'Не похоже на дату. Введите в формате ДД.ММ.ГГГГ.', cancelRow());
      return true;
    }
    if (registrationEndsAt.getTime() < moscowToday().getTime()) {
      await sendHtml(chatId, 'Дата окончания набора уже прошла. Выберите будущую дату.', cancelRow());
      return true;
    }
    await setWizardStep(userId, 'wizard:gift_date', { registrationEndsAt });
    await sendHtml(chatId, 'Шаг 6 из 9. Когда вручаем подарки? Формат: ДД.ММ.ГГГГ.', cancelRow());
    return true;
  }

  if (step === 'gift_date') {
    const giftDate = parseDateRu(text);
    if (!giftDate) {
      await sendHtml(chatId, 'Не похоже на дату. Введите в формате ДД.ММ.ГГГГ.', cancelRow());
      return true;
    }
    if (giftDate.getTime() < new Date(draft.registrationEndsAt).getTime()) {
      await sendHtml(chatId, 'Дата вручения не может быть раньше окончания набора.', cancelRow());
      return true;
    }
    await setWizardStep(userId, 'wizard:delivery', { giftDate });
    await sendHtml(
      chatId,
      'Шаг 7 из 9. Как передаём подарки?',
      inline([
        [btn('Встреча', 'ss:wd:meetup'), btn('Доставка', 'ss:wd:shipping')],
        [btn('Встреча или доставка', 'ss:wd:hybrid')],
        [btn('Отмена', 'ss:wc')],
      ]),
    );
    return true;
  }

  if (step === 'logo') {
    if (text.toLowerCase() === '/skip') {
      await setWizardStep(userId, 'wizard:rules', { logoFileId: '' });
      await sendHtml(
        chatId,
        'Шаг 9 из 9. Отправьте правила кампании или /skip, чтобы взять шаблон.',
        cancelRow(),
      );
      return true;
    }
    await sendHtml(chatId, 'Пришлите фото-логотип или /skip, чтобы пропустить этот шаг.', cancelRow());
    return true;
  }

  if (step === 'rules') {
    const rules = text.toLowerCase() === '/skip' ? DEFAULT_RULES : text;
    const checked = validateRules(rules);
    if (!checked.ok) {
      await sendHtml(
        chatId,
        checked.code === 'RULES_LONG'
          ? `Правила слишком длинные: максимум ${MAX_RULES} символов.`
          : 'Правила не могут быть пустыми. Отправьте текст или /skip.',
        cancelRow(),
      );
      return true;
    }
    await setWizardStep(userId, 'wizard:preview', { rules: checked.value });
    await sendPreview(chatId, userId, { ...draft, rules: checked.value });
    return true;
  }

  if (step === 'preview') {
    await sendHtml(chatId, 'Сейчас мы на шаге публикации. Нажмите «Опубликовать» или «Отмена».', inline([
      [btn('Опубликовать', 'ss:wz')],
      [btn('Отмена', 'ss:wc')],
    ]));
    return true;
  }

  if (step === 'delivery') {
    await sendHtml(
      chatId,
      'Выберите формат кнопкой ниже — текстом этот шаг не заполняется.',
      inline([
        [btn('Встреча', 'ss:wd:meetup'), btn('Доставка', 'ss:wd:shipping')],
        [btn('Встреча или доставка', 'ss:wd:hybrid')],
        [btn('Отмена', 'ss:wc')],
      ]),
    );
    return true;
  }

  await clear(userId);
  await sendHtml(chatId, 'Черновик сброшен. Начните заново командой /new.');
  return true;
}

export async function handleWizardPhoto(chatId, userId, session, message) {
  if (session?.step !== 'wizard:logo') return false;
  const photos = Array.isArray(message?.photo) ? message.photo : [];
  if (photos.length === 0) return false;
  const fileId = photos[photos.length - 1].file_id;
  await setWizardStep(userId, 'wizard:rules', { logoFileId: fileId });
  await sendHtml(
    chatId,
    'Логотип добавлен.\n\nШаг 9 из 9. Отправьте правила кампании или /skip, чтобы взять шаблон.',
    cancelRow(),
  );
  return true;
}

async function sendPreview(chatId, userId, draft) {
  const lines = [
    '🎄 <b>Проверьте кампанию</b>',
    '',
    `<b>${esc(draft.title)}</b>`,
    draft.description ? esc(draft.description) : '',
    `💰 Бюджет: ${formatBudget(draft.budgetMin, draft.budgetMax)}`,
    `📅 Набор до: ${formatDateRu(draft.registrationEndsAt)}`,
    `🎁 Вручение: ${formatDateRu(draft.giftDate)}`,
    `🚚 Формат: ${DELIVERY[draft.deliveryMode] ?? DELIVERY.hybrid}`,
    draft.logoFileId ? '🖼 Логотип: добавлен' : '',
    '',
    'После публикации появится код приглашения. Участники смогут вступить по нему.',
  ].filter(Boolean);
  await sendHtml(
    chatId,
    lines.join('\n'),
    inline([
      [btn('Опубликовать', 'ss:wz')],
      [btn('Отмена', 'ss:wc')],
    ]),
  );
}

export async function handleWizardCallback(chatId, userId, action, value, messageId) {
  const session = await getState(userId);
  if (!session || !String(session.step).startsWith('wizard:')) {
    await sendHtml(chatId, 'Черновик уже неактуален. Начните заново: /new.');
    return true;
  }

  if (action === 'wc') {
    await clear(userId);
    await sendHtml(chatId, 'Создание кампании отменено.');
    return true;
  }

  if (action === 'wd') {
    const mode = ['meetup', 'shipping', 'hybrid'].includes(value) ? value : 'hybrid';
    await setWizardStep(userId, 'wizard:logo', { deliveryMode: mode });
    await sendHtml(
      chatId,
      `Формат: <b>${DELIVERY[mode]}</b>\n\nШаг 8 из 9. Пришлите логотип кампании (фото) или /skip.`,
      cancelRow(),
    );
    return true;
  }

  if (action === 'wz') {
    if (session.step !== 'wizard:preview') {
      await sendHtml(chatId, 'Сначала заполните все шаги. Продолжим с текущего.');
      return true;
    }
    const draft = session.data?.draft ?? {};
    const checked = validateDraft({
      title: draft.title,
      description: draft.description,
      budgetMin: draft.budgetMin,
      budgetMax: draft.budgetMax,
      registrationEndsAt: draft.registrationEndsAt,
      giftDate: draft.giftDate,
    });
    if (!checked.ok) {
      await sendHtml(chatId, `Не получилось опубликовать: ${checked.code}. Проверьте данные и попробуйте снова.`);
      return true;
    }

    let code = '';
    for (let attempt = 0; attempt < 5; attempt += 1) {
      const candidate = makeCode();
      if (!(await getCampaignByCode(candidate))) {
        code = candidate;
        break;
      }
    }
    if (!code) {
      await sendHtml(chatId, 'Не удалось подобрать код кампании. Попробуйте ещё раз.');
      return true;
    }

    const campaign = await createCampaign({
      code,
      ...checked.value,
      rules: draft.rules,
      currency: 'RUB',
      deliveryMode: draft.deliveryMode ?? 'hybrid',
      logoFileId: draft.logoFileId ?? '',
      revealSanta: true,
      organizerId: Number(userId),
    });
    await clear(userId);
    await logAudit(campaign.id, userId, 'campaign.create', { code: campaign.code });
    await openCampaign(chatId, userId, campaign.id, null, 'Кампания опубликована!');
    return true;
  }

  return false;
}
