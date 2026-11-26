import { onMounted, onUnmounted, watch } from 'vue';
import { bindBackButton } from '../telegram/webapp.js';

export function useBackButton(show, handler) {
  let cleanup = null;
  function update() {
    if (cleanup) cleanup();
    cleanup = bindBackButton(Boolean(show()), handler);
  }
  onMounted(update);
  watch(show, update);
  onUnmounted(() => {
    if (cleanup) cleanup();
  });
}
