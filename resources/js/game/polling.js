import { showToast } from './toasts';
import { showLevelUp } from './level-up';

export function startNotificationPolling(url, markUrl, csrf) {
    if (!url) {
        return;
    }

    const seen = new Set();

    const pull = async () => {
        try {
            const response = await fetch(url, { headers: { Accept: 'application/json' } });
            if (!response.ok) {
                return;
            }
            const data = await response.json();
            const badge = document.querySelector('[data-bell-count]');
            if (badge) {
                badge.textContent = data.unread;
                badge.classList.toggle('hidden', data.unread === 0);
            }

            (data.items || []).forEach((item) => {
                if (seen.has(item.id)) {
                    return;
                }
                seen.add(item.id);
                if (item.type === 'duel_challenge') {
                    // O modal dedicado cuida do aceite/recusa.
                    return;
                }
                const url = item.payload?.url || null;
                if (item.type === 'duel_result' && url) {
                    const focusId = sessionStorage.getItem('arena-duel-focus');
                    const duelId = String(item.payload?.duel_id ?? '');
                    const alreadyOnDuel = window.location.pathname.includes('/arena/duelos/');
                    if (focusId && focusId === duelId && ! alreadyOnDuel) {
                        sessionStorage.removeItem('arena-duel-focus');
                        window.location.href = url;
                        return;
                    }
                    if (document.querySelector('[data-duel-waiting]') && ! alreadyOnDuel) {
                        window.location.href = url;
                        return;
                    }
                }
                showToast(item.title, item.message, item.type, url);
                if (item.type === 'level' || item.type === 'badge') {
                    showLevelUp(item.title, item.message);
                }
            });
        } catch {
            // ignore network blips
        }
    };

    pull();
    setInterval(pull, 10000);

    document.querySelectorAll('[data-mark-read]').forEach((btn) => {
        btn.addEventListener('click', async () => {
            await fetch(markUrl, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrf,
                    Accept: 'application/json',
                },
            });
            pull();
        });
    });
}

export function startRankingPolling(url) {
    if (!url) {
        return;
    }

    const pull = async () => {
        try {
            const response = await fetch(url, { headers: { Accept: 'application/json' } });
            if (!response.ok) {
                return;
            }
            const data = await response.json();
            updatePlayers(data.players || []);
            updateGuilds(data.guilds || []);
        } catch {
            // ignore
        }
    };

    setInterval(pull, 10000);
}

function syncRankBadge(el, position) {
    if (!el?.classList?.contains('rank-badge')) {
        return;
    }

    el.classList.remove('is-gold', 'is-silver', 'is-bronze');
    if (position === 1) {
        el.classList.add('is-gold');
    } else if (position === 2) {
        el.classList.add('is-silver');
    } else if (position === 3) {
        el.classList.add('is-bronze');
    }
}

function updatePlayers(players) {
    const list = document.querySelector('[data-player-list]');
    if (!list) {
        return;
    }

    players.forEach((player) => {
        const row = list.querySelector(`[data-player-id="${player.id}"]`);
        if (!row) {
            return;
        }
        const pos = row.querySelector('[data-pos]');
        const avg = row.querySelector('[data-avg]');
        const xpText = row.querySelector('[data-xp-text]');
        const levelEl = row.querySelector('[data-level]');
        const badgeCountEl = row.querySelector('[data-badge-count]');
        const xpFill = row.querySelector('[data-xp-fill]');
        const oldPos = Number(pos?.textContent || 0);
        if (pos) {
            pos.textContent = `${player.position}º`;
            syncRankBadge(pos, player.position);
        }
        if (avg) {
            avg.textContent = Number(player.average).toFixed(1);
        }
        if (xpText) {
            xpText.textContent = `${player.xp}`;
        }
        if (levelEl && player.level_name) {
            levelEl.textContent = player.level_name;
        }
        if (badgeCountEl) {
            badgeCountEl.textContent = Number(player.badge_count || 0);
        }
        if (xpFill) {
            const pct = Math.max(0, Math.min(100, Number(player.xp_progress || 0)));
            xpFill.dataset.xpFill = pct;
            xpFill.style.width = `${pct}%`;
        }
        if (oldPos && oldPos !== player.position) {
            row.classList.add(player.position < oldPos ? 'rank-up' : 'rank-down');
            setTimeout(() => row.classList.remove('rank-up', 'rank-down'), 900);
        }
    });
}

function updateGuilds(guilds) {
    const list = document.querySelector('[data-guild-list]');
    if (!list) {
        return;
    }

    guilds.forEach((guild) => {
        const row = list.querySelector(`[data-guild-id="${guild.id}"]`);
        if (!row) {
            return;
        }
        const pos = row.querySelector('[data-pos]');
        const score = row.querySelector('[data-score]');
        const oldPos = Number(pos?.textContent || 0);
        if (pos) {
            pos.textContent = `${guild.position}º`;
            syncRankBadge(pos, guild.position);
        }
        if (score) {
            score.textContent = Number(guild.score).toFixed(1);
        }
        const emblem = row.querySelector('[data-team-emblem]');
        if (emblem && guild.color) {
            emblem.style.color = guild.color;
        }
        const bar = row.querySelector('[data-xp-fill]');
        if (bar) {
            bar.style.width = `${guild.score}%`;
        }
        if (oldPos && oldPos !== guild.position) {
            row.classList.add(guild.position < oldPos ? 'rank-up' : 'rank-down');
            setTimeout(() => row.classList.remove('rank-up', 'rank-down'), 900);
        }
    });
}
