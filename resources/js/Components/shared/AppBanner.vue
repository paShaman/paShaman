<script setup>
</script>

<template>
    <section class="relative flex flex-col items-center justify-center text-center pt-12 sm:pt-16 pb-16 sm:pb-24 overflow-hidden">
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
                    веб-приложения
                </span>
            </p>
        </div>

        <!-- Wave divider -->
        <div class="wave-divider absolute bottom-0 left-0" aria-hidden="true">
            <svg viewBox="0 0 1200 120" preserveAspectRatio="none">
                <path d="M321.39,56.44c58-10.79,114.16-30.13,172-41.86,82.39-16.72,168.19-17.73,250.45-.39C823.78,31,906.67,72,985.66,92.83c70.05,18.48,146.53,26.09,214.34,3V0H0V27.35A600.21,600.21,0,0,0,321.39,56.44Z" fill="#ffffff"/>
            </svg>
        </div>
    </section>
</template>