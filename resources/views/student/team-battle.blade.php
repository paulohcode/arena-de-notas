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
            'wrap' => ! empty($matchup['wrap']),
        ];
    }

    $viewerFought = collect($log['fighter_ids'] ?? [])
        ->map(fn ($id) => (int) $id)
        ->contains((int) $student->id);
    $seriesPayload = $isResolved ? [
        'fights' => $fights,
        'leftTeam' => [
            'id' => (int) $battle->challengerTeam->id,
            'name' => $battle->challengerTeam->name,
            'emblem' => $battle->challengerTeam->emblemIcon(),
            'color' => $battle->challengerTeam->color ?: '#f5c56b',
        ],
        'rightTeam' => [
            'id' => (int) $battle->opponentTeam->id,
            'name' => $battle->opponentTeam->name,
            'emblem' => $battle->opponentTeam->emblemIcon(),
            'color' => $battle->opponentTeam->color ?: '#7c3aed',
        ],
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
        class="game-card p-8 text-center max-w-xl mx-auto reveal guild-war-wait"
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
            <div class="text-center guild-war-wait__side">
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
            <div class="relative z-30 flex flex-wrap gap-2">
                <button type="button" class="game-btn-ghost !py-1 !px-3 text-sm" x-show="!seriesFinished" @click.prevent.stop="skipCurrent()">Pular combate</button>
                <button type="button" class="game-btn-ghost !py-1 !px-3 text-sm" x-show="!seriesFinished" @click.prevent.stop="skipAll()">Pular tudo</button>
                <a class="game-btn-ghost" href="{{ route('student.arena.index') }}">Voltar à arena</a>
            </div>
        </div>

        <div
            class="guild-war game-card overflow-hidden"
            data-guild-war
            :class="{
                'guild-war--intro': phase === 'intro',
                'guild-war--clash': phase === 'clash',
                'guild-war--result': seriesFinished,
                'guild-war--flash': stageFlash === 'hit',
                'guild-war--heavy': stageFlash === 'heavy',
                'guild-war--ko': stageFlash === 'ko',
                'guild-war--heal': stageFlash === 'heal',
                'guild-war--rumble': rumble,
                'guild-war--crowd': leftFighters.length > 3 || rightFighters.length > 3
            }"
            :style="{
                '--guild-left': leftTeam.color || '#f5c56b',
                '--guild-right': rightTeam.color || '#7c3aed'
            }"
        >
            <div class="guild-war__embers" aria-hidden="true">
                <span></span><span></span><span></span><span></span><span></span><span></span>
                <span></span><span></span><span></span><span></span>
            </div>
            <div class="guild-war__floor" aria-hidden="true"></div>

            <div class="guild-war__banners">
                <div class="guild-war__banner guild-war__banner--left">
                    <span class="text-2xl md:text-4xl" x-text="leftTeam.emblem"></span>
                    <span class="font-display text-lg md:text-2xl truncate" x-text="leftTeam.name"></span>
                </div>
                <div class="guild-war__scoreboard">
                    <span class="guild-war__score" x-text="liveScore.left"></span>
                    <span class="guild-war__vs" x-show="!seriesFinished">VS</span>
                    <span class="guild-war__vs guild-war__vs--done" x-show="seriesFinished" x-cloak>FIM</span>
                    <span class="guild-war__score" x-text="liveScore.right"></span>
                </div>
                <div class="guild-war__banner guild-war__banner--right">
                    <span class="font-display text-lg md:text-2xl truncate" x-text="rightTeam.name"></span>
                    <span class="text-2xl md:text-4xl" x-text="rightTeam.emblem"></span>
                </div>
            </div>

            <div class="guild-war__fx" aria-hidden="true">
                <template x-for="fx in effects" :key="fx.id">
                    <span
                        class="guild-fx-slot"
                        :style="{ top: (fx.y ?? 50) + '%', '--duel-fx': fx.tone || '#f5c56b' }"
                    >
                        <span class="duel-fx__item" :class="fx.className" :style="fx.tone ? { '--duel-fx': fx.tone } : {}"></span>
                    </span>
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
                        :style="{ top: (float.y ?? 18) + '%', '--duel-fx': float.tone || '#fecaca' }"
                        x-text="float.text"
                    ></span>
                </template>
            </div>

            <div class="guild-war__field">
                <div class="guild-war__side guild-war__side--left" :class="{ 'guild-war__side--won': seriesFinished && leftWon }">
                    <template x-for="fighter in leftFighters" :key="fighter.id">
                        <div
                            class="guild-war__fighter duel-fighter duel-fighter--left"
                            :class="{
                                'duel-fighter--hit': fighter.hit,
                                'duel-fighter--heal': fighter.healed,
                                'duel-fighter--strike': fighter.striking,
                                'duel-fighter--down': fighter.hp <= 0 || fighter.down,
                                'guild-war__fighter--active': fighter.active,
                                'guild-war__fighter--you': fighter.id === viewerId
                            }"
                        >
                            <div
                                class="duel-portrait guild-war__portrait"
                                :style="'--portrait-tone:' + fighter.tone"
                                :class="{
                                    'duel-portrait--winner': seriesFinished && leftWon && fighter.hp > 0,
                                    'duel-portrait--down': fighter.hp <= 0 || fighter.down
                                }"
                            >
                                <span class="text-2xl md:text-4xl" x-text="fighter.icon"></span>
                            </div>
                            <p class="font-semibold text-xs md:text-sm truncate" x-text="fighter.arena || fighter.name"></p>
                            <p class="text-[10px] md:text-xs text-amber-100/50 truncate" x-text="fighter.class"></p>
                            <div class="guild-war__hp">
                                <div class="flex justify-between text-[9px] uppercase tracking-wide text-rose-200/70 mb-0.5">
                                    <span>HP</span>
                                    <span x-text="fighter.hp + '/' + fighter.maxHp"></span>
                                </div>
                                <div class="duel-hp-track">
                                    <div class="duel-hp-fill" :style="'width:' + hpPct(fighter) + '%'"></div>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>

                <div class="guild-war__core" aria-hidden="true">
                    <div class="guild-war__orb"></div>
                </div>

                <div class="guild-war__side guild-war__side--right" :class="{ 'guild-war__side--won': seriesFinished && !leftWon }">
                    <template x-for="fighter in rightFighters" :key="fighter.id">
                        <div
                            class="guild-war__fighter duel-fighter duel-fighter--right"
                            :class="{
                                'duel-fighter--hit': fighter.hit,
                                'duel-fighter--heal': fighter.healed,
                                'duel-fighter--strike': fighter.striking,
                                'duel-fighter--down': fighter.hp <= 0 || fighter.down,
                                'guild-war__fighter--active': fighter.active,
                                'guild-war__fighter--you': fighter.id === viewerId
                            }"
                        >
                            <div
                                class="duel-portrait guild-war__portrait"
                                :style="'--portrait-tone:' + fighter.tone"
                                :class="{
                                    'duel-portrait--winner': seriesFinished && !leftWon && fighter.hp > 0,
                                    'duel-portrait--down': fighter.hp <= 0 || fighter.down
                                }"
                            >
                                <span class="text-2xl md:text-4xl" x-text="fighter.icon"></span>
                            </div>
                            <p class="font-semibold text-xs md:text-sm truncate" x-text="fighter.arena || fighter.name"></p>
                            <p class="text-[10px] md:text-xs text-amber-100/50 truncate" x-text="fighter.class"></p>
                            <div class="guild-war__hp">
                                <div class="flex justify-between text-[9px] uppercase tracking-wide text-rose-200/70 mb-0.5">
                                    <span>HP</span>
                                    <span x-text="fighter.hp + '/' + fighter.maxHp"></span>
                                </div>
                                <div class="duel-hp-track">
                                    <div class="duel-hp-fill" :style="'width:' + hpPct(fighter) + '%'"></div>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            <div
                class="guild-war__intro"
                x-show="phase === 'intro'"
                x-cloak
                x-transition.opacity.duration.400ms
            >
                <p class="hero-kicker !mb-2">Campo de guerra</p>
                <p class="font-display text-4xl md:text-6xl text-amber-200 guild-war__intro-title">A GUERRA COMEÇA</p>
                <p class="mt-3 text-amber-100/70">{{ $battle->challengerTeam->emblemIcon() }} {{ $battle->challengerTeam->name }} × {{ $battle->opponentTeam->emblemIcon() }} {{ $battle->opponentTeam->name }}</p>
            </div>

            <div class="guild-war__footer">
                <p class="text-sm text-amber-100/70" x-text="statusLine"></p>
                <div class="flex flex-wrap items-center gap-3">
                    <label class="flex items-center gap-2 text-sm text-amber-100/70">
                        <input type="checkbox" data-sound-toggle>
                        Som da arena
                    </label>
                    <button type="button" class="game-btn-ghost !py-1 !px-3 text-sm" x-show="!seriesFinished" @click.prevent.stop="skipCurrent()">Pular combate</button>
                </div>
            </div>
        </div>

        <div class="game-card p-5" x-show="!seriesFinished">
            <h2 class="font-display text-xl text-amber-200 mb-3">Histórico de danos</h2>
            <div class="space-y-1 max-h-72 overflow-y-auto" x-ref="logBox">
                <template x-for="(entry, index) in log" :key="index">
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
                <p class="text-sm text-purple-200/50 py-2" x-show="log.length === 0">O combate vai começar…</p>
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
