<script setup>
import { ref, onMounted } from 'vue';

const typewriteText = ref('');

const phrases = [
    'Web Applications',
    'Web Development',
    'Layouts',
    'Landing Pages',
    'Websites',
];

let loopNum = 0;
let isDeleting = false;
let txt = '';

function tick() {
    const i = loopNum % phrases.length;
    const fullTxt = phrases[i];

    if (isDeleting) {
        txt = fullTxt.substring(0, txt.length - 1);
    } else {
        txt = fullTxt.substring(0, txt.length + 1);
    }

    typewriteText.value = txt;

    let delta = 200 - Math.random() * 100;

    if (isDeleting) {
        delta /= 2;
    }

    if (!isDeleting && txt === fullTxt) {
        delta = 2000;
        isDeleting = true;
    } else if (isDeleting && txt === '') {
        isDeleting = false;
        loopNum++;
        delta = 500;
    }

    setTimeout(tick, delta);
}

onMounted(() => {
    tick();
});
</script>

<template>
    <section class="flex flex-col sm:justify-between items-center sm:flex-row mt-12 sm:mt-10">
        <div class="w-full text-left">
            <h1 class="font-semibold text-3xl md:text-3xl xl:text-4xl text-center sm:text-left text-ternary-dark">
                Павел Никитин
            </h1>
            <p class="font-medium mt-2 text-lg sm:text-xl xl:text-2xl text-center sm:text-left leading-snug text-gray-400">
                Backend & Frontend разработчик.<br class="md:hidden">
                Создаю
                <span class="bg-gradient-to-r from-indigo-500 via-purple-500 to-pink-500 bg-clip-text text-transparent font-bold">
                    {{ typewriteText }}
                </span>
                <span class="animate-pulse">|</span>
            </p>
        </div>
    </section>
</template>