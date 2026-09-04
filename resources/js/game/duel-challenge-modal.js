/**
 * Modal global de desafio: aparece em qualquer tela do aluno.
 */
export function startChallengePolling(url, csrf) {
    if (!url) {
        return;
    }

    const shown = new Set();
    let busy = false;
    let queue = [];

    const pull = async () => {
        try {
            const response = await fetch(url, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });
            if (! response.ok) {
                return;
            }

            const data = await response.json();
            (data.challenges || []).forEach((challenge) => {
                if (shown.has(challenge.duel_id)) {
                    return;
                }
                shown.add(challenge.duel_id);
                queue.push(challenge);
            });

            maybeShowNext();
        } catch {
            // ignore network blips
        }
    };

    const maybeShowNext = () => {
        if (busy || queue.length === 0) {
            return;
        }

        busy = true;
        const challenge = queue.shift();
        openChallengeModal(challenge, csrf, () => {
            busy = false;
            maybeShowNext();
        });
    };

    pull();
    setInterval(pull, 2500);
}

function openChallengeModal(challenge, csrf, onDone) {
    const existing = document.querySelector('[data-duel-challenge-modal]');
    if (existing) {
        existing.remove();
    }

    const layer = document.createElement('div');
    layer.className = 'modal-layer';
    layer.dataset.duelChallengeModal = 'true';
    layer.innerHTML = `
        <div class="duel-challenge-card game-card p-6 max-w-md w-[92vw]" role="dialog" aria-modal="true" aria-labelledby="duel-challenge-title">
            <p class="hero-kicker !mb-2">Desafio na arena</p>
            <h2 id="duel-challenge-title" class="font-display text-2xl text-amber-300 mb-2">Aceita o duelo?</h2>
            <p class="text-amber-100/75 mb-6">${escapeHtml(challenge.message)}</p>
            <div class="flex flex-wrap gap-3 justify-end">
                <button type="button" class="game-btn-ghost" data-duel-decline>Recusar</button>
                <button type="button" class="game-btn" data-duel-accept>Aceitar batalha</button>
            </div>
            <p class="text-xs text-amber-100/55 mt-4 hidden" data-duel-loading>Preparando o combate…</p>
            <p class="text-xs text-rose-300 mt-3 hidden" data-duel-error></p>
        </div>
    `;

    document.body.appendChild(layer);

    const acceptBtn = layer.querySelector('[data-duel-accept]');
    const declineBtn = layer.querySelector('[data-duel-decline]');
    const errorEl = layer.querySelector('[data-duel-error]');
    const loadingEl = layer.querySelector('[data-duel-loading]');

    const setLoading = (loading, label = 'Preparando o combate…') => {
        acceptBtn.disabled = loading;
        declineBtn.disabled = loading;
        acceptBtn.textContent = loading ? 'Entrando…' : 'Aceitar batalha';
        declineBtn.textContent = loading ? '…' : 'Recusar';
        if (loading) {
            loadingEl.textContent = label;
            loadingEl.classList.remove('hidden');
            errorEl.classList.add('hidden');
        } else {
            loadingEl.classList.add('hidden');
        }
    };

    const showError = (message) => {
        errorEl.textContent = message;
        errorEl.classList.remove('hidden');
        setLoading(false);
    };

    // Form POST nativo: o browser navega para a tela do duelo (fetch engolia o redirect).
    acceptBtn.addEventListener('click', () => {
        setLoading(true, 'Aceito! Abrindo a batalha…');
        sessionStorage.setItem('arena-duel-focus', String(challenge.duel_id));

        const form = document.createElement('form');
        form.method = 'POST';
        form.action = challenge.accept_url;
        form.style.display = 'none';

        const token = document.createElement('input');
        token.type = 'hidden';
        token.name = '_token';
        token.value = csrf;
        form.appendChild(token);

        document.body.appendChild(form);
        form.submit();
    });

    declineBtn.addEventListener('click', async () => {
        setLoading(true, 'Recusando…');
        try {
            const response = await fetch(challenge.decline_url, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrf,
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                redirect: 'manual',
            });

            if (response.type === 'opaqueredirect' || (response.status >= 300 && response.status < 400)) {
                layer.remove();
                onDone();
                return;
            }

            if (! response.ok) {
                const data = await response.json().catch(() => ({}));
                const firstError = data.errors
                    ? Object.values(data.errors).flat()[0]
                    : (data.message || 'Não foi possível recusar o desafio.');
                showError(firstError);
                return;
            }

            layer.remove();
            onDone();
        } catch {
            showError('Falha de conexão. Tente de novo.');
        }
    });
}

function escapeHtml(value) {
    return String(value)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;');
}
