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
            class="fixed right-8 bottom-5 p-2 bg-indigo-500 hover:bg-indigo-600 text-white transition duration-500 ease-in-out transform hover:-translate-y-1 hover:scale-110 shadow-lg z-50"
            style="border-radius: 50%; font-size: 22px; line-height: 22px;"
            title="Наверх"
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