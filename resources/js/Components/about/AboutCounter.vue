<script setup>
import { Layout, Clock, Coffee, Globe } from 'lucide-vue-next';
import AnimatedNumber from '@/Components/about/AnimatedNumber.vue';

defineProps({
    counters: {
        type: Object,
        required: true,
    },
});

function pluralize(count, words) {
    const cases = [2, 0, 1, 1, 1, 2];
    return words[(count % 100 > 4 && count % 100 < 20) ? 2 : cases[Math.min(count % 10, 5)]];
}

function format(num) {
    return new Intl.NumberFormat('ru-RU').format(Number.parseInt(num).toFixed(0));
}
</script>

<template>
    <div class="relative py-6 sm:py-0">
        <!-- Floating card -->
        <div class="container mx-auto px-4 sm:px-6">
            <div class="relative z-10 -mt-10 sm:-mt-16 bg-warm-surface shadow-float rounded-2xl sm:rounded-3xl px-4 py-10 sm:px-12 sm:py-14">
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-8 sm:gap-6">
                    <!-- Projects -->
                    <div class="flex flex-col items-center text-center">
                        <div class="flex items-center justify-center w-12 h-12 rounded-full bg-accent-terracotta-light/50 mb-3">
                            <Layout class="w-6 h-6 text-accent-terracotta" />
                        </div>
                        <AnimatedNumber
                            :to="counters.projects"
                            :duration="1.2"
                            :format="format"
                            class="font-sans font-bold text-3xl sm:text-4xl text-text-primary"
                        />
                        <span class="block text-sm text-text-secondary mt-1">
                            {{ pluralize(counters.projects, ['Проект', 'Проекта', 'Проектов']) }}
                        </span>
                    </div>

                    <!-- Experience -->
                    <div class="flex flex-col items-center text-center">
                        <div class="flex items-center justify-center w-12 h-12 rounded-full bg-accent-sage-light/50 mb-3">
                            <Clock class="w-6 h-6 text-accent-sage" />
                        </div>
                        <AnimatedNumber
                            :to="counters.experience"
                            :duration="1"
                            :format="format"
                            class="font-sans font-bold text-3xl sm:text-4xl text-text-primary"
                        />
                        <span class="block text-sm text-text-secondary mt-1">
                            {{ pluralize(counters.experience, ['Год опыта', 'Года опыта', 'Лет опыта']) }}
                        </span>
                    </div>

                    <!-- Coffee -->
                    <div class="flex flex-col items-center text-center">
                        <div class="flex items-center justify-center w-12 h-12 rounded-full bg-accent-amber-light/50 mb-3">
                            <Coffee class="w-6 h-6 text-accent-amber" />
                        </div>
                        <AnimatedNumber
                            :to="counters.cups"
                            :duration="1.3"
                            :format="format"
                            class="font-sans font-bold text-3xl sm:text-4xl text-text-primary"
                        />
                        <span class="block text-sm text-text-secondary mt-1">
                            {{ pluralize(counters.cups, ['Чашка кофе', 'Чашки кофе', 'Чашек кофе']) }}
                        </span>
                    </div>

                    <!-- Countries -->
                    <div class="flex flex-col items-center text-center">
                        <div class="flex items-center justify-center w-12 h-12 rounded-full bg-accent-terracotta-light/50 mb-3">
                            <Globe class="w-6 h-6 text-accent-terracotta" />
                        </div>
                        <AnimatedNumber
                            :to="counters.countries"
                            :duration="1.1"
                            :format="format"
                            class="font-sans font-bold text-3xl sm:text-4xl text-text-primary"
                        />
                        <span class="block text-sm text-text-secondary mt-1">
                            {{ pluralize(counters.countries, ['Страна посещена', 'Страны посещено', 'Стран посещено']) }}
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Background blob behind floating card -->
        <div
            class="absolute top-0 right-0 w-64 h-64 rounded-full bg-accent-sage/8 animate-blob pointer-events-none -z-0"
            aria-hidden="true"
        />
    </div>
</template>