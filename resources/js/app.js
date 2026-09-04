import Alpine from 'alpinejs';
import { animateBars, animateCounters } from './game/xp-bar';
import { startNotificationPolling, startRankingPolling } from './game/polling';
import { startChallengePolling } from './game/duel-challenge-modal';
import { showToast } from './game/toasts';
import { kingdomMapEditor } from './game/map-editor';
import { duelBattle } from './game/duel-battle';

window.Alpine = Alpine;
Alpine.data('kingdomMapEditor', kingdomMapEditor);
Alpine.data('duelBattle', duelBattle);
Alpine.start();

document.addEventListener('DOMContentLoaded', () => {
    animateBars();
    animateCounters();

    const root = document.querySelector('[data-game-root]');
    if (root) {
        startNotificationPolling(root.dataset.notifyUrl, root.dataset.markReadUrl, root.dataset.csrf);
        startRankingPolling(root.dataset.rankingUrl);
        startChallengePolling(root.dataset.challengePollUrl, root.dataset.csrf);
    }

    document.querySelectorAll('[data-flash-toast]').forEach((el) => {
        showToast(el.dataset.flashTitle || 'Arena', el.dataset.flashToast);
    });

    const soundToggle = document.querySelector('[data-sound-toggle]');
    if (soundToggle) {
        soundToggle.checked = localStorage.getItem('arena-sound') === 'on';
        soundToggle.addEventListener('change', () => {
            localStorage.setItem('arena-sound', soundToggle.checked ? 'on' : 'off');
        });
    }
});
