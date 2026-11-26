<script setup>
import { computed } from 'vue';
import { RouterLink } from 'vue-router';
import AppIcon from './AppIcon.vue';
import { pluralRu } from '../../utils/plural.js';

const props = defineProps({
  to: { type: String, required: true },
  label: { type: String, default: 'Чат' },
  unread: { type: Number, default: 0 },
  block: { type: Boolean, default: false },
});

const badge = computed(() => (props.unread > 99 ? '99+' : String(props.unread)));
const aria = computed(() => {
  if (!props.unread) return props.label;
  const word = pluralRu(props.unread, 'новое сообщение', 'новых сообщения', 'новых сообщений');
  return `${props.label}: ${props.unread} ${word}`;
});
</script>

<template>
  <RouterLink
    :to="to"
    class="btn btn--secondary chat-cta"
    :class="{ 'chat-cta--unread': unread > 0, 'btn--block': block }"
    :aria-label="aria"
  >
    <AppIcon name="chat" :size="18" :stroke-width="1.9" />
    <span>{{ label }}</span>
    <span v-if="unread > 0" class="chat-cta__badge" aria-hidden="true">{{ badge }}</span>
  </RouterLink>
</template>

<style scoped>
.chat-cta {
  position: relative;
}
.chat-cta--unread {
  border-color: var(--berry-600);
}
.chat-cta__badge {
  min-width: 20px;
  height: 20px;
  padding: 0 6px;
  border-radius: 999px;
  background: var(--berry-600);
  color: #fff8f0;
  font-size: 12px;
  font-weight: 800;
  line-height: 1;
  display: inline-grid;
  place-items: center;
  margin-left: auto;
}
</style>
