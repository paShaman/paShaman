<script setup>
import { ref, onMounted, onUnmounted } from 'vue';

const canvasRef = ref(null);

let ctx = null;
let ratio = 1;
let cb = null;
let dots = [];
let animationId = null;

const mouse = { x: 0, y: 0 };
const wide = 36;
const high = wide / 2.2;
const padding = 50;

function getAngle(obj1, obj2) {
    const dX = obj2.x - obj1.x;
    const dY = obj2.y - obj1.y;
    return Math.atan2(dY, dX) / Math.PI * 180;
}

function getDistance(obj1, obj2) {
    const dx = obj1.x - obj2.x;
    const dy = obj1.y - obj2.y;
    return Math.sqrt(dx * dx + dy * dy);
}

function getV(dot) {
    const d = getDistance(dot, mouse);

    dot.size = (200 - d) / 20;
    dot.size = dot.size < 1 ? 1 : dot.size;

    dot.angle = getAngle(dot, mouse);

    return {
        x: (d > 20 ? 20 : d) * Math.cos(dot.angle * Math.PI / 180),
        y: (d > 20 ? 20 : d) * Math.sin(dot.angle * Math.PI / 180),
    };
}

function render() {
    ctx.clearRect(0, 0, ctx.canvas.width, ctx.canvas.height);
    ctx.fillStyle = '#e07a5f';

    for (let i = 0; i < dots.length; i++) {
        const s = dots[i];
        const v = getV(s);

        ctx.beginPath();
        ctx.moveTo(s.x, s.y);
        ctx.lineTo(s.x + v.x, s.y + v.y);
        ctx.strokeStyle = '#e07a5f';
        ctx.lineWidth = 1 * ratio;
        ctx.stroke();
        ctx.closePath();
    }

    for (let i = 0; i < dots.length; i++) {
        const s = dots[i];
        const v = getV(s);

        ctx.beginPath();
        ctx.arc(s.x + v.x, s.y + v.y, s.size * ratio, 0, 2 * Math.PI, false);
        ctx.fill();
        ctx.closePath();
    }
}

function createDots() {
    dots = [];
    for (let i = 0; i < wide; i++) {
        const x = Math.floor(((cb.width - padding * 2) / (wide - 1)) * i + padding) * ratio;

        for (let j = 0; j < high; j++) {
            const y = Math.floor(((cb.height - padding * 2) / (high - 1)) * j + padding) * ratio;

            dots.push({
                x: x,
                y: y,
                ox: x,
                oy: y,
            });
        }
    }
}

function resize() {
    ctx.canvas.width = window.innerWidth * ratio;
    ctx.canvas.height = window.innerHeight * ratio;
    cb = canvasRef.value.getBoundingClientRect();
    createDots();
}

function onMouseMove(e) {
    mouse.x = e.pageX * ratio;
    mouse.y = e.pageY * ratio;
}

function animate() {
    render();
    animationId = requestAnimationFrame(animate);
}

onMounted(() => {
    const canvas = canvasRef.value;
    ctx = canvas.getContext('2d');
    ratio = window.devicePixelRatio || 1;
    cb = canvas.getBoundingClientRect();

    createDots();
    resize();

    window.addEventListener('mousemove', onMouseMove);
    window.addEventListener('resize', resize);

    animationId = requestAnimationFrame(animate);
});

onUnmounted(() => {
    if (animationId) {
        cancelAnimationFrame(animationId);
    }
    window.removeEventListener('mousemove', onMouseMove);
    window.removeEventListener('resize', resize);
});
</script>

<template>
    <canvas
        ref="canvasRef"
        id="stage"
        class="fixed inset-0 w-full h-full pointer-events-none -z-1 opacity-20"
    />
</template>