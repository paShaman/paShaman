<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { ChevronUp } from '@lucide/vue';

const visible = ref(false);

function onScroll() {
    visible.value = window.scrollY > 400;
}

function scrollToTop() {
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

onMounted(() => {
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();
});

onUnmounted(() => {
    window.removeEventListener('scroll', onScroll);
});
</script>

<template>
    <button
        v-if="visible"
        aria-label="Наверх"
        class="fixed bottom-6 right-6 z-50 flex size-12 items-center justify-center rounded-full bg-accent-terracotta text-white shadow-lg hover:shadow-xl hover:-translate-y-1 transition-all duration-300 cursor-pointer"
        @click="scrollToTop"
    >
        <ChevronUp class="size-5" />
    </button>
</template>