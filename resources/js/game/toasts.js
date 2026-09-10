import { toLocalPath } from './urls';
import { playArenaSound } from './sound';

export function showToast(title, message, type = 'info', url = null) {
    const stack = document.querySelector('[data-toast-stack]') || createStack();
    const path = toLocalPath(url);
    const el = document.createElement(path ? 'a' : 'div');
    el.className = 'toast';
    if (type === 'warn') {
        el.classList.add('toast--warn');
    }
    if (path) {
        el.href = path;
        el.classList.add('toast--link');
    }
    el.innerHTML = `<p class="font-display text-sm text-gold">${escapeHtml(title)}</p><p class="text-sm text-purple-100/80">${escapeHtml(message)}</p>`;
    stack.appendChild(el);
    playOptionalSound(type);
    setTimeout(() => el.remove(), path ? 8000 : 4500);
}

function createStack() {
    const stack = document.createElement('div');
    stack.className = 'toast-stack';
    stack.dataset.toastStack = 'true';
    document.body.appendChild(stack);
    return stack;
}

function escapeHtml(value) {
    return String(value)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;');
}

function playOptionalSound(type) {
    if (!['level', 'rank_up', 'badge', 'duel_result', 'duel_challenge', 'realm_duel_result', 'realm_duel_challenge'].includes(type)) {
        return;
    }

    playArenaSound(type);
}
