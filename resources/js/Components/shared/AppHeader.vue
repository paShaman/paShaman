<script setup>
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <header class="py-6 sm:py-8">
        <div class="container mx-auto px-4 sm:px-6">
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