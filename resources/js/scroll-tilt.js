const STAGE_SELECTOR = '[data-hero-stage]';
const TARGET_SELECTOR = '[data-tilt-target]';
const SHADOW_SELECTOR = '[data-tilt-shadow]';

const clamp = (value, min, max) => Math.min(max, Math.max(min, value));

let entries = [];
let observer = null;
let cleanupFns = [];
let frame = null;
let pointer = { x: 0, y: 0, active: false, stage: null };

function scrollProgress(stage) {
    const rect = stage.getBoundingClientRect();
    const viewportHeight = window.innerHeight || document.documentElement.clientHeight;
    const center = rect.top + rect.height / 2;

    // -1 (stage center above viewport center) .. 1 (below), 0 when centered.
    return clamp((viewportHeight / 2 - center) / (viewportHeight / 2), -1, 1);
}

function render() {
    frame = null;

    entries.forEach((entry) => {
        if (!entry.visible || !entry.target) {
            return;
        }

        const progress = scrollProgress(entry.stage);
        let rotateY = progress * 10;
        let rotateX = progress * -4;

        if (pointer.active && pointer.stage === entry.stage) {
            rotateY += pointer.x * 6;
            rotateX += pointer.y * -6;
        }

        entry.target.style.transform = `rotateX(${rotateX.toFixed(2)}deg) rotateY(${rotateY.toFixed(2)}deg)`;

        if (entry.shadow) {
            const depth = Math.abs(progress);
            entry.shadow.style.opacity = String(0.55 - depth * 0.25);
            entry.shadow.style.transform = `scale(${(1 - depth * 0.12).toFixed(3)})`;
        }
    });
}

function requestRender() {
    if (frame !== null) {
        return;
    }

    frame = requestAnimationFrame(render);
}

function handlePointerMove(stage, event) {
    const rect = stage.getBoundingClientRect();

    pointer = {
        active: true,
        stage,
        x: clamp((event.clientX - (rect.left + rect.width / 2)) / (rect.width / 2), -1, 1),
        y: clamp((event.clientY - (rect.top + rect.height / 2)) / (rect.height / 2), -1, 1),
    };

    requestRender();
}

function handlePointerLeave(stage) {
    if (pointer.stage === stage) {
        pointer = { x: 0, y: 0, active: false, stage: null };
        requestRender();
    }
}

/**
 * Tears down listeners/observers and clears any applied transforms, so the
 * controller can be safely re-initialized after a wire:navigate page swap.
 */
export function teardownScrollTilt() {
    if (observer) {
        observer.disconnect();
        observer = null;
    }

    cleanupFns.forEach((fn) => fn());
    cleanupFns = [];

    entries.forEach((entry) => {
        if (entry.target) {
            entry.target.style.transform = '';
        }
    });
    entries = [];

    pointer = { x: 0, y: 0, active: false, stage: null };

    if (frame !== null) {
        cancelAnimationFrame(frame);
        frame = null;
    }
}

/**
 * Wires up the scroll/pointer-driven 3D tilt on every [data-hero-stage] in
 * the current page. Does nothing (and leaves images perfectly static) when
 * the user prefers reduced motion, or when a stage has no [data-tilt-target]
 * child (e.g. the hero has no featured item yet).
 */
export function initScrollTilt() {
    teardownScrollTilt();

    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        return;
    }

    const stages = Array.from(document.querySelectorAll(STAGE_SELECTOR));

    if (stages.length === 0) {
        return;
    }

    const supportsHover = window.matchMedia('(hover: hover) and (pointer: fine)').matches;

    entries = stages.map((stage) => ({
        stage,
        target: stage.querySelector(TARGET_SELECTOR),
        shadow: stage.querySelector(SHADOW_SELECTOR),
        visible: false,
    }));

    observer = new IntersectionObserver((observerEntries) => {
        observerEntries.forEach((observerEntry) => {
            const match = entries.find((entry) => entry.stage === observerEntry.target);

            if (match) {
                match.visible = observerEntry.isIntersecting;
            }
        });

        requestRender();
    }, { rootMargin: '10% 0px' });

    entries.forEach((entry) => {
        if (!entry.target) {
            return;
        }

        observer.observe(entry.stage);

        if (!supportsHover) {
            return;
        }

        const onMove = (event) => handlePointerMove(entry.stage, event);
        const onLeave = () => handlePointerLeave(entry.stage);

        entry.stage.addEventListener('pointermove', onMove);
        entry.stage.addEventListener('pointerleave', onLeave);

        cleanupFns.push(() => {
            entry.stage.removeEventListener('pointermove', onMove);
            entry.stage.removeEventListener('pointerleave', onLeave);
        });
    });

    const onScrollOrResize = () => requestRender();

    window.addEventListener('scroll', onScrollOrResize, { passive: true });
    window.addEventListener('resize', onScrollOrResize, { passive: true });

    cleanupFns.push(() => {
        window.removeEventListener('scroll', onScrollOrResize);
        window.removeEventListener('resize', onScrollOrResize);
    });

    requestRender();
}
