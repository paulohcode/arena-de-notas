@extends('layouts.game')

@section('title', $area->name.' — Arena das Notas')

@section('content')
<div class="mb-4">
    <a href="{{ route('home') }}" class="game-btn-ghost text-sm">← Voltar ao mapa</a>
</div>

<div class="hero-banner reveal">
    <p class="hero-kicker" style="color: {{ $area->color }}">{{ $area->emblemIcon() }} Reino</p>
    <h1 class="hero-title">{{ $area->name }}</h1>
    @if($area->description)
        <p class="text-amber-100/70 mt-3 max-w-xl mx-auto">{{ $area->description }}</p>
    @endif
    <p class="text-amber-100/60 mt-3 max-w-xl mx-auto">As turmas deste reino competem entre si. Quem lidera o ranking de guerra abre o Hall.</p>
    <div class="mt-6 flex flex-wrap justify-center gap-3">
        <a href="{{ route('areas.seasons.index', $area) }}" class="game-btn">Temporadas deste reino</a>
    </div>
</div>

<h2 class="font-display text-2xl text-amber-200 mb-4 reveal">Ranking das turmas</h2>

<div class="grid md:grid-cols-2 gap-4">
    @forelse($ranking as $row)
        @php $class = $row['class']; @endphp
        <a href="{{ route('ranking.show', $class) }}" class="game-card game-card-glow p-6 block reveal reveal-delay-{{ $loop->iteration % 5 + 1 }}">
            <div class="flex items-start gap-4">
                <div class="rank-badge {{ $row['position'] === 1 ? 'is-gold' : ($row['position'] === 2 ? 'is-silver' : ($row['position'] === 3 ? 'is-bronze' : '')) }}">
                    {{ $row['position'] }}º
                </div>
                <div class="min-w-0 flex-1">
                    <p class="text-xs uppercase tracking-[0.2em] text-orange-300/80 mb-2">Turma em disputa</p>
                    <h3 class="font-display text-2xl text-amber-200">{{ $class->name }}</h3>
                    <p class="text-amber-100/60 mt-2">{{ $class->year ?: 'Sem ano' }} · modo {{ $class->score_mode === 'up_from_zero' ? 'sobe do 0' : 'cai do 100' }}</p>
                    <p class="score-chip mt-3">{{ number_format($row['score'], 1) }}</p>
                    <p class="text-amber-100/45 text-xs mt-1">pontos de guerra · {{ $row['student_count'] }} aluno{{ $row['student_count'] === 1 ? '' : 's' }}</p>
                    <p class="mt-4 text-sm text-amber-200/80">Abrir ranking →</p>
                </div>
            </div>
        </a>
    @empty
        <div class="game-card p-8 col-span-2 text-center text-amber-100/70 reveal">Nenhuma turma neste reino ainda.</div>
    @endforelse
</div>
@endsection
