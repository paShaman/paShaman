<script setup>
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <header class="relative py-8 sm:py-10">
        <!-- Background blob -->
        <div
            class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-4/5 w-[350px] sm:w-[600px] aspect-square rounded-full bg-accent-terracotta/10 animate-blob pointer-events-none"
            aria-hidden="true"
        />

        <div class="relative container mx-auto px-4 sm:px-6">
            <div class="flex justify-center">
                <Link href="/" class="inline-block">
                    <span class="font-display text-4xl sm:text-5xl font-bold text-text-primary tracking-tight hover:text-accent-terracotta transition-colors duration-300">
                        paShaman
                    </span>
                </Link>
            </div>
        </div>
    </header>
</template>
