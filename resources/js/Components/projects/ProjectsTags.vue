<script setup>
import { X } from 'lucide-vue-next';

const props = defineProps({
    tags: {
        type: Array,
        default: () => [],
    },
    cnt: {
        type: Number,
        default: 0,
    },
});

const emit = defineEmits(['select-tags']);

function toggleTag(tag) {
    tag.selected = !tag.selected;
    emit('select-tags', props.tags.filter((el) => el.selected));
}

function selectAll() {
    props.tags.forEach((t) => (t.selected = false));
    emit('select-tags', []);
}

function isAllSelected() {
    return props.tags.every((t) => !t.selected);
}
</script>

<template>
    <div class="flex gap-2 flex-wrap items-center">
        <!-- All button -->
        <button
            @click="selectAll()"
            class="inline-flex items-center gap-1.5 rounded-full px-3.5 py-1.5 text-sm font-medium transition-all duration-300 border cursor-pointer"
            :class="isAllSelected()
                ? 'bg-accent-terracotta text-white border-accent-terracotta'
                : 'bg-warm-surface text-text-secondary border-border-default hover:border-accent-terracotta hover:text-accent-terracotta'"
        >
            <span>Все</span>
            <span class="tabular-nums opacity-70">{{ cnt }}</span>
        </button>

        <!-- Tag pills -->
        <button
            v-for="tag in tags"
            :key="tag.name"
            @click="toggleTag(tag)"
            class="inline-flex items-center gap-1.5 rounded-full px-3.5 py-1.5 text-sm font-medium transition-all duration-300 border cursor-pointer"
            :class="tag.selected
                ? 'bg-accent-terracotta text-white border-accent-terracotta'
                : 'bg-warm-surface text-text-secondary border-border-default hover:border-accent-sage hover:text-accent-sage'"
        >
            <span>{{ tag.name }}</span>
            <span class="tabular-nums opacity-70">{{ tag.count }}</span>
            <X v-if="tag.selected" class="w-3 h-3 shrink-0" />
        </button>
    </div>
</template>