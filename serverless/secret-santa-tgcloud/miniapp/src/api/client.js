export class ApiError extends Error {
  constructor(message, code = 'UNKNOWN', parameters = {}) {
    super(message);
    this.name = 'ApiError';
    this.code = code;
    this.parameters = parameters;
  }
}

export function telegramApp() {
  return typeof window !== 'undefined' ? window.Telegram?.WebApp ?? null : null;
}

export function isTelegram() {
  return Boolean(telegramApp()?.initData);
}

let mockLoader = null;

async function mockCall(name, input) {
  if (!mockLoader) {
    const module = await import('./mock.js');
    mockLoader = module.mockCall;
  }
  return mockLoader(name, input);
}

export function call(name, input = {}) {
  const tg = telegramApp();
  const hasInitData = Boolean(tg?.initData);
  if (!tg?.Serverless?.call || (import.meta.env.DEV && !hasInitData)) {
    if (import.meta.env.DEV) return mockCall(name, input);
    return Promise.reject(
      new ApiError('Откройте приложение через Telegram.', 'NO_TELEGRAM'),
    );
  }
  return new Promise((resolve, reject) => {
    try {
      tg.Serverless.call(name, input, (error, result) => {
        if (error) {
          reject(
            new ApiError(
              error.message || 'Не удалось выполнить запрос.',
              error.parameters?.code || error.type || 'UNKNOWN',
              error.parameters || {},
            ),
          );
          return;
        }
        resolve(result);
      });
    } catch (error) {
      reject(new ApiError(error?.message || 'Ошибка приложения.', 'CALL_FAILED'));
    }
  });
}

export function isMock() {
  return import.meta.env.DEV && !isTelegram();
}