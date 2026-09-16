@extends('layouts.game')

@section('title', ($vigil->isBossChallenge() ? 'Chefão' : 'Vigília').' — '.$season->name)

@php
    $log = $vigil->log ?? [];
    $fighters = $log['fighters'] ?? [];
    $challengerSnap = $fighters['challenger'] ?? null;
    $opponentSnap = $fighters['opponent'] ?? null;
    $turns = $log['turns'] ?? [];
    $bossMeta = $season->bossMeta() ?? [];
    $isResolved = $challengerSnap && $opponentSnap;
    $lootTotals = $vigil->lootTotals();
    $jackpotTotals = $vigil->jackpotTotals();
    $jackpotFillFrom = $jackpotTotals
        ? \App\Support\BossArchetypeCatalog::bossBankFillPercent($jackpotTotals['bank_before'])
        : 0.0;
    $jackpotFillTo = $jackpotTotals
        ? \App\Support\BossArchetypeCatalog::bossBankFillPercent(
            max(0, $jackpotTotals['bank_before'] - $jackpotTotals['relics'])
        )
        : 0.0;

    $staffView = $staffView ?? false;
    $backUrl = $backUrl ?? route('student.arena.index').'#rito-temporada';
    $backLabel = $backLabel ?? 'Voltar à arena';
    $viewerForBattle = isset($viewerId) ? (int) $viewerId : (int) $student->id;
    $kicker = $staffView
        ? ($vigil->isStaffChallenge() ? 'Mesa do chefão · Provocação' : 'Mesa do chefão · Replay')
        : ($vigil->isBossChallenge() ? 'Desafio pago · Chefão completo' : 'Vigília · Sombra do Rito');

    $battlePayload = $isResolved ? [
        'left' => [
            'id' => $vigil->student->id,
            'name' => $vigil->student->name,
            'arena' => $vigil->student->arenaName(),
            'class' => $challengerSnap['class'],
            'classKey' => (string) ($vigil->student->character_class ?? ''),
            'icon' => $vigil->student->avatarIcon(),
            'tone' => $vigil->student->avatarTone(),
            'classTone' => $vigil->student->characterClassTone(),
            'maxHp' => (int) $challengerSnap['max_hp'],
            'isBoss' => false,
        ],
        'right' => [
            'id' => (int) ($opponentSnap['id'] ?? \App\Services\ArenaCombatService::BOSS_FIGHTER_ID),
            'name' => $opponentSnap['name'] ?? ($bossMeta['name'] ?? 'Sombra'),
            'arena' => $opponentSnap['arena_name'] ?? null,
            'class' => $opponentSnap['class'] ?? ($bossMeta['name'] ?? 'Chefão'),
            'classKey' => (string) ($opponentSnap['class_key'] ?? $season->boss_archetype ?? 'boss'),
            'icon' => $opponentSnap['icon'] ?? ($bossMeta['icon'] ?? '🌑'),
            'tone' => $opponentSnap['tone'] ?? ($bossMeta['tone'] ?? '#6d28d9'),
            'classTone' => $opponentSnap['tone'] ?? ($bossMeta['tone'] ?? '#6d28d9'),
            'maxHp' => (int) $opponentSnap['max_hp'],
            'isBoss' => true,
        ],
        'turns' => $turns,
        'winnerId' => (int) ($log['winner_id'] ?? 0),
        'viewerId' => $viewerForBattle,
        'gloryWin' => \App\Support\BossArchetypeCatalog::GLORY_WIN,
        'gloryLoss' => \App\Support\BossArchetypeCatalog::GLORY_LOSS,
        'gloryAwarded' => (int) $vigil->glory,
        'rewardLabel' => $vigil->isBossChallenge()
            ? \App\Models\GameCurrency::label('relics')
            : \App\Models\GameCurrency::label('glory'),
        'mode' => $staffView ? 'boss_desk' : ($vigil->isBossChallenge() ? 'boss_challenge' : 'vigil'),
        'markEarned' => (bool) $vigil->mark_earned,
        'feeRelics' => (int) $vigil->fee_relics,
        'loot' => $lootTotals,
        'jackpot' => $jackpotTotals,
        'currencies' => [
            'glory' => [
                'label' => \App\Models\GameCurrency::label('glory'),
                'icon' => \App\Models\GameCurrency::icon('glory'),
            ],
            'relics' => [
                'label' => \App\Models\GameCurrency::label('relics'),
                'icon' => \App\Models\GameCurrency::icon('relics'),
            ],
            'seals' => [
                'label' => \App\Models\GameCurrency::label('seals'),
                'icon' => \App\Models\GameCurrency::icon('seals'),
            ],
            'auras' => [
                'label' => \App\Models\GameCurrency::label('auras'),
                'icon' => \App\Models\GameCurrency::icon('auras'),
            ],
        ],
    ] : null;

    $winnerReasonLabel = match ($log['winner_reason'] ?? null) {
        'ko' => 'O rival ficou sem vida.',
        'hp' => 'O tempo acabou: mais HP restante.',
        'spd' => 'Empate de HP: mais velocidade.',
        'spd_tie' => 'Empate total: o desafiante venceu.',
        default => null,
    };
@endphp

@section('content')
@if($vigil->isPending())
    @php
        $statusUrl = $statusUrl ?? null;
        $bossMeta = $season->bossMeta() ?? [];
    @endphp
    <div
        class="game-card p-8 text-center max-w-xl mx-auto reveal"
        @if($statusUrl && $staffView)
            data-duel-waiting
            x-data="{
                statusUrl: {{ \Illuminate\Support\Js::from($statusUrl) }},
                pulling: false,
                async poll() {
                    if (this.pulling) { return; }
                    this.pulling = true;
                    try {
                        const response = await fetch(this.statusUrl, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
                        if (! response.ok) return;
                        const data = await response.json();
                        if (data.redirect) {
                            window.ArenaGoToBattle
                                ? window.ArenaGoToBattle(data.redirect)
                                : window.location.assign(data.redirect);
                        }
                    } catch (e) {}
                    finally { this.pulling = false; }
                },
                init() {
                    this.poll();
                    setInterval(() => this.poll(), 2000);
                }
            }"
        @endif
    >
        <p class="hero-kicker !mb-2">Sala de espera</p>
        <h1 class="font-display text-3xl text-violet-200 mb-3">
            {{ $staffView ? 'Aguardando o aluno aceitar' : 'O chefão te desafiou' }}
        </h1>
        <div class="flex items-center justify-center gap-6 my-8">
            <div class="text-center">
                @include('partials.player-avatar', ['student' => $vigil->student, 'size' => 'lg'])
                <p class="mt-2 font-semibold">{{ $vigil->student->arenaName() ?: $vigil->student->name }}</p>
            </div>
            <p class="font-display text-2xl text-violet-200/50">VS</p>
            <div class="text-center">
                <div class="duel-portrait duel-portrait--boss mx-auto" style="--portrait-tone: {{ $bossMeta['tone'] ?? '#6d28d9' }}">
                    <span class="text-5xl md:text-6xl">{{ $bossMeta['icon'] ?? '🌑' }}</span>
                </div>
                <p class="mt-2 font-semibold">{{ $season->bossDisplayName() }}</p>
            </div>
        </div>

        @if($staffView)
            <p class="text-amber-100/65 mb-2">Quando {{ $vigil->student->arenaName() ?: $vigil->student->name }} aceitar, o combate abre sozinho nesta tela.</p>
            <p class="text-xs text-cyan-300/70 animate-pulse">Escutando a arena…</p>
        @else
            <p class="text-amber-100/65 mb-4">Aceite para enfrentar o chefão. Recusar não gera punição.</p>
            <div class="flex flex-wrap justify-center gap-3">
                <form method="POST" action="{{ route('student.arena.vigil.accept', $vigil) }}">
                    @csrf
                    <button class="game-btn" type="submit">Aceitar batalha</button>
                </form>
                <form method="POST" action="{{ route('student.arena.vigil.decline', $vigil) }}">
                    @csrf
                    <button class="game-btn-ghost" type="submit">Recusar</button>
                </form>
            </div>
        @endif

        <a class="game-btn-ghost inline-block mt-6" href="{{ $backUrl }}">{{ $backLabel }}</a>
    </div>
@elseif($isResolved)
    <div
        class="space-y-6"
        x-data="duelBattle({{ \Illuminate\Support\Js::from($battlePayload) }})"
        x-init="start()"
    >
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="hero-kicker !mb-1">{{ $kicker }}</p>
                <h1 class="font-display text-3xl md:text-4xl text-violet-200" x-text="headline"></h1>
                <p class="text-amber-100/60 mt-1">{{ $season->bossDisplayName() }} · vs {{ $vigil->student->arenaName() ?: $vigil->student->name }}</p>
            </div>
            <a class="game-btn-ghost" href="{{ $backUrl }}">{{ $backLabel }}</a>
        </div>

        <div
            class="duel-stage duel-stage--boss game-card overflow-hidden"
            :class="{
                'duel-stage--flash': stageFlash === 'hit',
                'duel-stage--heavy': stageFlash === 'heavy',
                'duel-stage--ko': stageFlash === 'ko',
                'duel-stage--heal': stageFlash === 'heal',
                'duel-stage--phase-prova': currentPhase === 'prova',
                'duel-stage--phase-veredito': currentPhase === 'veredito'
            }"
        >
            <div class="duel-stage__floor" aria-hidden="true"></div>
            <div class="px-4 pt-4 relative z-10">
                <div class="boss-phase-bar" aria-hidden="true">
                    <span class="boss-phase-bar__seg" :class="{ 'is-active': phaseRank >= 0, 'is-current': currentPhase === 'julgamento' }">Julgamento</span>
                    <span class="boss-phase-bar__seg" :class="{ 'is-active': phaseRank >= 1, 'is-current': currentPhase === 'prova' }">Prova</span>
                    <span class="boss-phase-bar__seg" :class="{ 'is-active': phaseRank >= 2, 'is-current': currentPhase === 'veredito' }">Veredito</span>
                </div>
            </div>
            <div class="duel-fx" aria-hidden="true">
                <template x-for="fx in effects" :key="fx.id">
                    <span class="duel-fx__item" :class="fx.className" :style="fx.tone ? { '--duel-fx': fx.tone } : {}"></span>
                </template>
                <template x-for="float in floats" :key="float.id">
                    <span
                        class="duel-float"
                        :class="{
                            'duel-float--left': float.side === 'left',
                            'duel-float--right': float.side === 'right',
                            'duel-float--heal': float.kind === 'heal',
                            'duel-float--dmg': float.kind === 'dmg',
                            'duel-float--heavy': float.heavy
                        }"
                        :style="float.tone ? { '--duel-fx': float.tone } : {}"
                        x-text="float.text"
                    ></span>
                </template>
            </div>

            <div class="relative z-10 grid grid-cols-[1fr_auto_1fr] items-end gap-2 md:gap-6 px-3 md:px-8 pt-6 pb-8 min-h-[360px] md:min-h-[460px]">
                <div
                    class="duel-fighter duel-fighter--left text-center"
                    :class="{
                        'duel-fighter--hit': left.hit,
                        'duel-fighter--heal': left.healed,
                        'duel-fighter--strike': left.striking,
                        'duel-fighter--down': left.hp <= 0
                    }"
                >
                    <div class="duel-portrait mx-auto mb-3" :style="'--portrait-tone:' + left.tone">
                        <span class="text-4xl md:text-6xl" x-text="left.icon"></span>
                    </div>
                    <p class="font-semibold text-sm md:text-base truncate" x-text="left.arena || left.name"></p>
                    <p class="text-xs text-amber-100/50" x-text="left.class"></p>
                    <div class="mt-3 max-w-[11rem] mx-auto">
                        <div class="flex justify-between text-[10px] uppercase tracking-wide text-rose-200/70 mb-1">
                            <span>HP</span>
                            <span x-text="left.hp + '/' + left.maxHp"></span>
                        </div>
                        <div class="duel-hp-track">
                            <div class="duel-hp-fill" :style="'width:' + leftPct + '%'"></div>
                        </div>
                    </div>
                </div>

                <div class="relative self-center w-16 md:w-24 h-24 md:h-32 flex items-center justify-center">
                    <p class="font-display text-xl md:text-3xl text-violet-200/40" x-show="!finished">VS</p>
                </div>

                <div
                    class="duel-fighter duel-fighter--right duel-fighter--boss text-center"
                    :class="{
                        'duel-fighter--hit': right.hit,
                        'duel-fighter--heal': right.healed,
                        'duel-fighter--strike': right.striking,
                        'duel-fighter--down': right.hp <= 0
                    }"
                >
                    <div
                        class="duel-portrait duel-portrait--boss mx-auto mb-3"
                        :style="'--portrait-tone:' + right.tone"
                    >
                        <span class="text-5xl md:text-7xl" x-text="right.icon"></span>
                    </div>
                    <p class="font-semibold text-sm md:text-base truncate" x-text="right.arena || right.name"></p>
                    <p class="text-xs text-violet-200/70" x-text="phaseLabel"></p>
                    <div class="mt-3 max-w-[14rem] mx-auto">
                        <div class="flex justify-between text-[10px] uppercase tracking-wide text-rose-200/70 mb-1">
                            <span>HP</span>
                            <span x-text="right.hp + '/' + right.maxHp"></span>
                        </div>
                        <div class="duel-hp-track duel-hp-track--boss">
                            <div class="duel-hp-fill" :style="'width:' + rightPct + '%'"></div>
                            <div class="duel-hp-phases" aria-hidden="true">
                                <span></span><span></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="relative z-10 border-t border-violet-300/15 px-4 py-3 flex flex-wrap items-center justify-between gap-2 bg-black/20">
                <p class="text-sm text-amber-100/70" x-text="statusLine"></p>
                <div class="flex flex-wrap items-center gap-3">
                    <label class="flex items-center gap-2 text-sm text-amber-100/70">
                        <input type="checkbox" data-sound-toggle>
                        Som da arena
                    </label>
                    <button type="button" class="game-btn-ghost !py-1 !px-3 text-sm" x-show="!finished" @click="skip()">Pular animação</button>
                    <p class="text-sm font-semibold" x-show="outcomeRevealed && !victoryOpen" x-cloak :class="studentWon ? 'text-emerald-300' : 'text-rose-300'" x-text="resultLine"></p>
                </div>
            </div>
        </div>

        @if($winnerReasonLabel)
            <p class="text-sm text-amber-100/60 -mt-2" x-show="outcomeRevealed && !victoryOpen" x-cloak>{{ $winnerReasonLabel }}</p>
        @endif
        @if($vigil->mark_earned)
            <p class="text-sm text-violet-200" x-show="outcomeRevealed && !victoryOpen" x-cloak>Marca do Rito conquistada — a turma chega mais forte no assalto final.</p>
        @endif
        @if($vigil->isBossChallenge() || ($vigil->isStaffChallenge() && ($lootTotals['relics'] + $lootTotals['seals'] + $lootTotals['auras']) > 0))
            <div
                class="rounded-lg border border-amber-400/20 bg-black/20 p-4 text-sm space-y-1"
                x-show="outcomeRevealed && !victoryOpen"
                x-cloak
                x-transition.opacity.duration.300ms
            >
                @if($vigil->isBossChallenge())
                    <p class="text-amber-100/70">
                        Taxa paga: {{ \App\Models\GameCurrency::format('relics', (int) $vigil->fee_relics) }}
                    </p>
                @elseif($vigil->isStaffChallenge() && (int) $vigil->glory > 0)
                    <p class="text-amber-100/70">
                        Glória: +{{ \App\Models\GameCurrency::format('glory', (int) $vigil->glory) }}
                    </p>
                @endif
                @if($vigil->won)
                    <p class="text-emerald-300">
                        Loot:
                        {{ \App\Models\GameCurrency::format('relics', $lootTotals['relics']) }}
                        · {{ \App\Models\GameCurrency::format('seals', $lootTotals['seals']) }}
                        · {{ \App\Models\GameCurrency::format('auras', $lootTotals['auras']) }}
                    </p>
                @else
                    <p class="text-amber-200">
                        Consolação:
                        {{ \App\Models\GameCurrency::format('relics', $lootTotals['relics']) }}
                        · {{ \App\Models\GameCurrency::format('seals', $lootTotals['seals']) }}
                        · {{ \App\Models\GameCurrency::format('auras', $lootTotals['auras']) }}
                    </p>
                @endif
                @if($jackpotTotals)
                    <div class="pt-3 flex flex-wrap items-center gap-4 border-t border-amber-400/15 mt-2">
                        <div class="boss-pot boss-pot--compact is-jackpot" aria-label="Pote da turma">
                            <div class="boss-pot-vessel">
                                <div
                                    class="boss-pot-fill"
                                    data-boss-pot-fill="{{ $jackpotFillTo }}"
                                    data-boss-pot-from="{{ $jackpotFillFrom }}"
                                ></div>
                            </div>
                        </div>
                        <p class="text-amber-200 font-semibold">
                            Pote da turma: +{{ \App\Models\GameCurrency::format('relics', $jackpotTotals['relics']) }}
                            <span class="font-normal text-amber-100/60">
                                ({{ $jackpotTotals['percent'] }}% de {{ $jackpotTotals['bank_before'] }})
                            </span>
                        </p>
                    </div>
                @endif
            </div>
        @endif

        <div
            x-show="victoryOpen"
            x-cloak
            x-transition.opacity.duration.400ms
            class="duel-victory class-aura"
            :class="(studentWon ? left.classKey : right.classKey) ? ('class-aura--' + (studentWon ? left.classKey : right.classKey)) : ''"
            role="dialog"
            aria-modal="true"
            aria-labelledby="boss-reward-title"
            @keydown.escape.window="victoryOpen = false"
        >
            <span
                class="class-fx"
                :class="(studentWon ? left.classKey : right.classKey) ? ('class-fx--' + (studentWon ? left.classKey : right.classKey)) : ''"
                aria-hidden="true"
            >
                <span></span><span></span><span></span><span></span><span></span><span></span>
                <span></span><span></span><span></span><span></span><span></span><span></span>
            </span>
            <div class="duel-victory__stage">
                <div class="duel-victory__burst" aria-hidden="true"></div>
                <div class="duel-victory__content">
                    <p class="hero-kicker !mb-3" x-text="victoryKicker"></p>
                    <p class="duel-victory__icon" x-text="studentWon ? left.icon : right.icon"></p>
                    <h2 id="boss-reward-title" class="duel-victory__name font-display" x-text="victoryTitle"></h2>
                    <p class="duel-victory__class" x-text="studentWon ? (left.arena || left.name) : (right.arena || right.name)"></p>
                    <p class="duel-victory__glory" x-text="victoryGloryLine"></p>

                    <template x-if="gloryAwarded > 0">
                        <div class="boss-loot-row">
                            <div class="boss-loot-item" style="--loot-delay: 0.08s">
                                <span class="boss-loot-item__icon" x-text="currencyIcon('glory')"></span>
                                <span class="boss-loot-item__amount" x-text="'+' + gloryAwarded"></span>
                                <span class="boss-loot-item__label" x-text="currencyLabel('glory')"></span>
                            </div>
                        </div>
                    </template>

                    <template x-if="hasLootRewards">
                        <div class="boss-loot-row">
                            <template x-for="item in lootItems" :key="item.key">
                                <div class="boss-loot-item" :style="{ '--loot-delay': item.delay }">
                                    <span class="boss-loot-item__icon" x-text="item.icon"></span>
                                    <span class="boss-loot-item__amount" x-text="'+' + item.amount"></span>
                                    <span class="boss-loot-item__label" x-text="item.label"></span>
                                </div>
                            </template>
                        </div>
                    </template>

                    <template x-if="jackpotRelics > 0">
                        <p class="boss-loot-jackpot">
                            <span x-text="currencyIcon('relics')"></span>
                            Pote da turma:
                            <strong x-text="'+' + jackpotRelics"></strong>
                            <span class="boss-loot-jackpot__meta" x-text="currencyLabel('relics')"></span>
                        </p>
                    </template>

                    <button type="button" class="game-btn mt-8" @click="victoryOpen = false">Continuar</button>
                </div>
            </div>
        </div>

        <div class="game-card p-5">
            <h2 class="font-display text-xl text-amber-200 mb-3">Histórico de danos</h2>
            <div class="space-y-1 max-h-72 overflow-y-auto" x-ref="logBox">
                <template x-for="(entry, index) in log" :key="index">
                    <div class="flex items-start gap-3 py-2 border-b border-purple-900/40 text-sm">
                        <span class="text-amber-100/40 w-8 shrink-0" x-text="'#' + (index + 1)"></span>
                        <span class="flex-1">
                            <span x-text="entry.text"></span>
                            <span class="text-violet-300/70 text-xs ml-2" x-show="entry.phase" x-text="'· ' + (entry.phase || '')"></span>
                        </span>
                        <span
                            class="shrink-0 font-semibold"
                            :class="entry.action === 'heal' ? 'text-emerald-300' : 'text-rose-300'"
                            x-text="entry.action === 'heal' ? ('+' + entry.amount) : ('-' + entry.amount)"
                        ></span>
                    </div>
                </template>
            </div>
        </div>
    </div>
@else
    <div class="game-card p-8 text-center">
        <p class="text-amber-100/60">
            @if($vigil->status === \App\Models\BossVigil::STATUS_DECLINED)
                Desafio recusado.
            @elseif($vigil->status === \App\Models\BossVigil::STATUS_EXPIRED)
                Desafio expirado.
            @else
                Replay indisponível.
            @endif
        </p>
        <a class="game-btn-ghost inline-block mt-4" href="{{ $backUrl }}">{{ $backLabel }}</a>
    </div>
@endif
@endsection
