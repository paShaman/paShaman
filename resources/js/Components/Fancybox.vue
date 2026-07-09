<script setup>
import { onMounted, onUnmounted, ref } from 'vue';
import { Fancybox as NativeFancybox } from '@fancyapps/ui';
import '@fancyapps/ui/dist/fancybox/fancybox.css';

const container = ref(null);

onMounted(() => {
    // Fancybox auto-binds to [data-fancybox] attributes
    NativeFancybox.bind('[data-fancybox]');
});

onUnmounted(() => {
    NativeFancybox.destroy();
});
</script>

<template>
    <div ref="container">
        <slot />
    </div>
</template>