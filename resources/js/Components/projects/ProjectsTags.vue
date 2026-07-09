<script setup>
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

function calcTagClass(tag) {
    if (tag.selected) {
        return 'bg-rose-600 text-rose-200 border-rose-900';
    }
    if (tag.count >= 100) {
        return 'bg-violet-300 text-violet-700 hover:border-violet-700 border-violet-400';
    }
    if (tag.count >= 30) {
        return 'bg-emerald-300 text-emerald-700 hover:border-emerald-700 border-emerald-400';
    }
    if (tag.count >= 10) {
        return 'bg-amber-300 text-amber-700 hover:border-amber-700 border-amber-400';
    }
    if (tag.count >= 5) {
        return 'bg-cyan-200 text-cyan-600 hover:border-cyan-600 border-cyan-300';
    }
    return 'bg-gray-100 text-gray-500 hover:border-gray-500 border-gray-200';
}

function toggleTag(tag) {
    tag.selected = !tag.selected;
    emit('select-tags', props.tags.filter((el) => el.selected));
}

function selectAll() {
    props.tags.forEach((t) => (t.selected = false));
    emit('select-tags', []);
}
</script>

<template>
    <div class="flex gap-2 flex-wrap">
        <span
            class="inline-block rounded-md px-2.5 cursor-pointer transition-all border-2 duration-300 bg-white hover:border-gray-500"
            @click="selectAll()"
        >
            Все - <b>{{ cnt }}</b>
        </span>
        <div v-for="tag in tags" :key="tag.name">
            <span
                class="inline-block rounded-md px-2.5 cursor-pointer transition-all border-2 duration-300"
                :class="calcTagClass(tag)"
                @click="toggleTag(tag)"
            >
                {{ tag.name }} - <b>{{ tag.count }}</b>
            </span>
        </div>
    </div>
</template>