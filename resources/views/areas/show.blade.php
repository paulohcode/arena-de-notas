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
    <div class="mt-6 flex flex-wrap justify-center gap-3">
        <a href="{{ route('areas.seasons.index', $area) }}" class="game-btn">Temporadas deste reino</a>
    </div>
</div>

<div class="grid md:grid-cols-2 gap-4">
    @forelse($area->classes as $class)
        <a href="{{ route('ranking.show', $class) }}" class="game-card game-card-glow p-6 block reveal reveal-delay-{{ $loop->iteration % 5 + 1 }}">
            <p class="text-xs uppercase tracking-[0.2em] text-orange-300/80 mb-2">Turma em disputa</p>
            <h2 class="font-display text-2xl text-amber-200">{{ $class->name }}</h2>
            <p class="text-amber-100/60 mt-2">{{ $class->year ?: 'Sem ano' }} · modo {{ $class->score_mode === 'up_from_zero' ? 'sobe do 0' : 'cai do 100' }}</p>
            <p class="mt-4 text-sm text-amber-200/80">Abrir ranking →</p>
        </a>
    @empty
        <div class="game-card p-8 col-span-2 text-center text-amber-100/70 reveal">Nenhuma turma neste reino ainda.</div>
    @endforelse
</div>
@endsection
