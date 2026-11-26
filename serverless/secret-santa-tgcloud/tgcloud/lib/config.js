/**
 * Настройки проекта.
 *
 * ADMIN_IDS — Telegram ID супер-админов. Свой ID можно узнать командой /id.
 * BOT_USERNAME и APP_URL заполняются после создания бота и первого push:
 * без BOT_USERNAME бот показывает только код кампании, без APP_URL — не
 * показывает кнопку Mini App.
 */
export const ADMIN_IDS = ['115462629'];

export const BOT_USERNAME = 'pod_elochku_bot';
export const APP_URL = 'https://app8782869988.tgcloud.ai/';

export const TZ = 'Europe/Moscow';
export const TZ_OFFSET = '+03:00';

export const SESSION_TTL = 3600;
export const MAX_PARTICIPANTS = 500;
export const MAX_BROADCAST = 100;
export const MAX_WISHLIST_ITEMS = 50;
export const MAX_BROADCAST_TEXT = 3800;
export const MAX_CHAT_TEXT = 1000;
export const MAX_PHOTO_BYTES = 320 * 1024;
export const PHOTO_MAX_DIMENSION = 1280;
export const MAX_ITEM_TITLE = 120;
export const MAX_ITEM_COMMENT = 500;
export const MAX_URL_LENGTH = 2048;
export const MAX_CAMPAIGN_DESCRIPTION = 1000;
export const MAX_RULES = 3000;
export const MIN_PARTICIPANTS = 3;
export const MAX_PRICE = 1000000;

export const DEFAULT_RULES = `1. Подарок выбирается в рамках бюджета кампании.
2. Получателя не раскрываем до завершения кампании.
3. Вишлист — подсказка, а не заказ: подарок может быть сюрпризом.
4. Если не получается участвовать, сообщите организатору до жеребьёвки.
5. Уважайте друг друга: без обидных и опасных подарков.
6. Личные данные (адрес, телефон) видны только вашему Санте и удаляются после завершения кампании.`;

export const BROADCAST_PRESETS = [
  {
    key: 'wishlist',
    label: 'Заполните вишлист',
    text: 'Напоминаю: добавьте хотя бы одно желание в вишлист и подтвердите участие. Без этого я не смогу включить вас в жеребьёвку.',
  },
  {
    key: 'soon',
    label: 'Скоро жеребьёвка',
    text: 'Совсем скоро закрываем набор и собираем пары. Проверьте вишлист и подтвердите участие, если ещё не сделали этого.',
  },
  {
    key: 'gift',
    label: 'Подарок отправлен?',
    text: 'Если подарок уже отправлен или вручён, отметьте это в приложении — получателю будет приятно знать, что подарок в пути.',
  },
  {
    key: 'finish',
    label: 'Кампания завершается',
    text: 'Кампания подходит к концу. Отметьте получение подарка и загляните в приложение, чтобы завершить историю.',
  },
];
