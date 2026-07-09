<script setup>
import { Sun, Moon } from 'lucide-vue-next';

const props = defineProps({
    theme: {
        type: String,
        default: 'light',
    },
});

const emit = defineEmits(['themeChanged']);

function toggleTheme() {
    const newTheme = props.theme === 'dark' ? 'light' : 'dark';
    localStorage.setItem('theme', newTheme);
    emit('themeChanged', newTheme);
}
</script>

<template>
    <button
        type="button"
        @click="toggleTheme"
        class="p-2.5 rounded-lg bg-primary-light dark:bg-ternary-dark hover:bg-gray-100 dark:hover:bg-gray-700 shadow-sm cursor-pointer transition-colors duration-300"
        :title="theme === 'dark' ? 'Светлая тема' : 'Тёмная тема'"
    >
        <Sun v-if="theme === 'dark'" class="w-5 h-5 text-ternary-dark dark:text-ternary-light" />
        <Moon v-else class="w-5 h-5 text-ternary-dark dark:text-ternary-light" />
    </button>
</template>