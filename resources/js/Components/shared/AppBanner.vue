<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { ArrowDown, Send } from '@lucide/vue';
import { lenisScrollTo } from '@/composables/useLenis';

const page = usePage();

const headlineWords = ['Павел', 'Никитин'];

const subtitleWords = [
    ...['Backend', '&', 'Frontend', 'разработчик.', 'Создаю'].map((text) => ({ text })),
    { text: 'веб-проекты', gradient: true },
];

const socials = computed(() => page.props.site?.social || []);
const contactUrl = computed(() => {
    const telegram = socials.value.find((s) => s.icon === 'send');
    return telegram?.url || socials.value[0]?.url || '#';
});

function scrollToProjects() {
    lenisScrollTo('#projects', { offset: -96 });
}
</script>

<template>
    <section class="relative flex flex-col items-center justify-center text-center pt-12 sm:pt-16 pb-16 sm:pb-24">
        <!-- Background blob -->
        <div
            class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-3/5 w-115 sm:w-180 aspect-square rounded-full bg-[#F7ECE6] animate-blob pointer-events-none"
            aria-hidden="true"
        />

        <!-- Decorative gradient orbs -->
        <div
            class="absolute -left-16 sm:-left-24 top-1/3 w-56 sm:w-72 aspect-square rounded-full bg-accent-sage-light/50 blur-3xl animate-blob pointer-events-none"
            aria-hidden="true"
        />
        <div
            class="absolute -right-16 sm:-right-24 top-2/3 w-64 sm:w-80 aspect-square rounded-full bg-accent-amber-light/60 blur-3xl animate-blob pointer-events-none"
            style="animation-delay: -6s"
            aria-hidden="true"
        />

        <!-- Content -->
        <div class="relative container mx-auto px-4 sm:px-6">
            <h1 class="font-display text-4xl sm:text-6xl lg:text-7xl font-bold text-text-primary mb-4 sm:mb-6 tracking-tight">
                <span
                    v-for="(word, i) in headlineWords"
                    :key="`head-${i}`"
                    class="hero-word inline-block"
                    :style="{ '--hero-delay': `${i * 100}ms` }"
                >
                    {{ word }}{{ i < headlineWords.length - 1 ? '\u00A0' : '' }}
                </span>
            </h1>

            <p class="text-lg sm:text-xl lg:text-2xl text-text-secondary leading-relaxed max-w-2xl mx-auto">
                <span
                    v-for="(word, i) in subtitleWords"
                    :key="`sub-${i}`"
                    class="hero-word inline-block"
                    :style="{ '--hero-delay': `${(headlineWords.length + i) * 100}ms` }"
                >
                    <span
                        v-if="word.gradient"
                        class="bg-linear-to-r from-accent-terracotta via-accent-amber to-accent-sage bg-clip-text text-transparent font-semibold"
                    >
                        {{ word.text }}
                    </span>
                    <template v-else>{{ word.text }}</template>{{ i < subtitleWords.length - 1 ? '\u00A0' : '' }}
                </span>
            </p>

            <!-- CTA buttons -->
            <div
                class="hero-stagger mt-8 sm:mt-10 flex flex-wrap items-center justify-center gap-3 sm:gap-4"
                :style="{ '--hero-delay': `${(headlineWords.length + subtitleWords.length) * 100}ms` }"
            >
                <a
                    href="#projects"
                    @click.prevent="scrollToProjects"
                    class="inline-flex items-center gap-2 rounded-full bg-accent-terracotta px-6 sm:px-7 py-3 sm:py-3.5 text-sm sm:text-base font-medium text-white shadow-card hover:shadow-card-hover hover:-translate-y-0.5 hover:bg-accent-terracotta/90 transition-all duration-300 cursor-pointer"
                >
                    Смотреть проекты
                    <ArrowDown class="w-4 h-4" />
                </a>
                <a
                    :href="contactUrl"
                    target="_blank"
                    rel="noopener"
                    class="inline-flex items-center gap-2 rounded-full bg-warm-surface border border-border-default px-6 sm:px-7 py-3 sm:py-3.5 text-sm sm:text-base font-medium text-text-secondary hover:border-accent-terracotta hover:text-accent-terracotta hover:-translate-y-0.5 transition-all duration-300 cursor-pointer"
                >
                    <Send class="w-4 h-4" />
                    Связаться
                </a>
            </div>
        </div>
    </section>
</template>
