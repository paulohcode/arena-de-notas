export function animateBars() {
    document.querySelectorAll('[data-xp-fill]').forEach((el) => {
        const value = Number(el.dataset.xpFill || 0);
        requestAnimationFrame(() => {
            el.style.width = `${Math.max(0, Math.min(100, value))}%`;
        });
    });
}

export function animateCounters() {
    document.querySelectorAll('[data-count-to]').forEach((el) => {
        const target = Number(el.dataset.countTo || 0);
        const start = Number(el.dataset.countFrom || 0);
        const duration = 700;
        const started = performance.now();

        const tick = (now) => {
            const t = Math.min(1, (now - started) / duration);
            const eased = 1 - (1 - t) ** 3;
            el.textContent = (start + (target - start) * eased).toFixed(t === 1 ? 1 : 1);
            if (t < 1) {
                requestAnimationFrame(tick);
            }
        };

        requestAnimationFrame(tick);
    });
}
