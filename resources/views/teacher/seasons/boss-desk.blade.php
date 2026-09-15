@extends('layouts.game')

@section('title', 'Mesa do Chefão — '.$season->name)

@section('content')
<a href="{{ route('teacher.seasons.index') }}" class="game-btn-ghost mb-6 inline-flex">← Temporadas</a>

<div class="hero-banner !mb-6 reveal">
    <p class="hero-kicker">Você é o chefão</p>
    <h1 class="hero-title flex flex-wrap items-center gap-3">
        <span class="text-5xl">{{ $boss['icon'] }}</span>
        <span>{{ $season->bossDisplayName() }}</span>
    </h1>
    <p class="text-amber-100/70 mt-2 max-w-2xl">{{ $boss['blurb'] }}</p>
    <p class="text-xs text-amber-100/45 mt-2">
        {{ \App\Support\BossArchetypeCatalog::difficultyLabel($season->boss_difficulty) }}
        · Desafie alunos; eles precisam aceitar (como duelo 1v1)
        · Provocações do staff não geram Marca do Rito
    </p>
</div>

@if($errors->any())
    <div class="game-card p-4 mb-6 border-rose-400/30 text-rose-200 text-sm">
        {{ $errors->first() }}
    </div>
@endif

<div class="space-y-6">
    @forelse($roster as $row)
        <div class="game-card p-5 space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="font-display text-xl text-amber-200">{{ $row['class']->name }}</h2>
                <p class="text-xs text-amber-100/45">{{ count($row['students']) }} aluno(s)</p>
            </div>

            @if(empty($row['students']))
                <p class="text-sm text-amber-100/55">Nenhum aluno nesta turma.</p>
            @else
                <div class="space-y-2">
                    @foreach($row['students'] as $entry)
                        @php $peer = $entry['user']; @endphp
                        <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg bg-black/20 px-3 py-3">
                            <div class="flex items-center gap-3 min-w-0">
                                @include('partials.player-avatar', ['student' => $peer, 'size' => 'sm'])
                                <div class="min-w-0">
                                    <p class="font-semibold truncate">
                                        {{ $peer->name }}
                                        @if($peer->arenaName())
                                            <span class="text-amber-300"> · {{ $peer->arenaName() }}</span>
                                        @endif
                                    </p>
                                    <p class="text-xs text-amber-100/50">
                                        {{ $peer->characterClassLabel() }}
                                        @if($entry['notice'] === null)
                                            · poder {{ number_format($entry['power'], 2) }}
                                        @endif
                                    </p>
                                    @if($entry['notice'])
                                        <p class="text-xs text-rose-300/80 mt-1">{{ $entry['notice'] }}</p>
                                    @endif
                                </div>
                            </div>
                            <div>
                                    @if(! empty($entry['pending']) && ! empty($entry['pending_vigil_id']))
                                    <a href="{{ route('teacher.seasons.vigil.show', [$season, $entry['pending_vigil_id']]) }}"
                                       class="game-btn-ghost !py-1 !px-3 text-sm">
                                        Aguardando…
                                    </a>
                                @elseif($entry['notice'])
                                    <button type="button" class="game-btn-ghost !py-1 !px-3 text-sm opacity-40 cursor-not-allowed" disabled>
                                        Indisponível
                                    </button>
                                @else
                                    <form method="POST" action="{{ route('teacher.seasons.boss.challenge', $season) }}">
                                        @csrf
                                        <input type="hidden" name="class_id" value="{{ $row['class']->id }}">
                                        <input type="hidden" name="student_id" value="{{ $peer->id }}">
                                        <button type="submit" class="game-btn !py-1 !px-3 text-sm">
                                            Desafiar
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    @empty
        <div class="game-card p-8 text-center text-amber-100/60">
            Vincule turmas a esta temporada para desafiar alunos.
        </div>
    @endforelse
</div>

@if($recentChallenges->isNotEmpty())
    <div class="game-card p-5 mt-8 space-y-3">
        <h2 class="font-display text-xl text-violet-200">Provocações recentes</h2>
        <ul class="space-y-2">
                    @foreach($recentChallenges as $challenge)
                <li class="flex flex-wrap items-center justify-between gap-2 text-sm border-b border-purple-900/40 py-2">
                    <span>
                        @if($challenge->isPending())
                            <span class="text-cyan-300">Aguardando aceite</span>
                        @elseif($challenge->status === \App\Models\BossVigil::STATUS_DECLINED)
                            <span class="text-amber-100/55">Recusado</span>
                        @else
                            <span class="{{ $challenge->won ? 'text-rose-300' : 'text-emerald-300' }}">
                                {{ $challenge->won ? 'Aluno venceu' : 'Chefão venceu' }}
                            </span>
                        @endif
                        · {{ $challenge->student?->arenaName() ?: $challenge->student?->name }}
                        <span class="text-amber-100/45">({{ $challenge->schoolClass?->name }})</span>
                    </span>
                    <a class="text-amber-200 underline text-xs" href="{{ route('teacher.seasons.vigil.show', [$season, $challenge]) }}">
                        {{ $challenge->isPending() ? 'Sala de espera' : 'Assistir' }}
                    </a>
                </li>
            @endforeach
        </ul>
    </div>
@endif
@endsection
