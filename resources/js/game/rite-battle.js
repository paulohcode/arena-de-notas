import { playArenaSound, unlockArenaAudio } from './sound';

export function riteBattleSeries(payload) {
    const phaseOrder = { julgamento: 0, prova: 1, veredito: 2 };
    const fights = (payload.fights || []).map((fight) => ({
        ...fight,
        left: { ...fight.left, hp: fight.left.maxHp, hit: false, healed: false, striking: false },
        right: {
            ...fight.right,
            hp: fight.bossHpStart ?? fight.right.maxHp,
            maxHp: payload.bossMaxHp || fight.right.maxHp,
            hit: false,
            healed: false,
            striking: false,
            isBoss: true,
        },
    }));

    return {
        fights,
        fightIndex: 0,
        turnIndex: 0,
        left: null,
        right: null,
        currentFight: null,
        currentPhase: 'julgamento',
        raidHp: Number(payload.bossHpStart || payload.bossMaxHp || 0),
        bossMaxHp: Number(payload.bossMaxHp || 1),
        bossName: payload.bossName || 'Rito',
        bossIcon: payload.bossIcon || '🌑',
        viewerId: Number(payload.viewerId),
        broken: Boolean(payload.broken),
        relicsWin: payload.relicsWin,
        relicsLoss: payload.relicsLoss,
        rewardLabel: payload.rewardLabel || 'Relíquias',
        marksApplied: payload.marksApplied || 0,
        effects: [],
        stageFlash: null,
        effectSeq: 0,
        playing: false,
        seriesFinished: false,
        tickTimer: null,

        get raidHpPct() {
            return Math.max(0, Math.min(100, (this.raidHp / this.bossMaxHp) * 100));
        },

        get raidHpLabel() {
            return `${Math.max(0, this.raidHp)}/${this.bossMaxHp}`;
        },

        get leftPct() {
            if (! this.left) {
                return 0;
            }

            return Math.max(0, Math.min(100, (this.left.hp / this.left.maxHp) * 100));
        },

        get phaseRank() {
            return phaseOrder[this.currentPhase] ?? 0;
        },

        get phaseLabel() {
            if (this.currentPhase === 'veredito') {
                return 'Veredito';
            }
            if (this.currentPhase === 'prova') {
                return 'Prova';
            }
            return 'Julgamento';
        },

        get headline() {
            if (! this.seriesFinished) {
                return 'Assalto em andamento';
            }

            return this.broken ? 'Rito quebrado!' : 'O Rito resistiu';
        },

        get statusLine() {
            if (this.seriesFinished) {
                const relics = this.broken ? this.relicsWin : this.relicsLoss;

                return `+${relics} ${this.rewardLabel} para quem lutou · ${this.marksApplied} Marca(s)`;
            }

            if (! this.currentFight) {
                return 'Preparando a primeira onda…';
            }

            return `${this.currentFight.label} · turno ${this.turnIndex} · dano da onda ${this.currentFight.damage}`;
        },

        prefersReducedMotion() {
            return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        },

        start() {
            if (this.playing || this.seriesFinished || ! this.fights.length) {
                if (! this.fights.length) {
                    this.seriesFinished = true;
                }

                return;
            }

            this.playing = true;
            unlockArenaAudio();
            playArenaSound('boss_phase');
            this.loadFight(0);
            this.tickTimer = setTimeout(() => this.tick(), 900);
        },

        loadFight(index) {
            this.fightIndex = index;
            this.turnIndex = 0;
            this.currentFight = this.fights[index] || null;
            if (! this.currentFight) {
                return;
            }

            this.left = { ...this.currentFight.left, hp: this.currentFight.left.maxHp, hit: false, healed: false, striking: false };
            this.right = {
                ...this.currentFight.right,
                hp: this.currentFight.bossHpStart,
                maxHp: this.bossMaxHp,
                hit: false,
                healed: false,
                striking: false,
                isBoss: true,
            };
            this.raidHp = this.currentFight.bossHpStart;
            this.currentPhase = 'julgamento';
            this.effects = [];
        },

        skipFight() {
            if (this.seriesFinished || ! this.currentFight) {
                return;
            }

            while (this.turnIndex < this.currentFight.turns.length) {
                this.applyTurn(this.currentFight.turns[this.turnIndex], false);
                this.turnIndex += 1;
            }

            this.advanceFight();
        },

        skipAll() {
            if (this.seriesFinished) {
                return;
            }

            this.playing = false;
            if (this.tickTimer) {
                clearTimeout(this.tickTimer);
                this.tickTimer = null;
            }

            for (let i = this.fightIndex; i < this.fights.length; i += 1) {
                this.loadFight(i);
                while (this.turnIndex < this.currentFight.turns.length) {
                    this.applyTurn(this.currentFight.turns[this.turnIndex], false);
                    this.turnIndex += 1;
                }
            }

            this.finishSeries();
        },

        tick() {
            if (! this.playing || this.seriesFinished || ! this.currentFight) {
                return;
            }

            if (this.turnIndex >= this.currentFight.turns.length) {
                this.advanceFight();

                return;
            }

            this.applyTurn(this.currentFight.turns[this.turnIndex], true);
            this.turnIndex += 1;
            this.tickTimer = setTimeout(() => this.tick(), this.prefersReducedMotion() ? 400 : 1200);
        },

        advanceFight() {
            this.raidHp = this.currentFight?.bossHpEnd ?? this.raidHp;
            const next = this.fightIndex + 1;

            if (next >= this.fights.length || this.raidHp <= 0) {
                this.finishSeries();

                return;
            }

            this.loadFight(next);
            this.tickTimer = setTimeout(() => this.tick(), this.prefersReducedMotion() ? 500 : 1100);
        },

        applyTurn(turn, animate) {
            if (! this.left || ! this.right) {
                return;
            }

            const fromLeft = Number(turn.actor_id) === this.left.id;
            const actor = fromLeft ? this.left : this.right;
            const target = fromLeft ? this.right : this.left;

            if (turn.phase) {
                const next = String(turn.phase);
                if ((phaseOrder[next] ?? 0) >= (phaseOrder[this.currentPhase] ?? 0) && next !== this.currentPhase) {
                    this.currentPhase = next;
                    if (animate) {
                        playArenaSound('boss_phase', actor.classKey || '');
                    }
                }
            }

            if (turn.action === 'heal') {
                actor.hp = Math.min(actor.maxHp, Number(turn.actor_hp));
                if (! fromLeft) {
                    this.raidHp = actor.hp;
                }
                if (animate) {
                    actor.healed = true;
                    playArenaSound('heal', actor.classKey || '');
                    this.stageFlash = 'heal';
                    setTimeout(() => {
                        actor.healed = false;
                        this.stageFlash = null;
                    }, 500);
                }
            } else {
                target.hp = Math.max(0, Number(turn.target_hp));
                actor.hp = Math.max(0, Number(turn.actor_hp));
                if (fromLeft) {
                    this.raidHp = target.hp;
                } else {
                    this.raidHp = Math.max(this.raidHp, actor.hp);
                }

                if (animate) {
                    const heavy = (! fromLeft && this.currentPhase === 'veredito') || turn.amount >= 16;
                    actor.striking = true;
                    playArenaSound('whoosh', actor.classKey || '');
                    setTimeout(() => {
                        actor.striking = false;
                        playArenaSound(heavy ? 'heavy' : 'hit', actor.classKey || '');
                        target.hit = true;
                        this.stageFlash = heavy ? 'heavy' : 'hit';
                        if (Number(turn.target_hp) <= 0) {
                            playArenaSound('ko');
                            this.stageFlash = 'ko';
                        }
                        setTimeout(() => {
                            target.hit = false;
                            this.stageFlash = null;
                        }, 450);
                    }, 220);
                }
            }
        },

        finishSeries() {
            this.seriesFinished = true;
            this.playing = false;
            this.raidHp = Number(payload.bossHpEnd ?? this.raidHp);
            if (this.tickTimer) {
                clearTimeout(this.tickTimer);
                this.tickTimer = null;
            }
            playArenaSound(this.broken ? 'duel_result' : 'ko');
        },
    };
}
