import { telegramApp } from '../api/client.js';

export function initTelegram() {
  const tg = telegramApp();
  if (!tg) return null;
  try {
    tg.ready?.();
    tg.expand?.();
    applyTheme();
    tg.onEvent?.('themeChanged', applyTheme);
    tg.onEvent?.('viewportChanged', () => applyTheme());
    if (tg.setHeaderColor) tg.setHeaderColor(themeColor());
    if (tg.setBackgroundColor) tg.setBackgroundColor(themeColor());
    if (tg.setBottomBarColor) tg.setBottomBarColor(themeColor());
  } catch {
    return tg;
  }
  return tg;
}

export function themeColor() {
  return document.documentElement.dataset.theme === 'dark' ? '#0F1A17' : '#F6EEE2';
}

export function applyTheme() {
  const tg = telegramApp();
  const colorScheme = tg?.colorScheme;
  const prefersDark =
    typeof window !== 'undefined' &&
    window.matchMedia?.('(prefers-color-scheme: dark)')?.matches;
  const dark = colorScheme ? colorScheme === 'dark' : Boolean(prefersDark);
  document.documentElement.dataset.theme = dark ? 'dark' : 'light';
  const meta = document.querySelector('meta[name="theme-color"]');
  if (meta) meta.setAttribute('content', themeColor());
  try {
    if (tg?.setHeaderColor) tg.setHeaderColor(themeColor());
    if (tg?.setBackgroundColor) tg.setBackgroundColor(themeColor());
    if (tg?.setBottomBarColor) tg.setBottomBarColor(themeColor());
  } catch {
    // старые клиенты Telegram
  }
}

export function startParam() {
  return telegramApp()?.initDataUnsafe?.start_param ?? null;
}

export function telegramUser() {
  return telegramApp()?.initDataUnsafe?.user ?? null;
}

export function haptic(type = 'light') {
  const tg = telegramApp();
  try {
    if (tg?.HapticFeedback) tg.HapticFeedback.impactOccurred(type);
  } catch {
    // haptic недоступен
  }
}

export function openLink(url) {
  if (!url) return;
  const tg = telegramApp();
  try {
    if (tg?.openLink) {
      tg.openLink(url);
      return;
    }
  } catch {
    // fallthrough
  }
  window.open(url, '_blank', 'noopener');
}

export function shareLink(url, text = '') {
  if (!url) return false;
  const shareUrl = `https://t.me/share/url?url=${encodeURIComponent(url)}${
    text ? `&text=${encodeURIComponent(text)}` : ''
  }`;
  const tg = telegramApp();
  try {
    if (tg?.openTelegramLink) {
      tg.openTelegramLink(shareUrl);
      return true;
    }
  } catch {
    // fallthrough
  }
  window.open(shareUrl, '_blank', 'noopener');
  return true;
}

export function bindBackButton(show, handler) {
  const tg = telegramApp();
  if (!tg?.BackButton) return () => {};
  try {
    if (show) {
      tg.BackButton.show();
      tg.BackButton.onClick(handler);
    } else {
      tg.BackButton.hide();
      tg.BackButton.offClick(handler);
    }
  } catch {
    return () => {};
  }
  return () => {
    try {
      tg.BackButton.offClick(handler);
    } catch {
      // ignore
    }
  };
}

export function setClosingConfirmation(enabled) {
  const tg = telegramApp();
  try {
    if (enabled) tg?.enableClosingConfirmation?.();
    else tg?.disableClosingConfirmation?.();
  } catch {
    // ignore
  }
}
