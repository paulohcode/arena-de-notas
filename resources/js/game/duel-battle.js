export function duelBattle(payload) {
    return {
        left: { ...payload.left, hp: payload.left.maxHp, hit: false, healed: false },
        right: { ...payload.right, hp: payload.right.maxHp, hit: false, healed: false },
        turns: payload.turns || [],
        winnerId: payload.winnerId,
        viewerId: payload.viewerId,
        gloryWin: payload.gloryWin,
        gloryLoss: payload.gloryLoss,
        index: 0,
        log: [],
        bolts: [],
        floats: [],
        finished: false,
        playing: false,
        boltSeq: 0,
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

        start() {
            if (this.playing) {
                return;
            }

            this.playing = true;
            setTimeout(() => this.tick(), 700);
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
            setTimeout(() => this.tick(), 1100);
        },

        applyTurn(turn, animate) {
            const fromLeft = turn.actor_id === this.left.id;
            const actor = fromLeft ? this.left : this.right;
            const target = fromLeft ? this.right : this.left;

            if (turn.action === 'heal') {
                actor.hp = Math.min(actor.maxHp, turn.actor_hp);
                this.log.push(turn);

                if (animate) {
                    this.spawnBolt(fromLeft ? 'right' : 'left', 'heal');
                    this.spawnFloat(fromLeft ? 'left' : 'right', 'heal', `+${turn.amount}`);
                    actor.healed = true;
                    setTimeout(() => {
                        actor.healed = false;
                    }, 450);
                }
            } else {
                target.hp = Math.max(0, turn.target_hp);
                actor.hp = Math.max(0, turn.actor_hp);
                this.log.push(turn);

                if (animate) {
                    this.spawnBolt(fromLeft ? 'right' : 'left', 'attack');
                    this.spawnFloat(fromLeft ? 'right' : 'left', 'dmg', `-${turn.amount}`);
                    target.hit = true;
                    setTimeout(() => {
                        target.hit = false;
                    }, 450);
                }
            }

            this.$nextTick(() => {
                const box = this.$refs.logBox;
                if (box) {
                    box.scrollTop = box.scrollHeight;
                }
            });
        },

        spawnBolt(dir, kind) {
            const id = ++this.boltSeq;
            this.bolts.push({ id, dir, kind });
            setTimeout(() => {
                this.bolts = this.bolts.filter((bolt) => bolt.id !== id);
            }, 700);
        },

        spawnFloat(side, kind, text) {
            const id = ++this.floatSeq;
            this.floats.push({ id, side, kind, text });
            setTimeout(() => {
                this.floats = this.floats.filter((float) => float.id !== id);
            }, 900);
        },

        finish() {
            this.finished = true;
            this.playing = false;
            this.syncFinalHp();
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
