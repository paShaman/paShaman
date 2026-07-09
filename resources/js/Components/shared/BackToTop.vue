<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { ChevronUp } from 'lucide-vue-next';

const visible = ref(false);

function onScroll() {
    visible.value = window.scrollY > 400;
}

function scrollToTop() {
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

onMounted(() => {
    window.addEventListener('scroll', onScroll, { passive: true });
});

onUnmounted(() => {
    window.removeEventListener('scroll', onScroll);
});
</script>

<template>
    <Transition name="backtotop">
        <button
            v-show="visible"
            aria-label="Наверх"
            class="fixed bottom-6 right-6 z-50 flex items-center justify-center w-12 h-12 rounded-full bg-accent-terracotta text-white shadow-lg hover:shadow-xl hover:-translate-y-1 transition-all duration-300 cursor-pointer"
            @click="scrollToTop"
        >
            <ChevronUp class="w-5 h-5" />
        </button>
    </Transition>
</template>

<style scoped>
.backtotop-enter-active,
.backtotop-leave-active {
    transition: opacity 0.3s ease, transform 0.3s ease;
}
.backtotop-enter-from,
.backtotop-leave-to {
    opacity: 0;
    transform: translateY(12px);
}
</style>