<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { Link } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';

const props = defineProps({
    isProject: {
        type: Boolean,
        default: false,
    },
});

const scrolled = ref(false);

// Гистерезис: компактный вид включается при 48px, обратно — только ниже 8px,
// чтобы логотип не «колбасило» на пороге при небольшой прокрутке.
function onScroll() {
    const y = window.scrollY;
    if (scrolled.value) {
        if (y < 8) {
            scrolled.value = false;
        }
    } else if (y > 48) {
        scrolled.value = true;
    }
}

// Единый круг за логотипом: на детальной странице при скролле меняет размеры,
// на главной — появляется по opacity. В скролле круг крупнее, но торчит
// не сильнее (выше через -translate-y-[85%]).
const circleClass = computed(() => {
    if (scrolled.value) {
        return '-translate-y-[85%] w-52 sm:w-64 opacity-100';
    }
    if (props.isProject) {
        return '-translate-y-4/5 w-87.5 sm:w-150 opacity-100';
    }
    return '-translate-y-[85%] w-52 sm:w-64 opacity-0';
});

onMounted(() => {
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();
});

onUnmounted(() => {
    window.removeEventListener('scroll', onScroll);
});
</script>

<template>
    <header class="sticky top-0 inset-x-0 z-50">
        <!-- Protruding circle behind the logo (morphs its size on scroll) -->
        <div
            class="absolute top-1/2 left-1/2 -translate-x-1/2 aspect-square rounded-full bg-[#F7ECE6] animate-blob pointer-events-none transition-all duration-500"
            :class="circleClass"
            aria-hidden="true"
        />

        <div class="relative container mx-auto px-4 sm:px-6">
            <div
                class="relative flex items-center justify-center transition-all duration-300"
                :class="scrolled ? 'py-1.5 sm:py-2' : 'py-8 sm:py-10'"
            >
                <Link
                    v-if="isProject"
                    href="/"
                    class="group hidden sm:inline-flex absolute right-0 items-center gap-1.5 text-sm font-medium text-text-secondary hover:text-accent-terracotta transition-colors cursor-pointer"
                >
                    <span>Все проекты</span>
                    <ArrowLeft class="w-4 h-4 rotate-180 group-hover:translate-x-0.5 transition-transform" />
                </Link>

                <Link href="/" class="relative inline-block">
                    <span
                        class="font-display font-bold text-text-primary tracking-tight hover:text-accent-terracotta transition-all duration-300"
                        :class="scrolled ? 'text-lg sm:text-xl' : 'text-4xl sm:text-5xl'"
                    >
                        paShaman<span class="opacity-50 tracking-[0.03em]">.dev</span>
                    </span>
                </Link>
            </div>
        </div>
    </header>
</template>
