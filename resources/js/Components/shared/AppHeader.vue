<script setup>
import { ref, onMounted } from 'vue';
import { Link } from '@inertiajs/vue3';

const gradientClass = ref('');

const colors = [
    { from: 'from-slate-800', to: 'to-slate-400' },
    { from: 'from-zinc-800', to: 'to-zinc-400' },
    { from: 'from-neutral-800', to: 'to-neutral-400' },
    { from: 'from-red-800', to: 'to-red-400' },
    { from: 'from-stone-800', to: 'to-stone-400' },
    { from: 'from-orange-800', to: 'to-orange-400' },
    { from: 'from-amber-800', to: 'to-amber-400' },
    { from: 'from-yellow-800', to: 'to-yellow-400' },
    { from: 'from-lime-800', to: 'to-lime-400' },
    { from: 'from-green-800', to: 'to-green-400' },
    { from: 'from-emerald-800', to: 'to-emerald-400' },
    { from: 'from-teal-800', to: 'to-teal-400' },
    { from: 'from-cyan-800', to: 'to-cyan-400' },
    { from: 'from-sky-800', to: 'to-sky-400' },
    { from: 'from-blue-800', to: 'to-blue-400' },
    { from: 'from-indigo-800', to: 'to-indigo-400' },
    { from: 'from-violet-800', to: 'to-violet-400' },
    { from: 'from-purple-800', to: 'to-purple-400' },
    { from: 'from-fuchsia-800', to: 'to-fuchsia-400' },
    { from: 'from-pink-800', to: 'to-pink-400' },
    { from: 'from-rose-800', to: 'to-rose-400' },
    { from: 'from-gray-800', to: 'to-gray-400' },
];

function randomInt(min, max) {
    return Math.floor(Math.random() * (max - min + 1)) + min;
}

function calcGradientClass() {
    const color = colors[randomInt(1, colors.length - 1)];
    gradientClass.value = color.from + ' ' + color.to;
}

onMounted(() => {
    calcGradientClass();
});
</script>

<template>
    <nav id="nav" class="sm:container sm:mx-auto">
        <div class="z-10 max-w-screen-lg xl:max-w-screen-xl block sm:flex sm:justify-between sm:items-center my-6">
            <div class="flex justify-between items-center px-4 sm:px-0">
                <div>
                    <Link href="/">
                        <span class="text-4xl sm:text-5xl bg-gradient-to-r bg-clip-text text-transparent font-bold" :class="gradientClass" @click="calcGradientClass">paShaman</span>
                    </Link>
                </div>
            </div>
        </div>
    </nav>
</template>