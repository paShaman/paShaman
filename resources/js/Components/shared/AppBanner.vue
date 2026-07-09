<script setup>
</script>

<template>
    <section class="relative flex flex-col items-center justify-center text-center pt-12 sm:pt-16 pb-20 sm:pb-28">
        <!-- Background blob -->
        <div
            class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[500px] sm:w-[700px] h-[500px] sm:h-[700px] rounded-full bg-accent-terracotta/10 animate-blob pointer-events-none"
            aria-hidden="true"
        />

        <!-- Content -->
        <div class="relative container mx-auto px-4 sm:px-6">
            <h1 class="font-display text-4xl sm:text-6xl lg:text-7xl font-bold text-text-primary mb-4 sm:mb-6 tracking-tight">
                Павел Никитин
            </h1>
            <p class="text-lg sm:text-xl lg:text-2xl text-text-secondary leading-relaxed max-w-2xl mx-auto">
                Backend & Frontend разработчик.
                <br class="sm:hidden">
                Создаю
                <span class="bg-gradient-to-r from-accent-terracotta via-accent-amber to-accent-sage bg-clip-text text-transparent font-semibold">
                    веб-проекты
                </span>
            </p>
        </div>

    </section>
</template>