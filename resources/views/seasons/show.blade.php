@extends('layouts.game')

@section('title', $season->name.' — Temporada')

@section('content')
<a href="{{ route('areas.seasons.index', $area) }}" class="game-btn-ghost mb-6 inline-flex reveal">← Temporadas</a>

<div class="hero-banner !mb-6 reveal reveal-delay-1">
    <p class="hero-kicker">Temporada</p>
    <h1 class="hero-title">{{ $season->name }}</h1>
    @if($season->description)
        <p class="text-amber-100/70 mt-2">{{ $season->description }}</p>
    @endif
</div>

@if(count($ranking) === 0)
    <div class="game-card p-8 text-center text-amber-100/60 reveal">
        Nenhuma turma vinculada a esta temporada ainda.
    </div>
@else
    @php
        $top = array_slice($ranking, 0, 3);
        $levelIcons = [
            'iniciante' => 'I',
            'aprendiz' => 'A',
            'pleno' => 'P',
            'expert' => 'E',
            'mestre' => 'M',
        ];
        $levelNames = [
            'iniciante' => 'Iniciante',
            'aprendiz' => 'Aprendiz',
            'pleno' => 'Pleno',
            'expert' => 'Expert',
            'mestre' => 'Mestre',
        ];
        $podiumLabels = [1 => '1º', 2 => '2º', 3 => '3º'];
    @endphp

    <div class="grid md:grid-cols-3 gap-4 mb-8 items-end">
        @foreach($top as $row)
            <div class="game-card game-card-glow podium-item p-6 text-center {{ $row['position'] === 1 ? 'md:-translate-y-4 border-amber-400/50' : '' }}">
                @if($row['position'] === 1)
                    <p class="podium-crown text-amber-300 font-display text-xs tracking-[0.25em] uppercase mb-2">Dominadora</p>
                @endif
                <p class="font-display text-4xl text-amber-300">{{ $podiumLabels[$row['position']] ?? $row['position'].'º' }}</p>
                <p class="font-semibold text-amber-50 mt-2">{{ $row['class']->name }}</p>
                <p class="score-chip mt-2">{{ number_format($row['score'], 1) }}</p>
                <p class="text-amber-100/45 text-xs mt-1">pontos de guerra</p>
            </div>
        @endforeach
    </div>

    <div class="space-y-4">
        @foreach($ranking as $row)
            <div class="game-card battle-row p-5 reveal reveal-delay-{{ min($loop->iteration, 5) }}">
                <div class="flex flex-wrap items-center justify-between gap-4 mb-4">
                    <div class="flex items-center gap-4">
                        <div class="rank-badge {{ $row['position'] === 1 ? 'is-gold' : ($row['position'] === 2 ? 'is-silver' : ($row['position'] === 3 ? 'is-bronze' : '')) }}">
                            {{ $row['position'] }}º
                        </div>
                        <div>
                            <p class="font-semibold text-lg text-amber-50">{{ $row['class']->name }}</p>
                            <p class="text-amber-100/55 text-sm">{{ $row['student_count'] }} alunos · {{ $row['total_badges'] }} medalhas</p>
                        </div>
                    </div>
                    <div class="text-right">
                        <p class="score-chip">{{ number_format($row['score'], 1) }}</p>
                        <p class="text-amber-100/45 text-xs">score final</p>
                    </div>
                </div>

                <div class="xp-track mb-4">
                    <div class="xp-fill" data-xp-fill="{{ $row['score'] }}"></div>
                </div>

                <div class="grid grid-cols-2 md:grid-cols-4 gap-3 text-center">
                    <div class="game-card p-3">
                        <p class="text-xs text-amber-100/55 mb-1">Média individual</p>
                        <p class="font-display text-lg text-cyan-300">{{ number_format($row['avg_individual'], 1) }}</p>
                    </div>
                    <div class="game-card p-3">
                        <p class="text-xs text-amber-100/55 mb-1">Média guildas</p>
                        <p class="font-display text-lg text-cyan-300">{{ number_format($row['avg_guilds'], 1) }}</p>
                    </div>
                    <div class="game-card p-3">
                        <p class="text-xs text-amber-100/55 mb-1">Score de níveis</p>
                        <p class="font-display text-lg text-cyan-300">{{ number_format($row['level_score'], 1) }}</p>
                    </div>
                    <div class="game-card p-3">
                        <p class="text-xs text-amber-100/55 mb-1">Score de medalhas</p>
                        <p class="font-display text-lg text-cyan-300">{{ number_format($row['badge_score'], 1) }}</p>
                    </div>
                </div>

                <div class="mt-4 flex flex-wrap gap-2">
                    @foreach($row['level_distribution'] as $key => $count)
                        @if($count > 0)
                            <span class="px-2 py-1 rounded-lg bg-orange-950/50 border border-amber-500/20 text-xs text-amber-100">
                                {{ $levelIcons[$key] ?? '?' }} {{ $count }} {{ $levelNames[$key] ?? $key }}
                            </span>
                        @endif
                    @endforeach
                    @if(array_sum($row['level_distribution']) === 0)
                        <span class="text-xs text-amber-100/45">Sem alunos com XP ainda</span>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
@endif
@endsection
