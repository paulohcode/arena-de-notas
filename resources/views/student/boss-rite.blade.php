@extends('layouts.game')

@section('title', 'Rito — '.$season->name)

@php
    $log = $rite->log ?? [];
    $waves = $log['waves'] ?? [];
    $isResolved = $rite->isResolved() && ! empty($waves);
    $bossMeta = $season->bossMeta() ?? ($log['boss_meta'] ?? []);
    $damageBoard = $log['damage_board'] ?? [];

    $fights = [];
    foreach ($waves as $wave) {
        $leftSnap = $wave['fighters']['challenger'] ?? null;
        $rightSnap = $wave['fighters']['opponent'] ?? null;
        if (! $leftSnap || ! $rightSnap) {
            continue;
        }
        $leftUser = \App\Models\User::query()->find($wave['challenger_id']);
        if (! $leftUser) {
            continue;
        }

        $fights[] = [
            'label' => 'Onda '.($wave['index'] ?? count($fights) + 1),
            'damage' => (int) ($wave['damage_dealt'] ?? 0),
            'bossHpStart' => (int) ($wave['boss_hp_start'] ?? 0),
            'bossHpEnd' => (int) ($wave['boss_hp_end'] ?? 0),
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
                'isBoss' => false,
            ],
            'right' => [
                'id' => $bossFighterId,
                'name' => $rightSnap['name'] ?? ($bossMeta['name'] ?? 'Chefão'),
                'arena' => $rightSnap['arena_name'] ?? null,
                'class' => $rightSnap['class'] ?? ($bossMeta['name'] ?? 'Chefão'),
                'classKey' => (string) ($rightSnap['class_key'] ?? $season->boss_archetype ?? 'boss'),
                'icon' => $rightSnap['icon'] ?? ($bossMeta['icon'] ?? '🌑'),
                'tone' => $rightSnap['tone'] ?? ($bossMeta['tone'] ?? '#6d28d9'),
                'classTone' => $rightSnap['tone'] ?? ($bossMeta['tone'] ?? '#6d28d9'),
                'maxHp' => (int) ($log['boss_max_hp'] ?? $rightSnap['max_hp']),
                'isBoss' => true,
            ],
            'turns' => $wave['turns'] ?? [],
            'winnerId' => (int) ($wave['winner_id'] ?? 0),
        ];
    }

    $seriesPayload = $isResolved ? [
        'fights' => $fights,
        'bossMaxHp' => (int) ($log['boss_max_hp'] ?? $rite->boss_max_hp),
        'bossHpStart' => (int) ($log['boss_hp_start'] ?? $rite->boss_max_hp),
        'bossHpEnd' => (int) ($log['boss_hp_end'] ?? $rite->boss_hp),
        'viewerId' => (int) $student->id,
        'broken' => $rite->wasBroken(),
        'relicsWin' => (int) $relicsWin,
        'relicsLoss' => (int) $relicsLoss,
        'rewardLabel' => $rewardLabel,
        'bossName' => $season->bossDisplayName() ?? ($bossMeta['name'] ?? 'Rito'),
        'bossIcon' => $bossMeta['icon'] ?? '🌑',
        'marksApplied' => (int) ($log['marks_applied'] ?? $rite->marks_applied),
    ] : null;
@endphp

@section('content')
@if($isResolved)
    <div
        class="space-y-6"
        x-data="riteBattleSeries({{ \Illuminate\Support\Js::from($seriesPayload) }})"
        x-init="start()"
    >
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="hero-kicker !mb-1">Assalto do Rito</p>
                <h1 class="font-display text-3xl md:text-4xl text-violet-200" x-text="headline"></h1>
                <p class="text-amber-100/60 mt-1">{{ $season->bossDisplayName() }} · {{ $rite->marks_applied }} Marca(s)</p>
            </div>
            <a class="game-btn-ghost" href="{{ route('student.arena.index') }}#rito-temporada">Voltar à arena</a>
        </div>

        <div class="game-card p-4">
            <div class="flex justify-between text-xs uppercase tracking-wide text-rose-200/70 mb-2">
                <span>HP do Rito</span>
                <span x-text="raidHpLabel"></span>
            </div>
            <div class="duel-hp-track duel-hp-track--boss h-4">
                <div class="duel-hp-fill" :style="'width:' + raidHpPct + '%'"></div>
                <div class="duel-hp-phases" aria-hidden="true"><span></span><span></span></div>
            </div>
            <p class="text-sm text-amber-100/60 mt-2" x-text="statusLine"></p>
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
            x-show="currentFight"
        >
            <div class="duel-stage__floor" aria-hidden="true"></div>
            <div class="px-4 pt-4 relative z-10 flex flex-wrap justify-between gap-2">
                <p class="text-sm text-violet-200" x-text="currentFight?.label"></p>
                <div class="boss-phase-bar">
                    <span class="boss-phase-bar__seg" :class="{ 'is-active': phaseRank >= 0, 'is-current': currentPhase === 'julgamento' }">Julgamento</span>
                    <span class="boss-phase-bar__seg" :class="{ 'is-active': phaseRank >= 1, 'is-current': currentPhase === 'prova' }">Prova</span>
                    <span class="boss-phase-bar__seg" :class="{ 'is-active': phaseRank >= 2, 'is-current': currentPhase === 'veredito' }">Veredito</span>
                </div>
            </div>
            <div class="duel-fx" aria-hidden="true">
                <template x-for="fx in effects" :key="fx.id">
                    <span class="duel-fx__item" :class="fx.className" :style="fx.tone ? { '--duel-fx': fx.tone } : {}"></span>
                </template>
            </div>

            <div class="relative z-10 grid grid-cols-[1fr_auto_1fr] items-end gap-2 md:gap-6 px-3 md:px-8 pt-6 pb-8 min-h-[320px]">
                <div class="duel-fighter text-center" :class="{ 'duel-fighter--strike': left?.striking, 'duel-fighter--hit': left?.hit, 'duel-fighter--down': left && left.hp <= 0 }">
                    <div class="duel-portrait mx-auto mb-3" :style="left ? ('--portrait-tone:' + left.tone) : ''">
                        <span class="text-4xl md:text-6xl" x-text="left?.icon"></span>
                    </div>
                    <p class="font-semibold truncate" x-text="left ? (left.arena || left.name) : ''"></p>
                    <div class="mt-3 max-w-[11rem] mx-auto">
                        <div class="duel-hp-track"><div class="duel-hp-fill" :style="'width:' + leftPct + '%'"></div></div>
                    </div>
                </div>
                <p class="font-display text-2xl text-violet-200/40 self-center">VS</p>
                <div class="duel-fighter duel-fighter--boss text-center" :class="{ 'duel-fighter--strike': right?.striking, 'duel-fighter--hit': right?.hit, 'duel-fighter--down': right && right.hp <= 0 }">
                    <div class="duel-portrait duel-portrait--boss mx-auto mb-3" :style="right ? ('--portrait-tone:' + right.tone) : ''">
                        <span class="text-5xl md:text-7xl" x-text="right?.icon || bossIcon"></span>
                    </div>
                    <p class="font-semibold truncate" x-text="bossName"></p>
                    <p class="text-xs text-violet-200/70" x-text="phaseLabel"></p>
                </div>
            </div>

            <div class="relative z-10 border-t border-violet-300/15 px-4 py-3 flex flex-wrap items-center justify-between gap-2 bg-black/20">
                <label class="flex items-center gap-2 text-sm text-amber-100/70">
                    <input type="checkbox" data-sound-toggle>
                    Som da arena
                </label>
                <div class="flex gap-2">
                    <button type="button" class="game-btn-ghost !py-1 !px-3 text-sm" x-show="!seriesFinished" @click="skipFight()">Pular onda</button>
                    <button type="button" class="game-btn-ghost !py-1 !px-3 text-sm" x-show="!seriesFinished" @click="skipAll()">Pular tudo</button>
                </div>
            </div>
        </div>

        @if(! empty($damageBoard))
            <div class="game-card p-5">
                <h2 class="font-display text-xl text-amber-200 mb-3">Ranking de dano</h2>
                <ol class="space-y-2">
                    @foreach($damageBoard as $row)
                        <li class="flex flex-wrap items-center justify-between gap-2 text-sm border-b border-purple-900/40 py-2">
                            <span>
                                <span class="text-amber-100/40 mr-2">{{ $loop->iteration }}º</span>
                                {{ $row['arena_name'] ?: $row['name'] }}
                                <span class="text-amber-100/45">· {{ $row['class'] }}</span>
                            </span>
                            <span class="font-semibold text-rose-300">{{ $row['damage'] }} dano</span>
                        </li>
                    @endforeach
                </ol>
            </div>
        @endif
    </div>
@elseif($rite->isOpen())
    <div class="game-card p-8 text-center max-w-xl mx-auto">
        <p class="hero-kicker !mb-2">Rito aberto</p>
        <h1 class="font-display text-3xl text-violet-200 mb-3">{{ $season->bossDisplayName() }}</h1>
        <p class="text-amber-100/65 mb-4">HP {{ $rite->boss_hp }}/{{ $rite->boss_max_hp }} · {{ $rite->marks_applied }} Marca(s). O professor resolve o assalto.</p>
        <a class="game-btn-ghost" href="{{ route('student.arena.index') }}">Voltar à arena</a>
    </div>
@else
    <div class="game-card p-8 text-center">
        <p class="text-amber-100/60">O Rito ainda não foi resolvido.</p>
        <a class="game-btn-ghost inline-block mt-4" href="{{ route('student.arena.index') }}">Voltar</a>
    </div>
@endif
@endsection
