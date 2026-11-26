import { reactive, computed } from 'vue';
import { call, ApiError } from '../api/client.js';

export const state = reactive({
  boot: null,
  loading: true,
  error: '',
  toast: null,
  toastTimer: null,
});

let bootPromise = null;

export async function loadBoot(force = false) {
  if (state.boot && !force) return state.boot;
  if (bootPromise && !force) return bootPromise;
  state.loading = true;
  state.error = '';
  bootPromise = (async () => {
    try {
      state.boot = await call('bootstrap');
      return state.boot;
    } catch (error) {
      state.error =
        error instanceof ApiError && error.code === 'NO_TELEGRAM'
          ? 'Откройте приложение через Telegram.'
          : error.message || 'Не удалось загрузить приложение.';
      throw error;
    } finally {
      state.loading = false;
      bootPromise = null;
    }
  })();
  return bootPromise;
}

export async function refreshBoot() {
  return loadBoot(true);
}

export function toast(message) {
  state.toast = message;
  if (state.toastTimer) clearTimeout(state.toastTimer);
  state.toastTimer = setTimeout(() => {
    state.toast = null;
    state.toastTimer = null;
  }, 3200);
}

export function useSession() {
  return {
    state,
    boot: computed(() => state.boot),
    loading: computed(() => state.loading),
    error: computed(() => state.error),
    toast,
  };
}
