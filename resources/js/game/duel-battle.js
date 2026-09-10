export function duelBattle(payload) {
    return {
        left: { ...payload.left, hp: payload.left.maxHp, hit: false, healed: false, striking: false },
        right: { ...payload.right, hp: payload.right.maxHp, hit: false, healed: false, striking: false },
        turns: payload.turns || [],
        winnerId: payload.winnerId,
        viewerId: payload.viewerId,
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

        start() {
            if (this.playing) {
                return;
            }

            this.playing = true;
            setTimeout(() => this.tick(), 800);
        },

        skip() {
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
            setTimeout(() => this.tick(), this.prefersReducedMotion() ? 400 : 1450);
        },

        applyTurn(turn, animate) {
            const fromLeft = turn.actor_id === this.left.id;
            const actor = fromLeft ? this.left : this.right;
            const target = fromLeft ? this.right : this.left;

            if (turn.action === 'heal') {
                actor.hp = Math.min(actor.maxHp, turn.actor_hp);
                this.log.push(turn);

                if (animate) {
                    this.playHeal(fromLeft, turn.amount);
                }
            } else {
                target.hp = Math.max(0, turn.target_hp);
                actor.hp = Math.max(0, turn.actor_hp);
                this.log.push(turn);

                if (animate) {
                    this.playAttack(fromLeft, turn.amount, turn.target_hp <= 0);
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
            if (this.prefersReducedMotion()) {
                const target = fromLeft ? this.right : this.left;
                target.hit = true;
                setTimeout(() => {
                    target.hit = false;
                }, 280);

                return;
            }

            const actor = fromLeft ? this.left : this.right;
            const target = fromLeft ? this.right : this.left;
            const dir = fromLeft ? 'right' : 'left';
            const impactSide = fromLeft ? 'right' : 'left';
            const heavy = amount >= 16;

            actor.striking = true;
            this.spawnEffect(`duel-slash duel-slash--${dir}${heavy ? ' duel-slash--heavy' : ''}`, 700);
            if (heavy) {
                setTimeout(() => {
                    this.spawnEffect(`duel-slash duel-slash--${dir} duel-slash--follow`, 650);
                }, 80);
            }

            setTimeout(() => {
                actor.striking = false;
                this.spawnEffect(`duel-impact duel-impact--${impactSide}${heavy ? ' duel-impact--heavy' : ''}`, 750);
                this.spawnEffect(`duel-shock duel-shock--${impactSide}`, 650);
                this.spawnFloat(impactSide, 'dmg', `-${amount}`, heavy);
                target.hit = true;
                this.stageFlash = isKo ? 'ko' : (heavy ? 'heavy' : 'hit');
                setTimeout(() => {
                    target.hit = false;
                    this.stageFlash = null;
                }, 520);
            }, 280);
        },

        playHeal(fromLeft, amount) {
            const actor = fromLeft ? this.left : this.right;
            const side = fromLeft ? 'left' : 'right';

            actor.healed = true;
            this.stageFlash = 'heal';
            this.spawnEffect(`duel-heal-burst duel-heal-burst--${side}`, 900);
            this.spawnFloat(side, 'heal', `+${amount}`, false);
            setTimeout(() => {
                actor.healed = false;
                this.stageFlash = null;
            }, 620);
        },

        spawnEffect(className, ttl = 800) {
            const id = ++this.effectSeq;
            this.effects.push({ id, className });
            setTimeout(() => {
                this.effects = this.effects.filter((effect) => effect.id !== id);
            }, ttl);
        },

        spawnFloat(side, kind, text, heavy) {
            const id = ++this.floatSeq;
            this.floats.push({ id, side, kind, text, heavy: Boolean(heavy) });
            setTimeout(() => {
                this.floats = this.floats.filter((item) => item.id !== id);
            }, 1100);
        },

        finish() {
            this.finished = true;
            this.playing = false;
            this.syncFinalHp();
            setTimeout(() => {
                this.victoryOpen = true;
            }, 520);
        },

        syncFinalHp() {
            if (! this.turns.length) {
                return;
            }

            const last = this.turns[this.turns.length - 1];

            if (last.actor_id === this.left.id) {
                this.left.hp = Math.max(0, last.actor_hp);
                this.right.hp = Math.max(0, last.target_hp);
            } else {
                this.right.hp = Math.max(0, last.actor_hp);
                this.left.hp = Math.max(0, last.target_hp);
            }
        },
    };
}
