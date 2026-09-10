@extends('layouts.game')

@section('title', 'Eventos — '.$class->name)

@section('content')
<div class="flex flex-wrap items-start justify-between gap-4 mb-6">
    <div>
        <h1 class="font-display text-4xl text-amber-300">Eventos</h1>
        <p class="text-amber-100/65">{{ $class->name }} · quizzes da turma e do reino</p>
    </div>
    <a class="game-btn-ghost" href="{{ route('student.dashboard') }}">Voltar à ficha</a>
</div>

<div class="space-y-6">
    <div class="game-card p-5">
        <h2 class="font-display text-xl text-amber-200 mb-4">Da turma</h2>
        @forelse($classEvents as $event)
            <a href="{{ route('student.events.show', $event) }}" class="flex flex-wrap items-center justify-between gap-3 border-b border-purple-900/40 py-3 hover:bg-purple-950/40 rounded px-2">
                <div>
                    <p class="font-semibold text-amber-100">{{ $event->title }}</p>
                    <p class="text-xs text-purple-200/60">{{ $event->kindLabel() }} · {{ $event->modeLabel() }} · {{ $event->statusLabel() }}</p>
                </div>
                <span class="game-btn-ghost text-xs">Abrir</span>
            </a>
        @empty
            <p class="text-sm text-purple-200/60">Nenhum evento da turma no momento.</p>
        @endforelse
    </div>

    <div class="game-card p-5">
        <h2 class="font-display text-xl text-amber-200 mb-4">Do reino</h2>
        @forelse($realmEvents as $event)
            <a href="{{ route('student.events.show', $event) }}" class="flex flex-wrap items-center justify-between gap-3 border-b border-purple-900/40 py-3 hover:bg-purple-950/40 rounded px-2">
                <div>
                    <p class="font-semibold text-amber-100">{{ $event->title }}</p>
                    <p class="text-xs text-purple-200/60">{{ $event->statusLabel() }} · prêmio {{ $event->prizeItem?->icon }} {{ $event->prizeItem?->name }}</p>
                </div>
                <span class="game-btn-ghost text-xs">Abrir</span>
            </a>
        @empty
            <p class="text-sm text-purple-200/60">Nenhum evento do reino no momento.</p>
        @endforelse
    </div>
</div>
@endsection
