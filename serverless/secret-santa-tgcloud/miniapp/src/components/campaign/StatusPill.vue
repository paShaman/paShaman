<script setup>
const props = defineProps({
  label: { type: String, required: true },
  tone: { type: String, default: 'neutral' },
});
</script>

<template>
  <span class="pill" :class="`pill--${props.tone}`">{{ props.label }}</span>
</template>
