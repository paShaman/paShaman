<script setup>
import { watch, onUnmounted } from 'vue';
import { setClosingConfirmation } from '../../telegram/webapp.js';

const props = defineProps({
  open: { type: Boolean, default: false },
  title: { type: String, default: '' },
});
const emit = defineEmits(['close']);

function onKey(event) {
  if (event.key === 'Escape' && props.open) emit('close');
}

watch(
  () => props.open,
  (open) => {
    if (typeof document === 'undefined') return;
    document.body.style.overflow = open ? 'hidden' : '';
    setClosingConfirmation(open);
    if (open) document.addEventListener('keydown', onKey);
    else document.removeEventListener('keydown', onKey);
  },
);

onUnmounted(() => {
  document.body.style.overflow = '';
  document.removeEventListener('keydown', onKey);
  setClosingConfirmation(false);
});
</script>

<template>
  <Teleport to="body">
    <Transition name="sheet">
      <div v-if="open" class="sheet" role="dialog" aria-modal="true" :aria-label="title">
        <button class="sheet__backdrop" type="button" aria-label="Закрыть" @click="emit('close')" />
        <div class="sheet__panel">
          <div class="sheet__head">
            <h2 class="h2">{{ title }}</h2>
            <button class="icon-btn" type="button" aria-label="Закрыть" @click="emit('close')">
              ×
            </button>
          </div>
          <div class="sheet__body">
            <slot />
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.sheet {
  position: fixed;
  inset: 0;
  z-index: 80;
  display: flex;
  align-items: flex-end;
  justify-content: center;
}
.sheet__backdrop {
  position: absolute;
  inset: 0;
  border: 0;
  padding: 0;
  background: rgba(30, 40, 36, 0.45);
  backdrop-filter: blur(3px);
}
.sheet__panel {
  position: relative;
  width: min(100%, 560px);
  max-height: 92dvh;
  overflow: auto;
  background: var(--paper);
  border-radius: 26px 26px 0 0;
  padding: 18px 18px calc(18px + var(--safe-bottom));
  border: 1px solid var(--line);
  box-shadow: 0 -20px 60px -30px var(--shadow);
}
.sheet__head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 14px;
}
.sheet__body {
  display: flex;
  flex-direction: column;
  gap: 14px;
}
.sheet-enter-active,
.sheet-leave-active {
  transition: opacity 220ms cubic-bezier(0.22, 1, 0.36, 1);
}
.sheet-enter-active .sheet__panel,
.sheet-leave-active .sheet__panel {
  transition: transform 260ms cubic-bezier(0.22, 1, 0.36, 1);
}
.sheet-enter-from,
.sheet-leave-to {
  opacity: 0;
}
.sheet-enter-from .sheet__panel,
.sheet-leave-to .sheet__panel {
  transform: translateY(28px);
}
@media (prefers-reduced-motion: reduce) {
  .sheet-enter-active,
  .sheet-leave-active,
  .sheet-enter-active .sheet__panel,
  .sheet-leave-active .sheet__panel {
    transition: none;
  }
}
</style>
