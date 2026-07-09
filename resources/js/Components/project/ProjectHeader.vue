<script setup>
import { Clock, Link, Tag } from 'lucide-vue-next';

defineProps({
    project: {
        type: Object,
        required: true,
    },
});
</script>

<template>
    <div>
        <!-- Project name -->
        <h1 class="font-display text-3xl sm:text-4xl lg:text-5xl font-bold text-text-primary mt-10 sm:mt-16 mb-6">
            <span v-if="project.name">{{ project.name }}</span>
            <div v-else class="h-5 bg-border-default rounded-full w-48 mb-4 animate-pulse"></div>
        </h1>

        <!-- Meta pills -->
        <div class="flex gap-3 flex-wrap" v-if="project.date">
            <div class="inline-flex items-center gap-2 rounded-full px-3.5 py-2 bg-warm-surface border border-border-default text-sm text-text-secondary">
                <Clock class="w-3.5 h-3.5 text-accent-terracotta" />
                <span>{{ project.date }}</span>
            </div>
            <div class="inline-flex items-center gap-2 rounded-full px-3.5 py-2 bg-warm-surface border border-border-default text-sm text-text-secondary" v-if="project.site">
                <Link class="w-3.5 h-3.5 text-accent-sage" />
                <a
                    :href="project.site"
                    target="_blank"
                    class="hover:text-accent-terracotta transition-colors"
                >{{ project.site }}</a>
            </div>
            <div class="inline-flex items-center gap-2 rounded-full px-3.5 py-2 bg-warm-surface border border-border-default text-sm text-text-secondary" v-if="project.current_version">
                <Tag class="w-3.5 h-3.5 text-accent-amber" />
                <span>Версия {{ project.current_version }}</span>
            </div>
        </div>
    </div>
</template>