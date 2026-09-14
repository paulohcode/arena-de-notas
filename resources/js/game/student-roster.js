import { showToast } from './toasts';
import { csrfToken } from './urls';

export function bindStudentRoster(root) {
    bindRosterSearch(root);
    bindBehaviorForms(root);
}

function bindRosterSearch(root) {
    const input = root.querySelector('[data-student-search]');
    if (! input) {
        return;
    }

    const cards = [...root.querySelectorAll('[data-student-card]')];
    const groups = [...root.querySelectorAll('[data-roster-group]')];
    const empty = root.querySelector('[data-roster-empty]');

    const apply = () => {
        const query = input.value.trim().toLowerCase();

        cards.forEach((card) => {
            const haystack = card.dataset.search || '';
            card.hidden = Boolean(query) && ! haystack.includes(query);
        });

        groups.forEach((group) => {
            const visible = [...group.querySelectorAll('[data-student-card]')].some((card) => ! card.hidden);
            group.hidden = ! visible;
        });

        if (empty) {
            empty.hidden = cards.some((card) => ! card.hidden);
        }
    };

    input.addEventListener('input', apply);
    input.addEventListener('search', apply);
}

function bindBehaviorForms(root) {
    root.querySelectorAll('[data-behavior-form]').forEach((form) => {
        let queue = Promise.resolve();

        form.addEventListener('keydown', (event) => {
            if (event.key === 'Enter' && event.target.matches('[data-behavior-amount]')) {
                event.preventDefault();
            }
        });

        form.addEventListener('submit', (event) => {
            event.preventDefault();
            const delta = resolveBehaviorDelta(event, form);
            if (delta === null) {
                showToast('Comportamento', 'Informe uma quantidade de pontos maior que zero.', 'warn');
                return;
            }

            queue = queue.then(() => submitBehavior(form, delta));
        });
    });
}

function resolveBehaviorDelta(event, form) {
    const submitter = event.submitter;
    if (! submitter) {
        return null;
    }

    if (submitter.hasAttribute('data-behavior-sign')) {
        const amount = Number(form.querySelector('[data-behavior-amount]')?.value);
        const sign = Number(submitter.dataset.behaviorSign);
        if (! Number.isFinite(amount) || amount <= 0 || ! Number.isFinite(sign) || sign === 0) {
            return null;
        }

        return amount * sign;
    }

    if (submitter.name === 'delta') {
        const delta = Number(submitter.value);
        return Number.isFinite(delta) && delta !== 0 ? delta : null;
    }

    return null;
}

async function submitBehavior(form, delta) {
    const token = form.querySelector('input[name="_token"]')?.value || csrfToken();
    const body = new URLSearchParams();
    body.set('_token', token);
    body.set('delta', String(delta));

    form.setAttribute('aria-busy', 'true');

    try {
        const response = await fetch(form.action, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': token,
            },
            credentials: 'same-origin',
            body,
        });

        if (response.status === 419) {
            showToast('Comportamento', 'Sua sessão expirou. Atualize a página e tente de novo.', 'warn');
            return;
        }

        const contentType = response.headers.get('content-type') || '';
        const data = contentType.includes('application/json')
            ? await response.json().catch(() => ({}))
            : {};

        if (! response.ok) {
            const firstError = data.errors
                ? Object.values(data.errors).flat()[0]
                : (data.message || 'Não foi possível atualizar o comportamento.');
            showToast('Comportamento', firstError, 'warn');
            return;
        }

        if (typeof data.behavior_score === 'number') {
            updateBehaviorScore(form, data.behavior_score);
        }
    } catch {
        showToast('Comportamento', 'Não foi possível atualizar o comportamento.', 'warn');
    } finally {
        form.removeAttribute('aria-busy');
    }
}

function updateBehaviorScore(form, score) {
    const el = form.querySelector('[data-behavior-score]');
    if (! el) {
        return;
    }

    el.textContent = String(Math.round(score));
    el.classList.remove('text-rose-300', 'text-amber-300', 'text-emerald-300');
    el.classList.add(scoreClass(score));
}

function scoreClass(score) {
    if (score < 50) {
        return 'text-rose-300';
    }

    if (score < 80) {
        return 'text-amber-300';
    }

    return 'text-emerald-300';
}
