<script setup>
import { X, ChevronDown } from 'lucide-vue-next';

const props = defineProps({
    tags: {
        type: Array,
        default: () => [],
    },
    cnt: {
        type: Number,
        default: 0,
    },
    show: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['select-tags', 'toggle']);

function calcTagClass(tag) {
    if (tag.selected) {
        return 'bg-accent-terracotta text-white border-accent-terracotta';
    }
    if (tag.count >= 100) {
        return 'bg-violet-100 text-violet-700 border-violet-300 hover:border-violet-500';
    }
    if (tag.count >= 30) {
        return 'bg-accent-sage-light/60 text-accent-sage/90 border-accent-sage-light hover:border-accent-sage';
    }
    if (tag.count >= 10) {
        return 'bg-accent-amber-light/60 text-amber-700 border-amber-300 hover:border-amber-500';
    }
    if (tag.count >= 5) {
        return 'bg-cyan-100 text-cyan-700 border-cyan-200 hover:border-cyan-400';
    }
    return 'bg-warm-surface text-text-muted border-border-default hover:border-text-muted/50';
}

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
    <div>
        <!-- Toggle + All row -->
        <div class="flex gap-2 flex-wrap items-center justify-center">
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

            <button
                @click="emit('toggle')"
                class="inline-flex items-center gap-1.5 rounded-full px-3.5 py-1.5 text-sm font-medium transition-all duration-300 border cursor-pointer bg-warm-surface text-text-secondary border-border-default hover:border-accent-terracotta hover:text-accent-terracotta"
            >
                Теги
                <ChevronDown class="w-4 h-4 transition-transform duration-300" :class="{ 'rotate-180': show }" />
            </button>
        </div>

        <!-- Tags panel -->
        <div class="tags-outer mt-3" :class="{ active: show }">
            <div>
                <div class="flex gap-2 flex-wrap justify-center">
                    <button
                        v-for="tag in tags"
                        :key="tag.name"
                        @click="toggleTag(tag)"
                        class="inline-flex items-center gap-1.5 rounded-full px-3.5 py-1.5 text-sm font-medium transition-all duration-300 border cursor-pointer"
                        :class="calcTagClass(tag)"
                    >
                        <span>{{ tag.name }}</span>
                        <span class="tabular-nums opacity-70">{{ tag.count }}</span>
                        <X v-if="tag.selected" class="w-3 h-3 shrink-0" />
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.tags-outer {
    display: grid;
    grid-template-rows: 0fr;
    transition: all 0.3s ease;
}
.tags-outer > div {
    overflow: hidden;
}
.tags-outer > div > div {
    padding-block: 0.5rem;
}
.tags-outer.active {
    grid-template-rows: 1fr;
}
</style>