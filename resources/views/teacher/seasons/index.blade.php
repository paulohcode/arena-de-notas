@extends('layouts.game')

@section('title', 'Temporadas — Professor')

@section('content')
<div class="flex flex-wrap items-center justify-between gap-4 mb-8">
    <div>
        <h1 class="font-display text-4xl text-amber-300">Temporadas</h1>
        <p class="text-purple-200/70">Crie desafios inter-turmas, o Rito do chefão e acompanhe o ranking.</p>
    </div>
    <a class="game-btn" href="{{ route('teacher.seasons.create') }}">Nova temporada</a>
</div>

<div class="space-y-6">
    @forelse($seasons as $season)
        @php
            $boss = $season->bossMeta();
            $ritesByClass = $season->classRites->keyBy('class_id');
        @endphp
        <div class="game-card game-card-glow p-5 space-y-4">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h2 class="font-display text-xl text-amber-200">{{ $season->name }}</h2>
                    <p class="text-amber-100/55 text-xs mt-1">{{ $season->area?->emblemIcon() }} {{ $season->area?->name }}</p>
                    @if($season->description)
                        <p class="text-amber-100/70 text-sm mt-1">{{ $season->description }}</p>
                    @endif
                    <p class="text-amber-100/45 text-xs mt-1">{{ $season->classes_count }} turma(s) participando</p>
                    @if($boss)
                        <p class="text-sm text-violet-200 mt-2">
                            <span class="text-2xl align-middle">{{ $boss['icon'] }}</span>
                            {{ $season->bossDisplayName() }}
                            · {{ \App\Support\BossArchetypeCatalog::difficultyLabel($season->boss_difficulty) }}
                            · Vigília {{ $season->vigil_open ? 'aberta' : 'fechada' }}
                        </p>
                    @endif
                </div>
                <div class="flex gap-2 flex-wrap">
                    @if($season->area)
                        <a href="{{ route('areas.seasons.show', [$season->area, $season]) }}" class="game-btn-ghost text-sm">Ver ranking</a>
                    @endif
                    <a href="{{ route('teacher.seasons.edit', $season) }}" class="game-btn-ghost text-sm">Editar</a>
                    @if($boss)
                        <a href="{{ route('teacher.seasons.boss', $season) }}" class="game-btn text-sm">Mesa do chefão</a>
                        @if($season->vigil_open)
                            <form method="POST" action="{{ route('teacher.seasons.vigil.close', $season) }}">
                                @csrf
                                <button type="submit" class="game-btn-ghost text-sm">Fechar Vigília</button>
                            </form>
                        @else
                            <form method="POST" action="{{ route('teacher.seasons.vigil.open', $season) }}">
                                @csrf
                                <button type="submit" class="game-btn text-sm">Abrir Vigília</button>
                            </form>
                        @endif
                    @endif
                    <form method="POST" action="{{ route('teacher.seasons.destroy', $season) }}">
                        @csrf @method('DELETE')
                        <button type="submit" class="text-rose-300 text-sm hover:text-rose-200"
                            onclick="return confirm('Excluir temporada?')">Excluir</button>
                    </form>
                </div>
            </div>

            @if($boss && $season->classes->isNotEmpty())
                <div class="border-t border-amber-400/15 pt-4 space-y-3">
                    <h3 class="font-display text-lg text-violet-200">Rito por turma</h3>
                    @foreach($season->classes as $class)
                        @php
                            $rite = $ritesByClass->get($class->id);
                            $marks = $markCounts[$season->id][$class->id] ?? 0;
                        @endphp
                        <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg bg-black/20 px-3 py-3">
                            <div>
                                <p class="font-semibold text-amber-50">{{ $class->name }}</p>
                                <p class="text-xs text-amber-100/50">
                                    {{ $marks }} Marca(s) do Rito
                                    @if($rite)
                                        · {{ $rite->status === 'resolved'
                                            ? ($rite->wasBroken() ? 'Rito quebrado' : 'Rito resistiu')
                                            : ($rite->status === 'open' ? 'Assalto aberto' : 'Aguardando') }}
                                        @if($rite->isOpen() || $rite->isResolved())
                                            · HP {{ $rite->boss_hp }}/{{ $rite->boss_max_hp }}
                                        @endif
                                    @else
                                        · Rito ainda não aberto
                                    @endif
                                </p>
                            </div>
                            <div class="flex gap-2 flex-wrap">
                                @if(! $rite || $rite->status === 'pending')
                                    <form method="POST" action="{{ route('teacher.seasons.rite.open', [$season, $class]) }}">
                                        @csrf
                                        <button type="submit" class="game-btn !py-1 !px-3 text-sm">Abrir Rito</button>
                                    </form>
                                @elseif($rite->isOpen())
                                    <form method="POST" action="{{ route('teacher.seasons.rite.resolve', [$season, $class]) }}">
                                        @csrf
                                        <button type="submit" class="game-btn !py-1 !px-3 text-sm"
                                            onclick="return confirm('Resolver o assalto agora? A turma luta em sequência.')">Resolver assalto</button>
                                    </form>
                                @elseif($rite->isResolved())
                                    <a href="{{ route('teacher.seasons.rite.show', [$season, $rite]) }}" class="game-btn-ghost !py-1 !px-3 text-sm">Assistir Rito</a>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
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
