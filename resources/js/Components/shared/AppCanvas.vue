<script setup>
import { ref, onMounted, onUnmounted } from 'vue';

const canvasRef = ref(null);

let ctx = null;
let ratio = 1;
let width = 0;
let height = 0;
let particles = [];
let animationId = null;

const mouse = { x: -9999, y: -9999 };

const COLORS = ['#e07a5f', '#81b29a', '#f2cc8f'];
const TAU = Math.PI * 2;
const LINK_DIST = 130;
const MOUSE_RADIUS = 240;
const MOUSE_CLOSE = 70;
const MOUSE_FORCE = 0.08;
const MOUSE_REPEL = 0.05;
const REPEL_DIST = 30;
const REPEL_FORCE = 0.06;
const SPRING = 0.0022;
const FLOW_FORCE = 0.025;
const FLOW_SCALE = 0.004;
const FLOW_TIME = 0.0003;
const SPACING = 36;
const PADDING = 18;
const MAX_PARTICLES = 160;

function hash(ix, iy) {
    let h = Math.imul(ix, 374761393) ^ Math.imul(iy, 668265263);
    h = Math.imul(h ^ (h >>> 13), 1274126177);
    return ((h ^ (h >>> 16)) >>> 0) / 4294967295;
}

function smooth(t) {
    return t * t * (3 - 2 * t);
}

function lerp(a, b, t) {
    return a + (b - a) * t;
}

// Value noise 2D — плавное поле, задающее направление дрейфа частиц
function noise(x, y) {
    const xi = Math.floor(x);
    const yi = Math.floor(y);
    const xf = smooth(x - xi);
    const yf = smooth(y - yi);
    return lerp(
        lerp(hash(xi, yi), hash(xi + 1, yi), xf),
        lerp(hash(xi, yi + 1), hash(xi + 1, yi + 1), xf),
        yf,
    );
}

function createParticles() {
    const step = SPACING * ratio;
    const pad = PADDING * ratio;
    const homes = [];

    for (let x = pad; x < width - pad; x += step) {
        for (let y = pad; y < height - pad; y += step) {
            homes.push({ x, y });
        }
    }

    let count = Math.min(homes.length, MAX_PARTICLES);
    if (window.matchMedia('(pointer: coarse)').matches) {
        count = Math.round(count * 0.5);
    }

    for (let i = homes.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1));
        [homes[i], homes[j]] = [homes[j], homes[i]];
    }

    // Каждая частица привязана к своей «дому»-позиции на сетке
    particles = homes.slice(0, count).map((h) => ({
        x: h.x + (Math.random() - 0.5) * step * 0.5,
        y: h.y + (Math.random() - 0.5) * step * 0.5,
        ox: h.x,
        oy: h.y,
        vx: 0,
        vy: 0,
        radius: (0.8 + Math.random() * 1.4) * ratio,
        color: COLORS[Math.floor(Math.random() * COLORS.length)],
        seed: Math.random() * TAU,
    }));
}

function draw(t) {
    ctx.clearRect(0, 0, width, height);
    ctx.lineCap = 'round';

    const linkDist = LINK_DIST * ratio;

    for (let i = 0; i < particles.length; i++) {
        for (let j = i + 1; j < particles.length; j++) {
            const a = particles[i];
            const b = particles[j];
            const dx = a.x - b.x;
            const dy = a.y - b.y;
            const d2 = dx * dx + dy * dy;

            if (d2 < linkDist * linkDist) {
                const alpha = (1 - Math.sqrt(d2) / linkDist) * 0.3;
                ctx.strokeStyle = `rgba(139, 115, 96, ${alpha})`;
                ctx.lineWidth = ratio;
                ctx.beginPath();
                ctx.moveTo(a.x, a.y);
                ctx.lineTo(b.x, b.y);
                ctx.stroke();
            }
        }
    }

    const R = MOUSE_RADIUS * ratio;
    if (mouse.x >= 0 && mouse.y >= 0) {
        for (const p of particles) {
            const dx = p.x - mouse.x;
            const dy = p.y - mouse.y;
            const d2 = dx * dx + dy * dy;

            if (d2 < R * R) {
                const alpha = (1 - Math.sqrt(d2) / R) * 0.35;
                ctx.strokeStyle = `rgba(224, 122, 95, ${alpha})`;
                ctx.lineWidth = ratio;
                ctx.beginPath();
                ctx.moveTo(p.x, p.y);
                ctx.lineTo(mouse.x, mouse.y);
                ctx.stroke();
            }
        }
    }

    for (const p of particles) {
        const pulse = 1 + 0.3 * Math.sin(t * 2.4 + p.seed);
        ctx.fillStyle = p.color;
        ctx.beginPath();
        ctx.arc(p.x, p.y, p.radius * pulse, 0, TAU);
        ctx.fill();
    }
}

function step(time) {
    const t = time * 0.001;
    const closeDist = MOUSE_CLOSE * ratio;

    for (const p of particles) {
        const angle = noise(p.x * FLOW_SCALE, p.y * FLOW_SCALE + t * FLOW_TIME) * TAU * 2;

        p.vx += Math.cos(angle) * FLOW_FORCE * ratio;
        p.vy += Math.sin(angle) * FLOW_FORCE * ratio;

        p.vx += (p.ox - p.x) * SPRING * ratio;
        p.vy += (p.oy - p.y) * SPRING * ratio;

        const dx = mouse.x - p.x;
        const dy = mouse.y - p.y;
        const d2 = dx * dx + dy * dy;
        const R = MOUSE_RADIUS * ratio;

        if (d2 < R * R && d2 > 1) {
            const d = Math.sqrt(d2);
            const pull = (1 - d / R) * MOUSE_FORCE * ratio;
            const push = d < closeDist ? (1 - d / closeDist) * MOUSE_REPEL * ratio : 0;
            p.vx += (dx / d) * (pull - push);
            p.vy += (dy / d) * (pull - push);
        }

        p.vx *= 0.94;
        p.vy *= 0.94;

        p.x += p.vx;
        p.y += p.vy;
    }

    const repelDist = REPEL_DIST * ratio;
    const repelForce = REPEL_FORCE * ratio;

    for (let i = 0; i < particles.length; i++) {
        const a = particles[i];
        for (let j = i + 1; j < particles.length; j++) {
            const b = particles[j];
            const dx = a.x - b.x;
            const dy = a.y - b.y;
            const d2 = dx * dx + dy * dy;

            if (d2 < repelDist * repelDist && d2 > 0.01) {
                const d = Math.sqrt(d2);
                const f = ((repelDist - d) / repelDist) * repelForce;
                const nx = dx / d;
                const ny = dy / d;
                a.vx += nx * f;
                a.vy += ny * f;
                b.vx -= nx * f;
                b.vy -= ny * f;
            }
        }
    }

    draw(t);
}

function loop() {
    step(performance.now());
    animationId = requestAnimationFrame(loop);
}

function resize() {
    ratio = window.devicePixelRatio || 1;
    width = window.innerWidth * ratio;
    height = window.innerHeight * ratio;
    ctx.canvas.width = width;
    ctx.canvas.height = height;
    createParticles();

    if (!animationId) {
        draw(0);
    }
}

function onPointerMove(e) {
    // clientX/Y — относительно viewport (без скролла), rect.top/left компенсируют положение canvas
    const rect = canvasRef.value.getBoundingClientRect();
    mouse.x = (e.clientX - rect.left) * ratio;
    mouse.y = (e.clientY - rect.top) * ratio;
}

function onPointerLeave() {
    mouse.x = -9999;
    mouse.y = -9999;
}

function onVisibilityChange() {
    if (document.hidden) {
        if (animationId) {
            cancelAnimationFrame(animationId);
            animationId = null;
        }
    } else if (!animationId) {
        animationId = requestAnimationFrame(loop);
    }
}

onMounted(() => {
    const canvas = canvasRef.value;
    ctx = canvas.getContext('2d');

    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    resize();

    window.addEventListener('pointermove', onPointerMove, { passive: true });
    document.addEventListener('pointerleave', onPointerLeave);
    window.addEventListener('resize', resize);
    document.addEventListener('visibilitychange', onVisibilityChange);

    if (reducedMotion) {
        draw(0);
    } else {
        animationId = requestAnimationFrame(loop);
    }
});

onUnmounted(() => {
    if (animationId) {
        cancelAnimationFrame(animationId);
    }
    window.removeEventListener('pointermove', onPointerMove);
    document.removeEventListener('pointerleave', onPointerLeave);
    window.removeEventListener('resize', resize);
    document.removeEventListener('visibilitychange', onVisibilityChange);
});
</script>

<template>
    <canvas
        ref="canvasRef"
        id="stage"
        class="fixed inset-0 w-full h-full pointer-events-none -z-1 opacity-40"
    />
</template>
