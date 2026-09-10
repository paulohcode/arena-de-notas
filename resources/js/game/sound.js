const STORAGE_KEY = 'arena-sound';

let context = null;

const CLASS_HIT = {
    guerreiro: { type: 'square', freq: 160, endFreq: 90, duration: 0.14, gain: 0.055 },
    mago: { type: 'sine', freq: 620, endFreq: 880, duration: 0.16, gain: 0.045 },
    feiticeira: { type: 'sine', freq: 540, endFreq: 760, duration: 0.16, gain: 0.045 },
    arqueiro: { type: 'triangle', freq: 980, endFreq: 240, duration: 0.12, gain: 0.05 },
    ladino: { type: 'square', freq: 320, endFreq: 180, duration: 0.07, gain: 0.04 },
    paladino: { type: 'triangle', freq: 420, endFreq: 280, duration: 0.15, gain: 0.05 },
    druida: { type: 'sine', freq: 300, endFreq: 220, duration: 0.14, gain: 0.045 },
    bardo: { type: 'triangle', freq: 392, endFreq: 523, duration: 0.14, gain: 0.045 },
    clerigo: { type: 'sine', freq: 392, endFreq: 330, duration: 0.14, gain: 0.045 },
    necromante: { type: 'sawtooth', freq: 140, endFreq: 80, duration: 0.16, gain: 0.04 },
    anao: { type: 'square', freq: 110, endFreq: 70, duration: 0.16, gain: 0.06 },
    frankenstein: { type: 'sawtooth', freq: 220, endFreq: 90, duration: 0.15, gain: 0.05 },
};

export function isArenaSoundOn() {
    try {
        return localStorage.getItem(STORAGE_KEY) === 'on';
    } catch {
        return false;
    }
}

export function setArenaSound(enabled) {
    try {
        localStorage.setItem(STORAGE_KEY, enabled ? 'on' : 'off');
    } catch {
        // ignore
    }

    if (enabled) {
        unlockArenaAudio();
        playArenaSound('toggle');
    }
}

export function unlockArenaAudio() {
    if (! isArenaSoundOn()) {
        return;
    }

    const ctx = audioContext();

    if (! ctx) {
        return;
    }

    if (ctx.state === 'suspended') {
        ctx.resume().catch(() => {});
    }
}

export function bindArenaSoundToggles(root = document) {
    root.querySelectorAll('[data-sound-toggle]').forEach((toggle) => {
        toggle.checked = isArenaSoundOn();
        toggle.addEventListener('change', () => {
            setArenaSound(toggle.checked);
            root.querySelectorAll('[data-sound-toggle]').forEach((other) => {
                other.checked = toggle.checked;
            });
        });
    });
}

export function playArenaSound(kind, classKey = '') {
    if (! isArenaSoundOn()) {
        return;
    }

    const ctx = audioContext();

    if (! ctx) {
        return;
    }

    if (ctx.state === 'suspended') {
        ctx.resume().catch(() => {});
    }

    try {
        playKind(ctx, kind, classKey);
    } catch {
        // ignore
    }
}

function audioContext() {
    if (context) {
        return context;
    }

    const Ctor = window.AudioContext || window.webkitAudioContext;

    if (! Ctor) {
        return null;
    }

    try {
        context = new Ctor();
    } catch {
        return null;
    }

    return context;
}

function playKind(ctx, kind, classKey) {
    switch (kind) {
        case 'whoosh':
            beep(ctx, { type: 'triangle', freq: 900, endFreq: 180, duration: 0.12, gain: 0.03 });
            break;
        case 'hit': {
            const profile = CLASS_HIT[classKey] || { type: 'square', freq: 180, endFreq: 110, duration: 0.1, gain: 0.05 };
            beep(ctx, profile);
            beep(ctx, { type: 'triangle', freq: 420, duration: 0.05, gain: 0.03, delay: 0.02 });
            break;
        }
        case 'heavy': {
            const profile = CLASS_HIT[classKey] || { type: 'sawtooth', freq: 110, endFreq: 70, duration: 0.16, gain: 0.07 };
            beep(ctx, { ...profile, duration: Math.max(0.16, profile.duration || 0.16), gain: (profile.gain || 0.05) * 1.35 });
            beep(ctx, { type: 'square', freq: 70, duration: 0.18, gain: 0.04, delay: 0.03 });
            break;
        }
        case 'heal':
            beep(ctx, { type: 'sine', freq: 523, duration: 0.12, gain: 0.04 });
            beep(ctx, { type: 'sine', freq: 659, duration: 0.14, gain: 0.04, delay: 0.08 });
            break;
        case 'ko':
            beep(ctx, { type: 'sawtooth', freq: 180, endFreq: 70, duration: 0.28, gain: 0.06 });
            break;
        case 'toggle':
            beep(ctx, { type: 'sine', freq: 660, duration: 0.08, gain: 0.04 });
            break;
        case 'level':
        case 'badge':
        case 'duel_challenge':
            beep(ctx, { type: 'triangle', freq: 660, duration: 0.16, gain: 0.04 });
            break;
        case 'rank_up':
        case 'duel_result':
            beep(ctx, { type: 'triangle', freq: 520, duration: 0.18, gain: 0.045 });
            beep(ctx, { type: 'triangle', freq: 780, duration: 0.14, gain: 0.03, delay: 0.1 });
            break;
        default:
            break;
    }
}

function beep(ctx, { type = 'triangle', freq = 440, endFreq = null, duration = 0.12, gain = 0.05, delay = 0 }) {
    const osc = ctx.createOscillator();
    const amp = ctx.createGain();
    const start = ctx.currentTime + delay;

    osc.type = type;
    osc.frequency.setValueAtTime(freq, start);

    if (endFreq) {
        osc.frequency.exponentialRampToValueAtTime(Math.max(1, endFreq), start + duration);
    }

    amp.gain.setValueAtTime(0.0001, start);
    amp.gain.exponentialRampToValueAtTime(gain, start + 0.012);
    amp.gain.exponentialRampToValueAtTime(0.0001, start + duration);
    osc.connect(amp);
    amp.connect(ctx.destination);
    osc.start(start);
    osc.stop(start + duration + 0.02);
}
