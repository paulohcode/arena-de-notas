@extends('layouts.game')

@section('title', 'Eventos do reino — '.$area->name)

@section('content')
<div class="mb-6">
    <a href="{{ route('admin.areas.index') }}" class="game-btn-ghost text-sm">← Reinos</a>
    <h1 class="font-display text-4xl text-amber-300 mt-2">{{ $area->emblemIcon() }} Eventos do reino</h1>
    <p class="text-amber-100/65">{{ $area->name }} · item exclusivo ao melhor aluno</p>
</div>

<form method="POST" action="{{ route('admin.areas.events.store', $area) }}" class="game-card p-5 space-y-4 mb-6">
    @csrf
    <h2 class="font-display text-xl text-amber-200">Novo evento</h2>
    <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-3">
        <label class="space-y-1 sm:col-span-2">
            <span class="text-xs uppercase tracking-wide text-purple-200/70">Título</span>
            <input class="game-input" name="title" value="{{ old('title') }}" required maxlength="120">
        </label>
        <label class="space-y-1">
            <span class="text-xs uppercase tracking-wide text-purple-200/70">Modo</span>
            <select class="game-select" name="mode">
                <option value="window">Janela de tempo</option>
                <option value="live">Ao vivo</option>
            </select>
        </label>
        <label class="space-y-1">
            <span class="text-xs uppercase tracking-wide text-purple-200/70">Segundos / pergunta</span>
            <input class="game-input" type="number" name="question_seconds" value="{{ old('question_seconds', 30) }}" min="5" max="300" required>
        </label>
        <label class="space-y-1">
            <span class="text-xs uppercase tracking-wide text-purple-200/70">Início</span>
            <input class="game-input" type="datetime-local" name="starts_at" value="{{ old('starts_at') }}">
        </label>
        <label class="space-y-1">
            <span class="text-xs uppercase tracking-wide text-purple-200/70">Fim</span>
            <input class="game-input" type="datetime-local" name="ends_at" value="{{ old('ends_at') }}">
        </label>
    </div>
    <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-3">
        <label class="space-y-1">
            <span class="text-xs uppercase tracking-wide text-purple-200/70">Item — nome</span>
            <input class="game-input" name="prize_name" value="{{ old('prize_name') }}" required>
        </label>
        <label class="space-y-1">
            <span class="text-xs uppercase tracking-wide text-purple-200/70">Slot</span>
            <select class="game-select" name="prize_slot">
                @foreach($slots as $slot => $label)
                    <option value="{{ $slot }}">{{ $label }}</option>
                @endforeach
            </select>
        </label>
        <label class="space-y-1">
            <span class="text-xs uppercase tracking-wide text-purple-200/70">Ícone</span>
            <input class="game-input" name="prize_icon" value="{{ old('prize_icon', '👑') }}" required>
        </label>
        <label class="space-y-1">
            <span class="text-xs uppercase tracking-wide text-purple-200/70">Raridade</span>
            <select class="game-select" name="prize_rarity">
                @foreach($rarities as $rarity => $label)
                    <option value="{{ $rarity }}" @selected($rarity === 'epic')>{{ $label }}</option>
                @endforeach
            </select>
        </label>
    </div>
    @include('partials.quiz-questions-form')
    <button class="game-btn" type="submit">Criar evento do reino</button>
</form>

<div class="game-card p-5">
    <h2 class="font-display text-xl text-amber-200 mb-4">Eventos</h2>
    @forelse($events as $event)
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-purple-900/40 py-3">
            <div>
                <p class="font-semibold text-amber-100">{{ $event->title }}</p>
                <p class="text-xs text-purple-200/60">{{ $event->statusLabel() }} · {{ $event->prizeItem?->icon }} {{ $event->prizeItem?->name }}</p>
            </div>
            <a class="game-btn-ghost text-xs" href="{{ route('admin.areas.events.show', [$area, $event]) }}">Abrir</a>
        </div>
    @empty
        <p class="text-sm text-purple-200/60">Nenhum evento ainda.</p>
    @endforelse
</div>
@endsection
