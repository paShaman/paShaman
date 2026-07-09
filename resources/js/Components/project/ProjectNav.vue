<script setup>
import { Link } from '@inertiajs/vue3';
import { ArrowLeft, ArrowRight } from '@lucide/vue';

defineProps({
    prev: {
        type: Object,
        default: null,
    },
    next: {
        type: Object,
        default: null,
    },
});
</script>

<template>
    <div class="flex justify-between gap-4 mt-12 sm:mt-16 mb-8">
        <Link
            v-if="prev"
            :href="prev.link"
            class="group flex items-center gap-2.5 rounded-2xl bg-warm-surface shadow-card hover:shadow-card-hover hover:-translate-x-1 transition-all duration-300 px-4 py-3 sm:px-5 sm:py-3.5 cursor-pointer"
        >
            <ArrowLeft class="w-4 h-4 text-accent-terracotta group-hover:-translate-x-0.5 transition-transform shrink-0" />
            <span class="text-sm font-medium text-text-secondary group-hover:text-text-primary transition-colors text-left leading-snug">
                {{ prev.name }}
            </span>
        </Link>

        <!-- Spacer when no prev -->
        <span v-else />

        <Link
            v-if="next"
            :href="next.link"
            class="group flex items-center gap-2.5 rounded-2xl bg-warm-surface shadow-card hover:shadow-card-hover hover:translate-x-1 transition-all duration-300 px-4 py-3 sm:px-5 sm:py-3.5 cursor-pointer text-right"
        >
            <span class="text-sm font-medium text-text-secondary group-hover:text-text-primary transition-colors leading-snug">
                {{ next.name }}
            </span>
            <ArrowRight class="w-4 h-4 text-accent-terracotta group-hover:translate-x-0.5 transition-transform shrink-0" />
        </Link>

        <!-- Spacer when no next -->
        <span v-else />
    </div>
</template>