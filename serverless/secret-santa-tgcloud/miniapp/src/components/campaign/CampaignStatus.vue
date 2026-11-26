<script setup>
import { computed } from 'vue';

const props = defineProps({
  status: { type: String, default: '' },
});

const map = {
  recruiting: { label: 'Набор участников', tone: 'ok' },
  locked: { label: 'Набор закрыт', tone: 'warn' },
  drawn: { label: 'Пары собраны', tone: 'berry' },
  gifting: { label: 'Вручение', tone: 'warn' },
  completed: { label: 'Завершена', tone: 'ok' },
  cancelled: { label: 'Отменена', tone: 'berry' },
};

const view = computed(() => map[props.status] ?? { label: props.status, tone: 'neutral' });
</script>

<template>
  <span class="pill" :class="`pill--${view.tone}`">{{ view.label }}</span>
</template>
