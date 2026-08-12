import Lenis from 'lenis';

let lenis = null;

export function initLenis() {
    if (lenis || window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        return lenis;
    }

    lenis = new Lenis({
        duration: 1.1,
        easing: (t) => Math.min(1, 1.001 - 2 ** (-10 * t)),
        smoothWheel: true,
        autoRaf: true,
    });

    return lenis;
}

export function getLenis() {
    return lenis;
}

export function lenisScrollTo(target, options = {}) {
    if (lenis) {
        lenis.scrollTo(target, options);
        return;
    }

    const offset = options.offset ?? 0;
    let top = 0;

    if (typeof target === 'number') {
        top = target;
    } else if (typeof target === 'string') {
        const el = document.querySelector(target);
        top = el ? el.getBoundingClientRect().top + window.scrollY - offset : 0;
    } else if (target instanceof HTMLElement) {
        top = target.getBoundingClientRect().top + window.scrollY - offset;
    }

    window.scrollTo({ top, behavior: 'smooth' });
}
