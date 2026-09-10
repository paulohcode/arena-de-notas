@extends('layouts.game')

@section('title', 'Arena entre turmas — '.$class->name)

@section('content')
<div class="flex flex-wrap items-start justify-between gap-4 mb-6 reveal">
    <div>
        <p class="hero-kicker !mb-1">Duelos do reino</p>
        <h1 class="font-display text-4xl text-violet-300">Arena entre turmas</h1>
        <p class="text-amber-100/60 mt-1">
            {{ $class->name }}
            @if($area)
                · Reino {{ $area->name }}
            @endif
            · {{ $realmAuras }} Aura
        </p>
    </div>
    <div class="flex flex-wrap gap-2">
        <a class="game-btn-ghost" href="{{ route('student.arena.index') }}">Arena da turma</a>
        <a class="game-btn-ghost" href="{{ route('student.shop.index') }}">Loja</a>
        <a class="game-btn-ghost" href="{{ route('student.dashboard') }}">Minha ficha</a>
    </div>
</div>

@if($pendingRealmIncoming->isNotEmpty())
    <div class="game-card p-5 mb-6 space-y-4 border-violet-400/20 reveal">
        <h2 class="font-display text-xl text-violet-200">Desafios recebidos</h2>
        @foreach($pendingRealmIncoming as $duel)
            <div class="flex flex-wrap items-center justify-between gap-3 py-3 border-b border-purple-900/40">
                <div class="flex items-center gap-3 min-w-0">
                    @include('partials.player-avatar', ['student' => $duel->challenger, 'size' => 'sm'])
                    <div class="min-w-0">
                        <p class="font-semibold truncate">{{ $duel->challenger->name }}
                            @if($duel->challenger->arenaName())
                                <span class="text-amber-300"> · {{ $duel->challenger->arenaName() }}</span>
                            @endif
                        </p>
                        <p class="text-xs text-amber-100/50">{{ $duel->challengerClass->name }} · por Aura</p>
                    </div>
                </div>
                <div class="flex gap-2">
                    <form method="POST" action="{{ route('student.arena.realm.accept', $duel) }}">
                        @csrf
                        <button class="game-btn !py-1 !px-3 text-sm" type="submit">Aceitar</button>
                    </form>
                    <form method="POST" action="{{ route('student.arena.realm.decline', $duel) }}">
                        @csrf
                        <button class="game-btn-ghost !py-1 !px-3 text-sm" type="submit">Recusar</button>
                    </form>
                </div>
            </div>
        @endforeach
    </div>
@endif

<div class="reveal mb-8">
    @include('partials.realm-duel-panel', [
        'hideKicker' => true,
        'heading' => 'Desafiar outra turma',
        'cardClass' => '',
    ])
</div>
@endsection
