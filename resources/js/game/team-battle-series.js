import Alpine from 'alpinejs';
import { duelBattle } from './duel-battle';
import { playArenaSound } from './sound';

/**
 * Replay sequencial dos confrontos de uma batalha de guildas.
 */
export function teamBattleSeries(payload) {
    const fights = payload.fights || [];

    return {
        fights,
        fightIndex: 0,
        seriesFinished: false,
        seriesVictoryOpen: false,
        viewerId: payload.viewerId,
        winnerTeamId: payload.winnerTeamId,
        ownTeamId: payload.ownTeamId,
        gloryWin: payload.gloryWin,
        gloryLoss: payload.gloryLoss,
        viewerFought: payload.viewerFought,
        scoreLine: payload.scoreLine,
        winnerTeamName: payload.winnerTeamName,
        battle: null,
        autoAdvanceTimer: null,

        get totalFights() {
            return this.fights.length;
        },

        get iWonSeries() {
            return this.winnerTeamId === this.ownTeamId;
        },

        get seriesHeadline() {
            if (! this.seriesFinished) {
                return this.battle
                    ? `Combate ${this.fightIndex + 1} de ${this.totalFights}`
                    : 'Batalha de guildas';
            }

            return this.iWonSeries ? 'Vitória da guilda!' : 'Derrota da guilda';
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

        init() {
            if (this.totalFights === 0) {
                this.finishSeries();

                return;
            }

            this.loadFight(0);
        },

        clearAutoAdvance() {
            if (this.autoAdvanceTimer) {
                clearTimeout(this.autoAdvanceTimer);
                this.autoAdvanceTimer = null;
            }
        },

        makeBattle(fight) {
            const self = this;
            const battle = duelBattle({
                ...fight,
                viewerId: this.viewerId,
                gloryWin: this.gloryWin,
                gloryLoss: this.gloryLoss,
            });

            battle.finish = function finishWithoutModal() {
                if (this.finished) {
                    return;
                }

                this.finished = true;
                this.playing = false;
                this.syncFinalHp();
                playArenaSound('duel_result');
                this.victoryOpen = false;

                self.clearAutoAdvance();
                self.autoAdvanceTimer = setTimeout(() => {
                    if (! self.seriesFinished) {
                        self.nextFight();
                    }
                }, 1600);
            };

            return Alpine.reactive(battle);
        },

        loadFight(index) {
            this.clearAutoAdvance();
            this.fightIndex = index;
            const fight = this.fights[index];

            if (! fight) {
                this.finishSeries();

                return;
            }

            this.battle = this.makeBattle(fight);

            this.$nextTick(() => {
                if (this.battle && ! this.seriesFinished) {
                    this.battle.start();
                }
            });
        },

        skipCurrent() {
            this.clearAutoAdvance();

            if (this.battle && ! this.battle.finished) {
                this.battle.skip();
            }
        },

        nextFight() {
            this.clearAutoAdvance();

            if (this.fightIndex + 1 >= this.totalFights) {
                this.finishSeries();

                return;
            }

            this.loadFight(this.fightIndex + 1);
        },

        skipAll() {
            this.clearAutoAdvance();

            if (this.battle && this.battle.playing) {
                this.battle.playing = false;
            }

            for (let index = this.fightIndex; index < this.totalFights; index++) {
                const fight = this.fights[index];
                this.fightIndex = index;
                this.battle = this.makeBattle(fight);
                this.battle.skip();
            }

            this.finishSeries();
        },

        finishSeries() {
            if (this.seriesFinished) {
                return;
            }

            this.clearAutoAdvance();
            this.seriesFinished = true;
            playArenaSound('duel_result');
            setTimeout(() => {
                this.seriesVictoryOpen = true;
            }, 400);
        },
    };
}
