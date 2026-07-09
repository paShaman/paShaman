<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });
</script>

<template>
    <Head title="404 — Страница не найдена" />

    <div class="container mx-auto flex flex-col items-center justify-center min-h-[60vh] text-center">
        <h1 class="text-8xl sm:text-9xl font-bold bg-gradient-to-r from-indigo-500 via-purple-500 to-pink-500 bg-clip-text text-transparent">
            404
        </h1>
        <p class="text-2xl sm:text-3xl font-semibold text-ternary-dark dark:text-primary-light mt-4 mb-8">
            Страница не найдена
        </p>
        <p class="text-gray-400 dark:text-gray-500 mb-8 max-w-md">
            Запрашиваемая страница не существует или была перемещена.
        </p>
        <Link
            href="/"
            class="font-medium flex items-center px-6 py-3 rounded-lg shadow-lg hover:shadow-xl bg-indigo-500 hover:bg-indigo-600 text-white duration-300 cursor-pointer"
        >
            На главную
        </Link>
    </div>
</template>