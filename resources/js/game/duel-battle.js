import { playArenaSound, unlockArenaAudio } from './sound';

export function duelBattle(payload) {
    const leftId = Number(payload.left?.id);
    const rightId = Number(payload.right?.id);

    return {
        left: { ...payload.left, id: leftId, hp: payload.left.maxHp, hit: false, healed: false, striking: false },
        right: { ...payload.right, id: rightId, hp: payload.right.maxHp, hit: false, healed: false, striking: false },
        turns: payload.turns || [],
        winnerId: Number(payload.winnerId),
        viewerId: Number(payload.viewerId),
        gloryWin: payload.gloryWin,
        gloryLoss: payload.gloryLoss,
        index: 0,
        log: [],
        effects: [],
        floats: [],
        finished: false,
        playing: false,
        victoryOpen: false,
        stageFlash: null,
        effectSeq: 0,
        floatSeq: 0,
        tickTimer: null,

        get leftPct() {
            return Math.max(0, Math.min(100, (this.left.hp / this.left.maxHp) * 100));
        },

        get rightPct() {
            return Math.max(0, Math.min(100, (this.right.hp / this.right.maxHp) * 100));
        },

        get iWon() {
            return this.winnerId === this.viewerId;
        },

        get headline() {
            if (! this.finished) {
                return 'Combate em andamento';
            }

            return this.iWon ? 'Vitória!' : 'Derrota';
        },

        get statusLine() {
            if (this.finished) {
                return 'Combate encerrado.';
            }

            if (this.index === 0) {
                return 'Preparando o primeiro golpe…';
            }

            return `Turno ${this.index} de ${this.turns.length}`;
        },

        get resultLine() {
            return this.iWon
                ? `+${this.gloryWin} Glória`
                : `+${this.gloryLoss} Glória`;
        },

        get winner() {
            return this.winnerId === this.left.id ? this.left : this.right;
        },

        get victoryGloryLine() {
            const name = this.winner.arena || this.winner.name;

            if (this.iWon) {
                return `+${this.gloryWin} Glória para você`;
            }

            return `${name} leva +${this.gloryWin} Glória`;
        },

        prefersReducedMotion() {
            return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        },

        cssTone(value) {
            return /^#[0-9a-fA-F]{6}$/.test(value || '') ? value : '#f5c56b';
        },

        fxClass(classKey) {
            return /^[a-z0-9_]+$/.test(classKey || '') ? ` duel-fx--${classKey}` : '';
        },

        actorTone(actor) {
            return this.cssTone(actor.classTone || actor.tone);
        },

        isLeftActor(actorId) {
            return Number(actorId) === this.left.id;
        },

        start() {
            if (this.playing || this.finished) {
                return;
            }

            this.playing = true;
            unlockArenaAudio();
            this.tickTimer = setTimeout(() => this.tick(), 800);
        },

        skip() {
            if (this.finished) {
                return;
            }

            this.playing = false;
            if (this.tickTimer) {
                clearTimeout(this.tickTimer);
                this.tickTimer = null;
            }

            while (this.index < this.turns.length) {
                this.applyTurn(this.turns[this.index], false);
                this.index += 1;
            }

            this.finish();
        },

        tick() {
            if (! this.playing || this.finished) {
                return;
            }

            if (this.index >= this.turns.length) {
                this.finish();

                return;
            }

            this.applyTurn(this.turns[this.index], true);
            this.index += 1;
            this.tickTimer = setTimeout(() => this.tick(), this.prefersReducedMotion() ? 500 : 1450);
        },

        applyTurn(turn, animate) {
            const fromLeft = this.isLeftActor(turn.actor_id);
            const actor = fromLeft ? this.left : this.right;
            const target = fromLeft ? this.right : this.left;

            if (turn.action === 'heal') {
                actor.hp = Math.min(actor.maxHp, Number(turn.actor_hp));
                this.log.push(turn);

                if (animate) {
                    this.playHeal(fromLeft, turn.amount);
                }
            } else {
                target.hp = Math.max(0, Number(turn.target_hp));
                actor.hp = Math.max(0, Number(turn.actor_hp));
                this.log.push(turn);

                if (animate) {
                    this.playAttack(fromLeft, turn.amount, Number(turn.target_hp) <= 0);
                }
            }

            this.$nextTick(() => {
                const box = this.$refs.logBox;
                if (box) {
                    box.scrollTop = box.scrollHeight;
                }
            });
        },

        playAttack(fromLeft, amount, isKo) {
            const actor = fromLeft ? this.left : this.right;
            const target = fromLeft ? this.right : this.left;
            const classKey = actor.classKey || '';
            const heavy = amount >= 16;

            if (this.prefersReducedMotion()) {
                playArenaSound(heavy ? 'heavy' : 'hit', classKey);
                if (isKo) {
                    playArenaSound('ko');
                }
                target.hit = true;
                setTimeout(() => {
                    target.hit = false;
                }, 280);

                return;
            }

            const dir = fromLeft ? 'right' : 'left';
            const impactSide = fromLeft ? 'right' : 'left';
            const tone = this.actorTone(actor);
            const slashClass = `duel-slash duel-slash--${dir}${heavy ? ' duel-slash--heavy' : ''}${this.fxClass(classKey)}`;

            actor.striking = true;
            playArenaSound('whoosh', classKey);
            this.spawnEffect(slashClass, 700, tone);

            if (heavy) {
                setTimeout(() => {
                    this.spawnEffect(`duel-slash duel-slash--${dir} duel-slash--follow${this.fxClass(classKey)}`, 650, tone);
                }, 80);
            }

            setTimeout(() => {
                actor.striking = false;
                playArenaSound(heavy ? 'heavy' : 'hit', classKey);
                this.spawnEffect(`duel-impact duel-impact--${impactSide}${heavy ? ' duel-impact--heavy' : ''}${this.fxClass(classKey)}`, 750, tone);
                this.spawnEffect(`duel-shock duel-shock--${impactSide}${this.fxClass(classKey)}`, 650, tone);
                this.spawnFloat(impactSide, 'dmg', `-${amount}`, heavy, tone);
                target.hit = true;
                this.stageFlash = isKo ? 'ko' : (heavy ? 'heavy' : 'hit');

                if (isKo) {
                    playArenaSound('ko');
                }

                setTimeout(() => {
                    target.hit = false;
                    this.stageFlash = null;
                }, 520);
            }, 280);
        },

        playHeal(fromLeft, amount) {
            const actor = fromLeft ? this.left : this.right;
            const side = fromLeft ? 'left' : 'right';
            const tone = this.actorTone(actor);

            actor.healed = true;
            this.stageFlash = 'heal';
            playArenaSound('heal', actor.classKey || '');
            this.spawnEffect(`duel-heal-burst duel-heal-burst--${side}${this.fxClass(actor.classKey)}`, 900, tone);
            this.spawnFloat(side, 'heal', `+${amount}`, false, tone);
            setTimeout(() => {
                actor.healed = false;
                this.stageFlash = null;
            }, 620);
        },

        spawnEffect(className, ttl = 800, tone = null) {
            const id = ++this.effectSeq;
            this.effects.push({ id, className, tone });
            setTimeout(() => {
                this.effects = this.effects.filter((effect) => effect.id !== id);
            }, ttl);
        },

        spawnFloat(side, kind, text, heavy, tone = null) {
            const id = ++this.floatSeq;
            this.floats.push({ id, side, kind, text, heavy: Boolean(heavy), tone });
            setTimeout(() => {
                this.floats = this.floats.filter((item) => item.id !== id);
            }, 1100);
        },

        finish() {
            if (this.finished) {
                return;
            }

            this.finished = true;
            this.playing = false;
            if (this.tickTimer) {
                clearTimeout(this.tickTimer);
                this.tickTimer = null;
            }
            this.syncFinalHp();
            playArenaSound('duel_result');
            // Dá tempo de ver o palco final antes do modal cobrir a tela.
            setTimeout(() => {
                this.victoryOpen = true;
            }, 1400);
        },

        syncFinalHp() {
            if (! this.turns.length) {
                return;
            }

            const last = this.turns[this.turns.length - 1];

            if (this.isLeftActor(last.actor_id)) {
                this.left.hp = Math.max(0, Number(last.actor_hp));
                this.right.hp = Math.max(0, Number(last.target_hp));
            } else {
                this.right.hp = Math.max(0, Number(last.actor_hp));
                this.left.hp = Math.max(0, Number(last.target_hp));
            }
        },
    };
}
