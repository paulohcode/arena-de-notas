@extends('layouts.game')

@section('title', $class->name.' — Ranking')

@section('content')
@if($class->area)
    <a href="{{ route('areas.show', $class->area) }}" class="game-btn-ghost text-sm mb-4 inline-block reveal">← {{ $class->area->name }}</a>
@endif
<div class="flex flex-wrap items-end justify-between gap-4 mb-6 reveal">
    <div>
        <p class="hero-kicker !mb-1">Campo de batalha</p>
        <h1 class="font-display text-4xl text-amber-300">{{ $class->name }}</h1>
        <p class="text-amber-100/60 mt-1">Quem sobe, quem cai — o ranking atualiza ao vivo.</p>
    </div>
    <label class="flex items-center gap-2 text-sm text-amber-100/70 game-card px-3 py-2">
        <input type="checkbox" data-sound-toggle> Som de level up
    </label>
</div>

@if($self)
    <div class="game-card p-4 mb-6 flex flex-wrap items-center justify-between gap-3 reveal reveal-delay-1 border-orange-500/30">
        <p>Sua posição real: <strong class="text-amber-300">{{ $self['position'] }}º</strong> · nota {{ number_format($self['average'], 1) }}</p>
        <p class="text-sm text-cyan-300">{{ $self['level_name'] }} · {{ $self['xp'] }} XP · <span class="text-amber-200 opacity-80">{{ $self['badge_count'] }} medalhas</span></p>
        <p class="text-amber-100/60 text-sm">{{ $self['visible'] ? 'Você aparece no ranking público.' : 'Sua nota está oculta no ranking público.' }}</p>
    </div>
@endif

<div x-data="{ tab: 'jogadores' }">
    <div class="flex gap-2 mb-6 reveal reveal-delay-2 flex-wrap">
        <button type="button" class="tab-btn game-btn-ghost" :data-active="tab === 'jogadores'" @click="tab = 'jogadores'">Guerreiros</button>
        <button type="button" class="tab-btn game-btn-ghost" :data-active="tab === 'guildas'" @click="tab = 'guildas'">Hall das Guildas</button>
        <button type="button" class="tab-btn game-btn-ghost" :data-active="tab === 'arena'" @click="tab = 'arena'">Hall da Arena</button>
    </div>

    <div x-show="tab === 'jogadores'" class="space-y-3" data-player-list>
        @forelse($players as $row)
            <div class="game-card battle-row {{ $row['student']->characterAuraClass() }} p-4 flex items-center gap-4 reveal reveal-delay-{{ min($loop->iteration, 5) }}"
                 data-player-id="{{ $row['student']->id }}">
                @include('partials.class-fx', ['characterClass' => $row['student']->character_class])
                <div class="rank-badge {{ $row['position'] === 1 ? 'is-gold' : ($row['position'] === 2 ? 'is-silver' : ($row['position'] === 3 ? 'is-bronze' : '')) }}" data-pos>
                    {{ $row['position'] }}º
                </div>
                <div class="flex-1 min-w-0">
                    <p class="font-semibold truncate">
                        @if($canOpenStudentProfile)
                            <a
                                href="{{ route('teacher.students.show', [$class, $row['student']]) }}"
                                class="inline-flex items-center gap-2 hover:text-amber-300 transition-colors"
                                title="Ver ficha de {{ $row['student']->name }}"
                            >
                                @include('partials.player-avatar', ['student' => $row['student'], 'size' => 'sm'])
                                <span>
                                    <span class="underline decoration-amber-300/40 underline-offset-2">{{ $row['student']->name }}</span>
                                    @if($row['student']->arenaName())
                                        <span class="text-amber-300"> · {{ $row['student']->arenaName() }}</span>
                                    @endif
                                    @include('partials.cosmetic-title', ['student' => $row['student']])
                                </span>
                            </a>
                        @else
                            <span class="inline-flex items-center gap-2">
                                @include('partials.player-avatar', ['student' => $row['student'], 'size' => 'sm'])
                                <span>
                                    {{ $row['student']->name }}
                                    @if($row['student']->arenaName())
                                        <span class="text-amber-300"> · {{ $row['student']->arenaName() }}</span>
                                    @endif
                                    @include('partials.cosmetic-title', ['student' => $row['student']])
                                </span>
                            </span>
                        @endif
                    </p>
                    <p class="text-sm text-amber-100/55 flex flex-wrap items-center gap-2">
                        @include('partials.class-badge', ['student' => $row['student']])
                        <span>· {{ $row['team'] ?: 'Sem guilda' }} · <span data-xp-text>{{ $row['xp'] }}</span> XP</span>
                    </p>
                    <p class="text-xs text-amber-100/45">Nível <span data-level>{{ $row['level_name'] }}</span></p>
                    <div class="xp-track mt-2">
                        <div class="xp-fill" data-xp-fill="{{ $row['xp_progress'] }}"></div>
                    </div>
                    <p class="text-xs text-amber-100/45 mt-1">Medalhas: <span data-badge-count>{{ $row['badge_count'] }}</span></p>
                </div>
                <div class="text-right shrink-0">
                    <p class="score-chip" data-avg>{{ number_format($row['average'], 1) }}</p>
                    <p class="text-xs text-amber-100/45">nota</p>
                </div>
            </div>
        @empty
            <div class="game-card p-6 text-center text-amber-100/70">Nenhum jogador autorizou aparecer no ranking.</div>
        @endforelse
    </div>

    <div x-show="tab === 'guildas'" x-cloak>
        @php
            $top = array_slice($guilds, 0, 3);
        @endphp
        <div class="grid md:grid-cols-3 gap-4 mb-6 items-end">
            @foreach($top as $row)
                <a href="{{ route('ranking.guild', [$class, $row['team']]) }}"
                   class="game-card game-card-glow podium-item p-5 text-center {{ $row['position'] === 1 ? 'md:-translate-y-4 border-amber-400/50' : '' }}">
                    @if($row['position'] === 1)
                        <p class="podium-crown text-amber-300 font-display text-sm tracking-[0.2em] uppercase mb-1">Campeã</p>
                    @endif
                    <p class="text-4xl">{{ $row['team']->emblemIcon() }}</p>
                    <p class="font-display text-xl text-amber-300 mt-2">{{ $row['position'] }}º · {{ $row['team']->name }}</p>
                    <p class="score-chip mt-1">{{ number_format($row['score'], 1) }}</p>
                </a>
            @endforeach
        </div>
        <div class="space-y-3" data-guild-list>
            @foreach($guilds as $row)
                <a href="{{ route('ranking.guild', [$class, $row['team']]) }}"
                   class="game-card game-card-glow battle-row p-4 flex items-center gap-4"
                   data-guild-id="{{ $row['team']->id }}">
                    <div class="rank-badge {{ $row['position'] === 1 ? 'is-gold' : ($row['position'] === 2 ? 'is-silver' : ($row['position'] === 3 ? 'is-bronze' : '')) }}" data-pos>
                        {{ $row['position'] }}º
                    </div>
                    <div class="text-3xl" data-team-emblem>{{ $row['team']->emblemIcon() }}</div>
                    <div class="flex-1">
                        <p class="font-semibold">{{ $row['team']->name }}</p>
                        <p class="text-sm text-amber-100/55">{{ $row['members'] }} membros</p>
                        <div class="xp-track mt-2"><div class="xp-fill" data-xp-fill="{{ $row['score'] }}"></div></div>
                    </div>
                    <div class="score-chip" data-score>{{ number_format($row['score'], 1) }}</div>
                </a>
            @endforeach
        </div>
    </div>

    <div x-show="tab === 'arena'" x-cloak class="space-y-3">
        <div class="game-card p-4 mb-2">
            <p class="text-sm text-amber-100/65">
                Placar de Glória dos duelos RPG.
                {{ $arenaOpen ? 'A arena desta turma está aberta.' : 'A arena desta turma está fechada no momento.' }}
                Glória não altera a média nem o ranking acadêmico.
            </p>
        </div>
        @forelse($arenaHall as $row)
            <div class="game-card battle-row {{ $row['student']->characterAuraClass() }} p-4 flex items-center gap-4">
                @include('partials.class-fx', ['characterClass' => $row['student']->character_class])
                <div class="rank-badge {{ $row['position'] === 1 ? 'is-gold' : ($row['position'] === 2 ? 'is-silver' : ($row['position'] === 3 ? 'is-bronze' : '')) }}">
                    {{ $row['position'] }}º
                </div>
                <div class="flex-1 min-w-0">
                    <p class="font-semibold truncate">
                        <span class="inline-flex items-center gap-2">
                            @include('partials.player-avatar', ['student' => $row['student'], 'size' => 'sm'])
                            <span>
                                {{ $row['student']->name }}
                                @if($row['student']->arenaName())
                                    <span class="text-amber-300"> · {{ $row['student']->arenaName() }}</span>
                                @endif
                                @include('partials.cosmetic-title', ['student' => $row['student']])
                            </span>
                        </span>
                    </p>
                    <p class="text-sm text-amber-100/55">{{ $row['arena_wins'] }}V – {{ $row['arena_losses'] }}D</p>
                </div>
                <div class="text-right shrink-0">
                    <p class="score-chip">{{ $row['glory'] }}</p>
                    <p class="text-xs text-amber-100/45">glória</p>
                </div>
            </div>
        @empty
            <div class="game-card p-6 text-center text-amber-100/70">Ninguém conquistou Glória ainda.</div>
        @endforelse
    </div>
</div>
@endsection
