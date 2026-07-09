<script setup>
import { ref, onMounted } from 'vue';

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

onMounted(() => {
    const startTime = performance.now();
    const from = 0;
    const to = props.to;
    const duration = props.duration * 1000;

    function step(time) {
        const elapsed = time - startTime;
        const progress = Math.min(elapsed / duration, 1);
        // Easing: linear
        current.value = Math.round(from + (to - from) * progress);

        if (progress < 1) {
            requestAnimationFrame(step);
        }
    }

    requestAnimationFrame(step);
});
</script>

<template>
    <span>{{ format(current) }}</span>
</template>