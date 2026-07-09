<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';
import AppLayout from '@/Layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });
</script>

<template>
    <Head title="404 — Страница не найдена" />

    <div class="container mx-auto flex flex-col items-center justify-center min-h-[70vh] text-center px-4 sm:px-6">
        <!-- Decorative blob -->
        <div
            class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-87.5 sm:w-125 aspect-square rounded-full bg-[#F7ECE6] animate-blob pointer-events-none"
            aria-hidden="true"
        />

        <div class="relative z-10">
            <!-- 404 heading -->
            <h1 class="font-display text-[8rem] sm:text-[10rem] leading-none font-bold text-accent-terracotta/20 select-none">
                404
            </h1>

            <p class="font-display text-2xl sm:text-3xl font-bold text-text-primary mt-2 mb-3">
                Страница не найдена
            </p>

            <p class="text-text-muted mb-10 max-w-md text-base leading-relaxed">
                Запрашиваемая страница не существует или была перемещена.
            </p>

            <Link
                href="/"
                class="group inline-flex items-center gap-2.5 rounded-2xl bg-accent-terracotta text-white px-6 py-3.5 font-medium shadow-card hover:shadow-card-hover hover:-translate-y-0.5 transition-all duration-300 cursor-pointer"
            >
                <ArrowLeft class="w-4 h-4 group-hover:-translate-x-0.5 transition-transform" />
                На главную
            </Link>
        </div>
    </div>
</template>