@extends('layouts.game')

@section('title', 'Temporadas — '.$area->name)

@section('content')
<div class="mb-4">
    <a href="{{ route('areas.show', $area) }}" class="game-btn-ghost text-sm">← {{ $area->name }}</a>
</div>

<div class="hero-banner reveal">
    <p class="hero-kicker">Campanha do reino</p>
    <h1 class="hero-title">Temporadas</h1>
    <p class="text-amber-100/70 mt-3 max-w-xl mx-auto">Só as turmas de <strong class="text-amber-200">{{ $area->name }}</strong> entram nesta disputa.</p>
</div>

<div class="grid md:grid-cols-2 gap-4">
    @forelse($seasons as $season)
        <a href="{{ route('areas.seasons.show', [$area, $season]) }}" class="game-card game-card-glow p-6 block reveal reveal-delay-{{ $loop->iteration % 5 + 1 }}">
            <p class="text-xs uppercase tracking-[0.2em] text-orange-300/80 mb-2">Temporada ativa</p>
            <h2 class="font-display text-2xl text-amber-200">{{ $season->name }}</h2>
            @if($season->description)
                <p class="text-amber-100/65 text-sm mt-2">{{ $season->description }}</p>
            @endif
            <p class="text-amber-100/45 text-xs mt-3">{{ $season->classes_count }} turma(s) no campo</p>
            <p class="mt-4 text-sm text-amber-200/80">Abrir ranking →</p>
        </a>
    @empty
        <div class="game-card p-8 col-span-2 text-center text-amber-100/60 reveal">
            Nenhuma temporada neste reino ainda.
        </div>
    @endforelse
</div>
@endsection
