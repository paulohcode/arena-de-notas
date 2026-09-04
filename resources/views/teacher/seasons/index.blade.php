@extends('layouts.game')

@section('title', 'Temporadas — Professor')

@section('content')
<div class="flex flex-wrap items-center justify-between gap-4 mb-8">
    <div>
        <h1 class="font-display text-4xl text-amber-300">Temporadas</h1>
        <p class="text-purple-200/70">Crie desafios inter-turmas e acompanhe o ranking.</p>
    </div>
    <a class="game-btn" href="{{ route('teacher.seasons.create') }}">Nova temporada</a>
</div>

<div class="space-y-4">
    @forelse($seasons as $season)
        <div class="game-card game-card-glow p-5 flex flex-wrap items-center justify-between gap-4">
            <div>
                <h2 class="font-display text-xl text-amber-200">{{ $season->name }}</h2>
                <p class="text-amber-100/55 text-xs mt-1">{{ $season->area?->emblemIcon() }} {{ $season->area?->name }}</p>
                @if($season->description)
                    <p class="text-amber-100/70 text-sm mt-1">{{ $season->description }}</p>
                @endif
                <p class="text-amber-100/45 text-xs mt-1">{{ $season->classes_count }} turma(s) participando</p>
            </div>
            <div class="flex gap-2 flex-wrap">
                @if($season->area)
                    <a href="{{ route('areas.seasons.show', [$season->area, $season]) }}" class="game-btn-ghost text-sm">Ver ranking</a>
                @endif
                <a href="{{ route('teacher.seasons.edit', $season) }}" class="game-btn-ghost text-sm">Editar</a>
                <form method="POST" action="{{ route('teacher.seasons.destroy', $season) }}">
                    @csrf @method('DELETE')
                    <button type="submit" class="text-rose-300 text-sm hover:text-rose-200"
                        onclick="return confirm('Excluir temporada?')">Excluir</button>
                </form>
            </div>
        </div>
    @empty
        <div class="game-card p-8 text-center text-purple-200/60">
            <p class="text-3xl mb-3">🏆</p>
            <p>Nenhuma temporada criada ainda.</p>
            <a href="{{ route('teacher.seasons.create') }}" class="game-btn mt-4 inline-block">Criar primeira temporada</a>
        </div>
    @endforelse
</div>
@endsection
