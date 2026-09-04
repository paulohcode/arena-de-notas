export function showLevelUp(title, message) {
    if (document.querySelector('[data-level-modal]')) {
        return;
    }

    const layer = document.createElement('div');
    layer.className = 'modal-layer';
    layer.dataset.levelModal = 'true';
    layer.innerHTML = `
        <div class="game-card w-[min(92vw,420px)] p-8 text-center">
            <p class="text-5xl mb-3">✨</p>
            <h3 class="font-display text-2xl text-amber-300">${title}</h3>
            <p class="mt-2 text-purple-100/80">${message}</p>
            <button type="button" class="game-btn mt-6" data-close-modal>Continuar</button>
        </div>
    `;
    layer.addEventListener('click', (event) => {
        if (event.target === layer || event.target.closest('[data-close-modal]')) {
            layer.remove();
        }
    });
    document.body.appendChild(layer);
}
