import Alpine from 'alpinejs';
import { animateBars, animateCounters } from './game/xp-bar';
import { startNotificationPolling, startRankingPolling } from './game/polling';
import { startChallengePolling } from './game/duel-challenge-modal';
import { showToast } from './game/toasts';
import { kingdomMapEditor } from './game/map-editor';
import { duelBattle } from './game/duel-battle';
import { teamBattleSeries } from './game/team-battle-series';
import { bindArenaSoundToggles, unlockArenaAudio } from './game/sound';

window.Alpine = Alpine;
Alpine.data('kingdomMapEditor', kingdomMapEditor);
Alpine.data('duelBattle', duelBattle);
Alpine.data('teamBattleSeries', teamBattleSeries);
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
        const tone = el.dataset.flashTone === 'warn' ? 'warn' : 'info';
        showToast(el.dataset.flashTitle || 'Arena', el.dataset.flashToast, tone);
    });

    document.querySelectorAll('[data-attendance-form]').forEach((form) => {
        const syncAttendanceRow = (row) => {
            const valueInput = row.querySelector('[data-attendance-value]');
            const present = row.querySelector('[data-attendance-present]');
            const justified = row.querySelector('[data-attendance-justified]');

            if (!valueInput || !present || !justified) {
                return;
            }

            if (present.checked) {
                justified.checked = false;
                justified.disabled = true;
                valueInput.value = 'present';
                return;
            }

            justified.disabled = false;
            valueInput.value = justified.checked ? 'justified' : 'absent';
        };

        const applyAttendanceStatus = (row, status) => {
            const present = row.querySelector('[data-attendance-present]');
            const justified = row.querySelector('[data-attendance-justified]');

            if (!present || !justified) {
                return;
            }

            present.checked = status === 'present';
            justified.checked = status === 'justified';
            syncAttendanceRow(row);
        };

        form.querySelectorAll('[data-attendance-row]').forEach((row) => {
            row.querySelector('[data-attendance-present]')?.addEventListener('change', () => {
                syncAttendanceRow(row);
            });
            row.querySelector('[data-attendance-justified]')?.addEventListener('change', (event) => {
                if (event.target.checked) {
                    const present = row.querySelector('[data-attendance-present]');
                    if (present) {
                        present.checked = false;
                    }
                }
                syncAttendanceRow(row);
            });
            syncAttendanceRow(row);
        });

        form.querySelectorAll('[data-attendance-mark-all]').forEach((button) => {
            button.addEventListener('click', () => {
                const status = button.dataset.attendanceMarkAll;
                form.querySelectorAll('[data-attendance-row]').forEach((row) => {
                    applyAttendanceStatus(row, status);
                });
            });
        });
    });

    bindArenaSoundToggles();
    document.addEventListener('pointerdown', unlockArenaAudio);
    document.addEventListener('keydown', unlockArenaAudio);
});
