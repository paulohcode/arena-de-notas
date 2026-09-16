import { playArenaSound, unlockArenaAudio } from './sound';

/**
 * Guerra de guildas: os dois times no campo, confrontos em ondas
 * e vários poderes ao mesmo tempo.
 */
export function teamBattleSeries(payload) {
    const fights = (payload.fights || []).map((fight, fightIndex) => ({
        ...fight,
        fightIndex,
        left: { ...fight.left, id: Number(fight.left.id) },
        right: { ...fight.right, id: Number(fight.right.id) },
        winnerId: Number(fight.winnerId),
        turns: fight.turns || [],
    }));
    const timers = new Set();
    const scored = {};

    return {
        fights,
        leftTeam: payload.leftTeam || {},
        rightTeam: payload.rightTeam || {},
        leftFighters: uniqueFighters(fights, 'left'),
        rightFighters: uniqueFighters(fights, 'right'),
        viewerId: Number(payload.viewerId),
        winnerTeamId: Number(payload.winnerTeamId),
        ownTeamId: Number(payload.ownTeamId),
        gloryWin: payload.gloryWin,
        gloryLoss: payload.gloryLoss,
        viewerFought: payload.viewerFought,
        scoreLine: payload.scoreLine,
        winnerTeamName: payload.winnerTeamName,
        waves: groupWaves(fights),
        waveIndex: 0,
        phase: 'intro',
        seriesFinished: false,
        seriesVictoryOpen: false,
        liveScore: { left: 0, right: 0 },
        log: [],
        effects: [],
        floats: [],
        stageFlash: null,
        rumble: false,
        playGen: 0,
        effectSeq: 0,
        floatSeq: 0,

        get totalFights() {
            return this.fights.length;
        },

        get totalWaves() {
            return this.waves.length;
        },

        get iWonSeries() {
            return this.winnerTeamId === this.ownTeamId;
        },

        get leftWon() {
            return this.winnerTeamId === Number(this.leftTeam.id);
        },

        get seriesHeadline() {
            if (this.phase === 'intro') {
                return 'As guildas entram em campo';
            }

            if (! this.seriesFinished) {
                return `Onda ${this.waveIndex + 1} de ${Math.max(this.totalWaves, 1)}`;
            }

            return this.iWonSeries ? 'Vitória da guilda!' : 'Derrota da guilda';
        },

        get statusLine() {
            if (this.seriesFinished) {
                return 'A guerra terminou.';
            }

            if (this.phase === 'intro') {
                return 'Os dois lados se posicionam…';
            }

            const active = this.currentWave().length;
            if (active > 1) {
                return `${active} combates ao mesmo tempo · poderes cruzando o campo`;
            }

            return 'O combate está no centro do campo.';
        },

        get seriesGloryLine() {
            if (! this.viewerFought) {
                return this.iWonSeries
                    ? `${this.winnerTeamName} venceu a batalha`
                    : `${this.winnerTeamName} levou a vitória`;
            }

            return this.iWonSeries
                ? `+${this.gloryWin} Glória para você`
                : `+${this.gloryLoss} Glória para você`;
        },

        currentWave() {
            return this.waves[this.waveIndex] || [];
        },

        findFighter(id) {
            const fighterId = Number(id);

            return this.leftFighters.find((fighter) => fighter.id === fighterId)
                ?? this.rightFighters.find((fighter) => fighter.id === fighterId)
                ?? null;
        },

        hpPct(fighter) {
            if (! fighter || fighter.maxHp <= 0) {
                return 0;
            }

            return Math.max(0, Math.min(100, (fighter.hp / fighter.maxHp) * 100));
        },

        laneY(fighter, side) {
            const team = side === 'left' ? this.leftFighters : this.rightFighters;
            const index = team.findIndex((item) => item.id === fighter.id);
            const count = Math.max(team.length, 1);

            if (count === 1) {
                return 50;
            }

            return ((index + 0.5) / count) * 100;
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

        init() {
            if (this.totalFights === 0) {
                this.finishSeries();

                return;
            }

            this.schedule(() => this.beginClash(), this.prefersReducedMotion() ? 400 : 1800);
        },

        schedule(fn, ms) {
            const id = setTimeout(() => {
                timers.delete(id);
                fn();
            }, ms);

            timers.add(id);

            return id;
        },

        clearTimers() {
            timers.forEach((id) => clearTimeout(id));
            timers.clear();
        },

        bumpGeneration() {
            this.playGen += 1;
            this.clearTimers();
        },

        beginClash() {
            this.phase = 'clash';
            unlockArenaAudio();
            playArenaSound('whoosh');
            this.startWave(0);
        },

        startWave(index) {
            if (index >= this.totalWaves) {
                this.finishSeries();

                return;
            }

            this.waveIndex = index;
            const gen = this.playGen;
            const wave = this.waves[index];

            wave.forEach((matchup) => {
                this.resetMatchupFighters(matchup);
            });

            this.spawnEffect('guild-war-burst', 900, '#f5c56b', 50);

            wave.forEach((matchup, offset) => {
                const delay = this.prefersReducedMotion() ? offset * 80 : 180 + offset * 240;
                this.schedule(() => {
                    if (gen !== this.playGen || this.seriesFinished) {
                        return;
                    }

                    this.tickMatchup(matchup, gen);
                }, delay);
            });
        },

        resetMatchupFighters(matchup) {
            matchup.turnIndex = 0;
            matchup.finished = false;

            const left = this.findFighter(matchup.left.id);
            const right = this.findFighter(matchup.right.id);

            if (left) {
                left.maxHp = Number(matchup.left.maxHp);
                left.hp = Number(matchup.left.maxHp);
                left.active = true;
                left.down = false;
                left.hit = false;
                left.healed = false;
                left.striking = false;
            }

            if (right) {
                right.maxHp = Number(matchup.right.maxHp);
                right.hp = Number(matchup.right.maxHp);
                right.active = true;
                right.down = false;
                right.hit = false;
                right.healed = false;
                right.striking = false;
            }
        },

        tickMatchup(matchup, gen) {
            if (gen !== this.playGen || this.seriesFinished || matchup.finished) {
                return;
            }

            if (matchup.turnIndex >= matchup.turns.length) {
                this.completeMatchup(matchup);
                this.maybeAdvanceWave(gen);

                return;
            }

            this.applyTurn(matchup, matchup.turns[matchup.turnIndex], true);
            matchup.turnIndex += 1;

            const wait = this.prefersReducedMotion() ? 420 : 980;
            this.schedule(() => this.tickMatchup(matchup, gen), wait);
        },

        applyTurn(matchup, turn, animate) {
            const fromLeft = Number(turn.actor_id) === Number(matchup.left.id);
            const actor = this.findFighter(fromLeft ? matchup.left.id : matchup.right.id);
            const target = this.findFighter(fromLeft ? matchup.right.id : matchup.left.id);

            if (! actor || ! target) {
                return;
            }

            if (turn.action === 'heal') {
                actor.hp = Math.min(actor.maxHp, Number(turn.actor_hp));
                this.log.push(turn);

                if (animate) {
                    this.playHeal(actor, fromLeft, turn.amount);
                }
            } else {
                target.hp = Math.max(0, Number(turn.target_hp));
                actor.hp = Math.max(0, Number(turn.actor_hp));
                target.down = target.hp <= 0;
                this.log.push(turn);

                if (animate) {
                    this.playAttack(actor, target, fromLeft, turn.amount, target.hp <= 0);
                }
            }

            if (this.log.length > 48) {
                this.log.splice(0, this.log.length - 48);
            }

            this.$nextTick?.(() => {
                const box = this.$refs?.logBox;
                if (box) {
                    box.scrollTop = box.scrollHeight;
                }
            });
        },

        playAttack(actor, target, fromLeft, amount, isKo) {
            const classKey = actor.classKey || '';
            const heavy = amount >= 16;
            const actorY = this.laneY(actor, fromLeft ? 'left' : 'right');
            const targetY = this.laneY(target, fromLeft ? 'right' : 'left');
            const tone = this.cssTone(actor.classTone || actor.tone);

            if (this.prefersReducedMotion()) {
                playArenaSound(heavy ? 'heavy' : 'hit', classKey);
                if (isKo) {
                    playArenaSound('ko');
                }
                target.hit = true;
                this.schedule(() => {
                    target.hit = false;
                }, 280);

                return;
            }

            const dir = fromLeft ? 'right' : 'left';
            const impactSide = fromLeft ? 'right' : 'left';
            actor.striking = true;
            playArenaSound('whoosh', classKey);
            this.spawnEffect(
                `duel-slash duel-slash--${dir}${heavy ? ' duel-slash--heavy' : ''}${this.fxClass(classKey)}`,
                700,
                tone,
                actorY,
            );
            this.playSupportVolley(fromLeft, actor.id, classKey, tone);

            if (heavy) {
                this.schedule(() => {
                    this.spawnEffect(
                        `duel-slash duel-slash--${dir} duel-slash--follow${this.fxClass(classKey)}`,
                        650,
                        tone,
                        (actorY + targetY) / 2,
                    );
                }, 70);
            }

            this.schedule(() => {
                actor.striking = false;
                playArenaSound(heavy ? 'heavy' : 'hit', classKey);
                this.spawnEffect(
                    `duel-impact duel-impact--${impactSide}${heavy ? ' duel-impact--heavy' : ''}${this.fxClass(classKey)}`,
                    750,
                    tone,
                    targetY,
                );
                this.spawnEffect(`duel-shock duel-shock--${impactSide}${this.fxClass(classKey)}`, 650, tone, targetY);
                this.spawnFloat(impactSide, 'dmg', `-${amount}`, heavy, tone, targetY);
                target.hit = true;
                this.stageFlash = isKo ? 'ko' : (heavy ? 'heavy' : 'hit');
                this.rumble = heavy || isKo;

                if (isKo) {
                    playArenaSound('ko');
                }

                this.schedule(() => {
                    target.hit = false;
                    this.stageFlash = null;
                    this.rumble = false;
                }, 520);
            }, 260);
        },

        playSupportVolley(fromLeft, actorId, classKey, tone) {
            const team = fromLeft ? this.leftFighters : this.rightFighters;
            const allies = team.filter((fighter) => fighter.id !== actorId && fighter.hp > 0);

            if (allies.length === 0) {
                return;
            }

            const count = Math.min(allies.length, allies.length > 3 ? 2 : 1);
            for (let index = 0; index < count; index++) {
                const ally = allies[(actorId + index) % allies.length];
                this.schedule(() => {
                    const dir = fromLeft ? 'right' : 'left';
                    this.spawnEffect(
                        `duel-slash duel-slash--${dir} duel-slash--follow guild-war-fx--support${this.fxClass(ally.classKey || classKey)}`,
                        620,
                        this.cssTone(ally.classTone || tone),
                        this.laneY(ally, fromLeft ? 'left' : 'right'),
                    );
                }, 90 + index * 80);
            }
        },

        playHeal(actor, fromLeft, amount) {
            const side = fromLeft ? 'left' : 'right';
            const tone = this.cssTone(actor.classTone || actor.tone);
            actor.healed = true;
            this.stageFlash = 'heal';
            playArenaSound('heal', actor.classKey || '');
            this.spawnEffect(
                `duel-heal-burst duel-heal-burst--${side}${this.fxClass(actor.classKey)}`,
                900,
                tone,
                this.laneY(actor, side),
            );
            this.spawnFloat(side, 'heal', `+${amount}`, false, tone, this.laneY(actor, side));
            this.schedule(() => {
                actor.healed = false;
                this.stageFlash = null;
            }, 620);
        },

        spawnEffect(className, ttl = 800, tone = null, y = 50) {
            const id = ++this.effectSeq;
            this.effects.push({ id, className, tone, y });
            this.schedule(() => {
                this.effects = this.effects.filter((effect) => effect.id !== id);
            }, ttl);
        },

        spawnFloat(side, kind, text, heavy, tone = null, y = 50) {
            const id = ++this.floatSeq;
            this.floats.push({ id, side, kind, text, heavy: Boolean(heavy), tone, y });
            this.schedule(() => {
                this.floats = this.floats.filter((item) => item.id !== id);
            }, 1100);
        },

        completeMatchup(matchup) {
            if (matchup.finished) {
                return;
            }

            matchup.finished = true;

            const left = this.findFighter(matchup.left.id);
            const right = this.findFighter(matchup.right.id);
            const last = matchup.turns[matchup.turns.length - 1];

            if (last && left && right) {
                if (Number(last.actor_id) === left.id) {
                    left.hp = Math.max(0, Number(last.actor_hp));
                    right.hp = Math.max(0, Number(last.target_hp));
                } else {
                    right.hp = Math.max(0, Number(last.actor_hp));
                    left.hp = Math.max(0, Number(last.target_hp));
                }
                left.down = left.hp <= 0;
                right.down = right.hp <= 0;
            }

            if (left) {
                left.active = false;
                left.striking = false;
            }
            if (right) {
                right.active = false;
                right.striking = false;
            }

            if (! scored[matchup.fightIndex]) {
                scored[matchup.fightIndex] = true;
                if (matchup.winnerId === Number(matchup.left.id)) {
                    this.liveScore = { left: this.liveScore.left + 1, right: this.liveScore.right };
                } else {
                    this.liveScore = { left: this.liveScore.left, right: this.liveScore.right + 1 };
                }
            }
        },

        maybeAdvanceWave(gen) {
            if (gen !== this.playGen || this.seriesFinished) {
                return;
            }

            const wave = this.currentWave();
            if (wave.some((matchup) => ! matchup.finished)) {
                return;
            }

            this.schedule(() => {
                if (gen !== this.playGen || this.seriesFinished) {
                    return;
                }

                this.startWave(this.waveIndex + 1);
            }, this.prefersReducedMotion() ? 350 : 900);
        },

        skipCurrent() {
            if (this.seriesFinished) {
                return;
            }

            if (this.phase === 'intro') {
                this.bumpGeneration();
                this.beginClash();

                return;
            }

            this.bumpGeneration();
            this.currentWave().forEach((matchup) => {
                this.finishMatchupSilent(matchup);
            });
            this.stageFlash = null;
            this.rumble = false;
            this.effects = [];
            this.floats = [];

            if (this.waveIndex + 1 >= this.totalWaves) {
                this.finishSeries();

                return;
            }

            this.startWave(this.waveIndex + 1);
        },

        skipAll() {
            if (this.seriesFinished) {
                return;
            }

            this.bumpGeneration();
            this.phase = 'clash';
            this.fights.forEach((matchup) => this.finishMatchupSilent(matchup));
            this.effects = [];
            this.floats = [];
            this.stageFlash = null;
            this.rumble = false;
            this.finishSeries();
        },

        finishMatchupSilent(matchup) {
            if (matchup.finished) {
                return;
            }

            if (typeof matchup.turnIndex !== 'number') {
                this.resetMatchupFighters(matchup);
            }

            while (matchup.turnIndex < matchup.turns.length) {
                this.applyTurn(matchup, matchup.turns[matchup.turnIndex], false);
                matchup.turnIndex += 1;
            }

            this.completeMatchup(matchup);
        },

        finishSeries() {
            if (this.seriesFinished) {
                return;
            }

            this.bumpGeneration();
            this.seriesFinished = true;
            this.phase = 'result';
            playArenaSound('duel_result');
            this.schedule(() => {
                this.seriesVictoryOpen = true;
            }, 400);
        },
    };
}

function uniqueFighters(fights, side) {
    const seen = new Set();
    const fighters = [];

    fights.forEach((fight) => {
        const source = fight[side];
        const id = Number(source.id);

        if (seen.has(id)) {
            return;
        }

        seen.add(id);
        fighters.push({
            id,
            name: source.name,
            arena: source.arena,
            class: source.class,
            classKey: source.classKey || '',
            icon: source.icon,
            tone: source.tone,
            classTone: source.classTone,
            maxHp: Number(source.maxHp),
            hp: Number(source.maxHp),
            petName: source.petName || null,
            petAura: source.petAura || null,
            petSprite: source.petSprite || null,
            petGif: source.petGif || null,
            hit: false,
            healed: false,
            striking: false,
            active: false,
            down: false,
        });
    });

    return fighters;
}

function groupWaves(fights) {
    const queued = [...fights];
    const waves = [];

    while (queued.length > 0) {
        const wave = [];
        const busy = new Set();
        const nextQueue = [];

        queued.forEach((fight) => {
            const leftId = Number(fight.left.id);
            const rightId = Number(fight.right.id);

            if (busy.has(leftId) || busy.has(rightId)) {
                nextQueue.push(fight);

                return;
            }

            busy.add(leftId);
            busy.add(rightId);
            wave.push(fight);
        });

        waves.push(wave);
        queued.splice(0, queued.length, ...nextQueue);
    }

    return waves;
}
