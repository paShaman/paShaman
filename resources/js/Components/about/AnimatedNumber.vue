<script setup>
import { ref, watch, onMounted, onBeforeUnmount } from 'vue';

const props = defineProps({
    to: {
        type: Number,
        required: true,
    },
    duration: {
        type: Number,
        default: 1,
    },
    format: {
        type: Function,
        default: (n) => String(n),
    },
});

const current = ref(0);
let rafId = null;

function animateTo(to) {
    if (rafId) cancelAnimationFrame(rafId);

    const from = current.value;
    const startTime = performance.now();
    const duration = props.duration * 1000;

    function step(time) {
        const elapsed = time - startTime;
        const progress = Math.min(elapsed / duration, 1);
        // Easing: linear
        current.value = Math.round(from + (to - from) * progress);

        if (progress < 1) {
            rafId = requestAnimationFrame(step);
        } else {
            rafId = null;
        }
    }

    rafId = requestAnimationFrame(step);
}

watch(() => props.to, (to) => {
    animateTo(to);
});

onMounted(() => {
    animateTo(props.to);
});

onBeforeUnmount(() => {
    if (rafId) cancelAnimationFrame(rafId);
});
</script>

<template>
    <span>{{ format(current) }}</span>
</template>