<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { ChevronUp } from 'lucide-vue-next';

const visible = ref(false);

function onScroll() {
    visible.value = window.scrollY > 500;
}

function scrollToTop() {
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

onMounted(() => {
    window.addEventListener('scroll', onScroll);
});

onUnmounted(() => {
    window.removeEventListener('scroll', onScroll);
});
</script>

<template>
    <Transition name="fade">
        <button
            v-if="visible"
            @click="scrollToTop"
            class="fixed right-6 bottom-6 p-3 bg-accent-terracotta hover:bg-accent-terracotta/90 text-white rounded-full shadow-lg hover:shadow-xl transition-all duration-300 hover:-translate-y-1 z-50 cursor-pointer"
            title="Наверх"
            aria-label="Наверх"
        >
            <ChevronUp class="w-5 h-5" />
        </button>
    </Transition>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.3s ease;
}
.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}
</style>