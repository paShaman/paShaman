<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    project: {
        type: Object,
        required: true,
    },
});
</script>

<template>
    <!-- Active project -->
    <Link
        v-if="project.link !== '#'"
        :href="project.link"
        class="group block rounded-2xl sm:rounded-3xl overflow-hidden bg-warm-surface shadow-card hover:shadow-card-hover hover:-translate-y-1 transition-all duration-300"
        aria-label="Single Project"
    >
        <div class="overflow-hidden">
            <img
                :src="project.image"
                class="w-full aspect-[4/3] object-cover group-hover:scale-105 transition-transform duration-500"
                alt=""
                loading="lazy"
            />
        </div>
        <div class="px-5 py-5">
            <div class="flex items-center justify-between gap-3 mb-2">
                <h3 class="text-lg font-semibold text-text-primary flex-1 text-left truncate">
                    {{ project.name }}
                </h3>
                <span class="text-sm text-text-muted shrink-0">
                    {{ project.date }}
                </span>
            </div>
            <div class="flex gap-1.5 flex-wrap">
                <span
                    v-for="tag in project.tags"
                    :key="tag"
                    class="inline-block rounded-full px-2.5 py-0.5 text-xs font-medium bg-accent-terracotta-light/30 text-text-secondary"
                >
                    {{ tag }}
                </span>
            </div>
        </div>
    </Link>

    <!-- Hidden project (link === '#') -->
    <span
        v-else
        class="group block rounded-2xl sm:rounded-3xl overflow-hidden bg-warm-surface shadow-card hover:shadow-card-hover hover:-translate-y-1 transition-all duration-300"
        aria-label="Hidden Project"
    >
        <div class="overflow-hidden">
            <img
                :src="project.image"
                class="w-full aspect-[4/3] object-cover group-hover:scale-105 transition-transform duration-500"
                alt=""
                loading="lazy"
            />
        </div>
        <div class="px-5 py-5">
            <div class="flex items-center justify-between gap-3 mb-2">
                <h3 class="text-lg font-semibold text-text-primary flex-1 text-left truncate">
                    {{ project.name }}
                </h3>
                <span class="text-sm text-text-muted shrink-0">
                    {{ project.date }}
                </span>
            </div>
            <div class="flex gap-1.5 flex-wrap">
                <span
                    v-for="tag in project.tags"
                    :key="tag"
                    class="inline-block rounded-full px-2.5 py-0.5 text-xs font-medium bg-accent-terracotta-light/30 text-text-secondary"
                >
                    {{ tag }}
                </span>
            </div>
        </div>
    </span>
</template>