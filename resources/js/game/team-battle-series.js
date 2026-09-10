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

        get currentFight() {
            return this.fights[this.fightIndex] || null;
        },

        get totalFights() {
            return this.fights.length;
        },

        get iWonSeries() {
            return this.winnerTeamId === this.ownTeamId;
        },

        get seriesHeadline() {
            if (! this.seriesFinished) {
                return this.currentFight
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
            this.loadFight(0);
        },

        makeBattle(fight) {
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
            };

            return battle;
        },

        loadFight(index) {
            this.fightIndex = index;
            const fight = this.fights[index];
            if (! fight) {
                this.finishSeries();

                return;
            }

            this.battle = this.makeBattle(fight);

            this.$nextTick(() => {
                this.battle.start();
            });
        },

        skipCurrent() {
            if (this.battle && ! this.battle.finished) {
                this.battle.skip();
            }
        },

        nextFight() {
            if (this.fightIndex + 1 >= this.totalFights) {
                this.finishSeries();

                return;
            }

            this.loadFight(this.fightIndex + 1);
        },

        skipAll() {
            while (this.fightIndex < this.totalFights) {
                if (! this.battle || this.battle.finished === false) {
                    const fight = this.fights[this.fightIndex];
                    this.battle = this.makeBattle(fight);
                    this.battle.skip();
                }

                if (this.fightIndex + 1 >= this.totalFights) {
                    break;
                }

                this.fightIndex += 1;
            }

            this.finishSeries();
        },

        finishSeries() {
            if (this.seriesFinished) {
                return;
            }

            this.seriesFinished = true;
            playArenaSound('duel_result');
            setTimeout(() => {
                this.seriesVictoryOpen = true;
            }, 400);
        },
    };
}
