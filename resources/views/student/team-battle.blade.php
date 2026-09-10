@extends('layouts.game')

@section('title', 'Batalha de Guildas — '.$class->name)

@php
    $log = $battle->log ?? [];
    $matchups = $log['matchups'] ?? [];
    $isPending = $battle->isPending();
    $isResolved = $battle->isResolved() && ! empty($matchups);
    $score = $log['score'] ?? ['challenger' => 0, 'opponent' => 0];
    $fighterIds = collect($matchups)
        ->flatMap(fn ($m) => [$m['challenger_id'] ?? null, $m['opponent_id'] ?? null])
        ->filter()
        ->unique()
        ->values()
        ->all();
    $fightersById = \App\Models\User::query()->whereIn('id', $fighterIds)->get()->keyBy('id');

    $fights = [];
    foreach ($matchups as $matchup) {
        $leftUser = $fightersById->get($matchup['challenger_id']);
        $rightUser = $fightersById->get($matchup['opponent_id']);
        $leftSnap = $matchup['fighters']['challenger'] ?? null;
        $rightSnap = $matchup['fighters']['opponent'] ?? null;
        if (! $leftUser || ! $rightUser || ! $leftSnap || ! $rightSnap) {
            continue;
        }

        $fights[] = [
            'label' => 'Combate '.($matchup['index'] ?? count($fights) + 1)
                .(! empty($matchup['wrap']) ? ' · reentrada' : ''),
            'left' => [
                'id' => $leftUser->id,
                'name' => $leftUser->name,
                'arena' => $leftUser->arenaName(),
                'class' => $leftSnap['class'],
                'classKey' => (string) ($leftUser->character_class ?? ''),
                'icon' => $leftUser->avatarIcon(),
                'tone' => $leftUser->avatarTone(),
                'classTone' => $leftUser->characterClassTone(),
                'maxHp' => (int) $leftSnap['max_hp'],
            ],
            'right' => [
                'id' => $rightUser->id,
                'name' => $rightUser->name,
                'arena' => $rightUser->arenaName(),
                'class' => $rightSnap['class'],
                'classKey' => (string) ($rightUser->character_class ?? ''),
                'icon' => $rightUser->avatarIcon(),
                'tone' => $rightUser->avatarTone(),
                'classTone' => $rightUser->characterClassTone(),
                'maxHp' => (int) $rightSnap['max_hp'],
            ],
            'turns' => $matchup['turns'] ?? [],
            'winnerId' => (int) ($matchup['winner_id'] ?? 0),
        ];
    }

    $viewerFought = collect($log['fighter_ids'] ?? [])
        ->map(fn ($id) => (int) $id)
        ->contains((int) $student->id);
    $seriesPayload = $isResolved ? [
        'fights' => $fights,
        'viewerId' => (int) $student->id,
        'winnerTeamId' => (int) $battle->winner_team_id,
        'ownTeamId' => (int) ($ownTeam?->id ?? 0),
        'gloryWin' => (int) $battle->glory_winner,
        'gloryLoss' => (int) $battle->glory_loser,
        'viewerFought' => $viewerFought,
        'scoreLine' => $battle->challengerTeam->name.' '.$score['challenger'].' × '.$score['opponent'].' '.$battle->opponentTeam->name,
        'winnerTeamName' => $battle->winnerTeam?->name ?? 'Guilda',
    ] : null;

    $winnerReasonLabel = match ($log['winner_reason'] ?? null) {
        'score' => 'Mais vitórias individuais.',
        'hp' => 'Empate de vitórias: mais HP restante nos vencedores.',
        'challenger_tie' => 'Empate total: a guilda desafiante venceu.',
        default => null,
    };
@endphp

@section('content')
@if($isPending)
    <div
        class="game-card p-8 text-center max-w-xl mx-auto reveal"
        data-guild-waiting
        x-data="{
            statusUrl: {{ \Illuminate\Support\Js::from($statusUrl) }},
            pulling: false,
            async poll() {
                if (this.pulling) {
                    return;
                }
                this.pulling = true;
                try {
                    const response = await fetch(this.statusUrl, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
                    if (! response.ok) return;
                    const data = await response.json();
                    if (data.redirect) {
                        window.ArenaGoToBattle
                            ? window.ArenaGoToBattle(data.redirect)
                            : window.location.reload();
                    }
                } catch (e) {}
                finally {
                    this.pulling = false;
                }
            },
            init() {
                this.poll();
                setInterval(() => this.poll(), 2000);
            }
        }"
    >
        <p class="hero-kicker !mb-2">Sala de espera</p>
        <h1 class="font-display text-3xl text-amber-300 mb-3">Aguardando a guilda rival</h1>
        <div class="flex items-center justify-center gap-6 my-8">
            <div class="text-center">
                <p class="text-4xl mb-2">{{ $battle->challengerTeam->emblemIcon() }}</p>
                <p class="mt-2 font-semibold">{{ $battle->challengerTeam->name }}</p>
            </div>
            <p class="font-display text-2xl text-amber-200/50">VS</p>
            <div class="text-center opacity-70">
                <p class="text-4xl mb-2">{{ $battle->opponentTeam->emblemIcon() }}</p>
                <p class="mt-2 font-semibold">{{ $battle->opponentTeam->name }}</p>
            </div>
        </div>
        <p class="text-amber-100/65 mb-2">Quando alguém de {{ $battle->opponentTeam->name }} aceitar, a batalha abre sozinha nesta tela.</p>
        <p class="text-xs text-cyan-300/70 animate-pulse">Escutando a arena…</p>
        <a class="game-btn-ghost inline-block mt-6" href="{{ route('student.arena.index') }}">Voltar à arena</a>
    </div>
@elseif($isResolved && $seriesPayload)
    <div
        class="space-y-6"
        x-data="teamBattleSeries({{ \Illuminate\Support\Js::from($seriesPayload) }})"
    >
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="hero-kicker !mb-1">Guerra de guildas</p>
                <h1 class="font-display text-3xl md:text-4xl text-amber-300" x-text="seriesHeadline"></h1>
                <p class="text-amber-100/60 mt-1">
                    {{ $battle->challengerTeam->emblemIcon() }} {{ $battle->challengerTeam->name }}
                    {{ $score['challenger'] }}–{{ $score['opponent'] }}
                    {{ $battle->opponentTeam->emblemIcon() }} {{ $battle->opponentTeam->name }}
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <button type="button" class="game-btn-ghost !py-1 !px-3 text-sm" x-show="!seriesFinished && battle && !battle.finished" @click="skipCurrent()">Pular combate</button>
                <button type="button" class="game-btn-ghost !py-1 !px-3 text-sm" x-show="!seriesFinished" @click="skipAll()">Pular tudo</button>
                <a class="game-btn-ghost" href="{{ route('student.arena.index') }}">Voltar à arena</a>
            </div>
        </div>

        <div class="game-card p-4 flex flex-wrap gap-3 text-sm">
            @foreach($matchups as $matchup)
                @php
                    $left = $fightersById->get($matchup['challenger_id']);
                    $right = $fightersById->get($matchup['opponent_id']);
                    $leftPower = $matchup['fighters']['challenger']['power'] ?? null;
                    $rightPower = $matchup['fighters']['opponent']['power'] ?? null;
                    $leftGrade = $matchup['fighters']['challenger']['breakdown']['grade'] ?? null;
                    $rightGrade = $matchup['fighters']['opponent']['breakdown']['grade'] ?? null;
                @endphp
                <div
                    class="rounded-lg border px-3 py-2 min-w-[12rem]"
                    :class="fightIndex === {{ $loop->index }} && !seriesFinished ? 'border-cyan-300/50 bg-cyan-950/30' : 'border-purple-900/40'"
                >
                    <p class="text-xs text-amber-100/50 mb-1">
                        #{{ $matchup['index'] ?? $loop->iteration }}
                        @if(! empty($matchup['wrap']))
                            · reentrada
                        @endif
                    </p>
                    <p>
                        {{ $left?->arenaName() ?: $left?->name }}
                        <span class="text-amber-100/40">vs</span>
                        {{ $right?->arenaName() ?: $right?->name }}
                    </p>
                    @if($leftPower !== null && $rightPower !== null)
                        <p class="text-[11px] text-amber-100/45 mt-1">
                            Poder {{ number_format((float) $leftPower, 2) }}
                            @if($leftGrade !== null)
                                (nota {{ number_format((float) $leftGrade, 0) }})
                            @endif
                            ×
                            {{ number_format((float) $rightPower, 2) }}
                            @if($rightGrade !== null)
                                (nota {{ number_format((float) $rightGrade, 0) }})
                            @endif
                        </p>
                    @endif
                    <p class="text-xs mt-1 {{ ((int) ($matchup['winner_id'] ?? 0)) === (int) ($left?->id) ? 'text-emerald-300' : 'text-rose-300' }}">
                        Venceu: {{ ((int) ($matchup['winner_id'] ?? 0)) === (int) ($left?->id) ? ($left?->arenaName() ?: $left?->name) : ($right?->arenaName() ?: $right?->name) }}
                    </p>
                </div>
            @endforeach
        </div>

        <div class="space-y-4" x-show="battle" x-cloak>
            <div
                class="duel-stage game-card overflow-hidden"
                :class="{
                    'duel-stage--flash': battle.stageFlash === 'hit',
                    'duel-stage--heavy': battle.stageFlash === 'heavy',
                    'duel-stage--ko': battle.stageFlash === 'ko',
                    'duel-stage--heal': battle.stageFlash === 'heal'
                }"
            >
                <div class="duel-stage__floor" aria-hidden="true"></div>
                <div class="duel-fx" aria-hidden="true">
                    <template x-for="fx in battle.effects" :key="fx.id">
                        <span class="duel-fx__item" :class="fx.className" :style="fx.tone ? { '--duel-fx': fx.tone } : {}"></span>
                    </template>
                    <template x-for="float in battle.floats" :key="float.id">
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

                <div class="relative z-10 grid grid-cols-[1fr_auto_1fr] items-end gap-2 md:gap-6 px-3 md:px-8 pt-10 pb-8 min-h-[360px] md:min-h-[460px]">
                    <div
                        class="duel-fighter duel-fighter--left text-center"
                        :class="{
                            'duel-fighter--hit': battle.left.hit,
                            'duel-fighter--heal': battle.left.healed,
                            'duel-fighter--strike': battle.left.striking,
                            'duel-fighter--down': battle.left.hp <= 0
                        }"
                    >
                        <div
                            class="duel-portrait mx-auto mb-3"
                            :style="'--portrait-tone:' + battle.left.tone"
                            :class="{ 'duel-portrait--winner': battle.finished && battle.winnerId === battle.left.id, 'duel-portrait--down': battle.left.hp <= 0 }"
                        >
                            <span class="text-4xl md:text-6xl" x-text="battle.left.icon"></span>
                        </div>
                        <p class="font-semibold text-sm md:text-base truncate" x-text="battle.left.arena || battle.left.name"></p>
                        <p class="text-xs text-amber-100/50" x-text="battle.left.class"></p>
                        <div class="mt-3 max-w-[11rem] mx-auto">
                            <div class="flex justify-between text-[10px] uppercase tracking-wide text-rose-200/70 mb-1">
                                <span>HP</span>
                                <span x-text="battle.left.hp + '/' + battle.left.maxHp"></span>
                            </div>
                            <div class="duel-hp-track">
                                <div class="duel-hp-fill" :style="'width:' + battle.leftPct + '%'"></div>
                            </div>
                        </div>
                    </div>

                    <div class="relative self-center w-16 md:w-24 h-24 md:h-32 flex items-center justify-center">
                        <p class="font-display text-xl md:text-3xl text-amber-200/40" x-show="battle && !battle.finished">VS</p>
                    </div>

                    <div
                        class="duel-fighter duel-fighter--right text-center"
                        :class="{
                            'duel-fighter--hit': battle.right.hit,
                            'duel-fighter--heal': battle.right.healed,
                            'duel-fighter--strike': battle.right.striking,
                            'duel-fighter--down': battle.right.hp <= 0
                        }"
                    >
                        <div
                            class="duel-portrait mx-auto mb-3"
                            :style="'--portrait-tone:' + battle.right.tone"
                            :class="{ 'duel-portrait--winner': battle.finished && battle.winnerId === battle.right.id, 'duel-portrait--down': battle.right.hp <= 0 }"
                        >
                            <span class="text-4xl md:text-6xl" x-text="battle.right.icon"></span>
                        </div>
                        <p class="font-semibold text-sm md:text-base truncate" x-text="battle.right.arena || battle.right.name"></p>
                        <p class="text-xs text-amber-100/50" x-text="battle.right.class"></p>
                        <div class="mt-3 max-w-[11rem] mx-auto">
                            <div class="flex justify-between text-[10px] uppercase tracking-wide text-rose-200/70 mb-1">
                                <span>HP</span>
                                <span x-text="battle.right.hp + '/' + battle.right.maxHp"></span>
                            </div>
                            <div class="duel-hp-track">
                                <div class="duel-hp-fill" :style="'width:' + battle.rightPct + '%'"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="relative z-10 border-t border-amber-300/15 px-4 py-3 flex flex-wrap items-center justify-between gap-2 bg-black/20">
                    <p class="text-sm text-amber-100/70" x-text="seriesFinished ? 'Série encerrada.' : battle.statusLine"></p>
                    <div class="flex flex-wrap items-center gap-3">
                        <label class="flex items-center gap-2 text-sm text-amber-100/70">
                            <input type="checkbox" data-sound-toggle>
                            Som da arena
                        </label>
                        <button type="button" class="game-btn !py-1 !px-3 text-sm" x-show="battle.finished && !seriesFinished" @click="nextFight()">
                            <span x-text="fightIndex + 1 >= totalFights ? 'Ver resultado' : 'Próximo combate'"></span>
                        </button>
                    </div>
                </div>
            </div>

            <div class="game-card p-5" x-show="!seriesFinished">
                <h2 class="font-display text-xl text-amber-200 mb-3">Histórico de danos</h2>
                <div class="space-y-1 max-h-72 overflow-y-auto" x-ref="logBox">
                    <template x-for="(entry, index) in battle.log" :key="index">
                        <div class="flex items-start gap-3 py-2 border-b border-purple-900/40 text-sm">
                            <span class="text-amber-100/40 w-8 shrink-0" x-text="'#' + (index + 1)"></span>
                            <span class="flex-1" x-text="entry.text"></span>
                            <span
                                class="shrink-0 font-semibold"
                                :class="entry.action === 'heal' ? 'text-emerald-300' : 'text-rose-300'"
                                x-text="entry.action === 'heal' ? ('+' + entry.amount) : ('-' + entry.amount)"
                            ></span>
                        </div>
                    </template>
                    <p class="text-sm text-purple-200/50 py-2" x-show="battle.log.length === 0">O combate vai começar…</p>
                </div>
            </div>
        </div>

        @if($winnerReasonLabel)
            <p class="text-sm text-amber-100/60" x-show="seriesFinished" x-cloak>{{ $winnerReasonLabel }}</p>
        @endif

        <div class="reveal">
            @include('partials.combat-rules')
        </div>

        <div
            x-show="seriesVictoryOpen"
            x-cloak
            x-transition.opacity.duration.400ms
            class="duel-victory"
            role="dialog"
            aria-modal="true"
            aria-labelledby="guild-winner-name"
            @keydown.escape.window="seriesVictoryOpen = false"
        >
            <div class="duel-victory__stage">
                <div class="duel-victory__burst" aria-hidden="true"></div>
                <div class="duel-victory__content">
                    <p class="hero-kicker !mb-3">Vencedora da guerra</p>
                    <p class="duel-victory__icon">{{ $battle->winnerTeam?->emblemIcon() ?? '🛡️' }}</p>
                    <h2 id="guild-winner-name" class="duel-victory__name font-display">{{ $battle->winnerTeam?->name }}</h2>
                    <p class="duel-victory__class" x-text="scoreLine"></p>
                    <p class="duel-victory__glory" x-text="seriesGloryLine"></p>
                    <button type="button" class="game-btn mt-8" @click="seriesVictoryOpen = false">Continuar</button>
                </div>
            </div>
        </div>
    </div>
@else
    <div class="game-card p-5">
        <p class="text-amber-100/70">Esta batalha ainda não está disponível.</p>
        <a class="game-btn-ghost inline-block mt-4" href="{{ route('student.arena.index') }}">Voltar à arena</a>
    </div>
@endif
@endsection
