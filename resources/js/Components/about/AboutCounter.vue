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
    <div class="mt-10 sm:mt-20 bg-primary-light shadow-sm">
        <div class="font-normal container mx-auto py-12 md:py-20 flex sm:justify-between sm:items-center flex-wrap gap-y-12">
            <div class="w-1/2 md:w-auto md:flex-1">
                <div class="flex items-center gap-2 mb-2 justify-center">
                    <Layout class="w-5 sm:w-8 h-5 sm:h-8 text-ternary-dark" />
                    <AnimatedNumber
                        :to="counters.projects"
                        :duration="1.2"
                        :format="format"
                        class="font-medium text-4xl text-secondary-dark"
                    />
                </div>
                <span class="block text-md text-ternary-dark text-center">
                    {{ pluralize(counters.projects, ['Проект', 'Проекта', 'Проектов']) }}
                </span>
            </div>

            <div class="w-1/2 md:w-auto md:flex-1">
                <div class="flex items-center gap-2 mb-2 justify-center">
                    <Clock class="w-5 sm:w-8 h-5 sm:h-8 text-ternary-dark" />
                    <AnimatedNumber
                        :to="counters.experience"
                        :duration="1"
                        :format="format"
                        class="font-medium text-4xl text-secondary-dark"
                    />
                </div>
                <span class="block text-md text-ternary-dark text-center">
                    {{ pluralize(counters.experience, ['Год опыта', 'Года опыта', 'Лет опыта']) }}
                </span>
            </div>

            <div class="w-1/2 md:w-auto md:flex-1">
                <div class="flex items-center gap-2 mb-2 justify-center">
                    <Coffee class="w-5 sm:w-8 h-5 sm:h-8 text-ternary-dark" />
                    <AnimatedNumber
                        :to="counters.cups"
                        :duration="1.3"
                        :format="format"
                        class="font-medium text-4xl text-secondary-dark"
                    />
                </div>
                <span class="block text-md text-ternary-dark text-center">
                    {{ pluralize(counters.cups, ['Чашка кофе', 'Чашки кофе', 'Чашек кофе']) }}
                </span>
            </div>

            <div class="w-1/2 md:w-auto md:flex-1">
                <div class="flex items-center gap-2 mb-2 justify-center">
                    <Globe class="w-5 sm:w-8 h-5 sm:h-8 text-ternary-dark" />
                    <AnimatedNumber
                        :to="counters.countries"
                        :duration="1.1"
                        :format="format"
                        class="font-medium text-4xl text-secondary-dark"
                    />
                </div>
                <span class="block text-md text-ternary-dark text-center">
                    {{ pluralize(counters.countries, ['Страна посещена', 'Страны посещено', 'Стран посещено']) }}
                </span>
            </div>
        </div>
    </div>
</template>