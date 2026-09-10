@extends('layouts.game')

@section('title', $event->title.' — Reino')

@section('content')
<div class="mb-6">
    <a href="{{ route('admin.areas.events.index', $area) }}" class="game-btn-ghost text-sm">← Eventos</a>
    <h1 class="font-display text-4xl text-amber-300 mt-2">{{ $event->title }}</h1>
    <p class="text-amber-100/65">{{ $area->name }} · {{ $event->modeLabel() }} · {{ $event->statusLabel() }}</p>
</div>

<div class="flex flex-wrap gap-2 mb-6">
    @if($event->isLiveMode() && in_array($event->status, ['draft', 'scheduled'], true))
        <form method="POST" action="{{ route('admin.areas.events.start', [$area, $event]) }}">
            @csrf
            <button class="game-btn" type="submit">Iniciar ao vivo</button>
        </form>
    @endif
    @if(! $event->isClosed())
        <form method="POST" action="{{ route('admin.areas.events.close', [$area, $event]) }}">
            @csrf
            <button class="game-btn-ghost" type="submit">Encerrar</button>
        </form>
    @endif
</div>

<div class="grid lg:grid-cols-2 gap-6">
    <div class="game-card p-5">
        <h2 class="font-display text-xl text-amber-200 mb-3">Perguntas</h2>
        <ol class="space-y-3 text-sm">
            @foreach($event->questions as $question)
                <li>
                    <p class="font-semibold">{{ $question->position + 1 }}. {{ $question->prompt }}</p>
                    <p class="text-purple-200/60">Certa: {{ $question->options[$question->correct_index] ?? '—' }}</p>
                </li>
            @endforeach
        </ol>
        @if($event->prizeItem)
            <p class="mt-4 text-violet-200 text-sm">Prêmio: {{ $event->prizeItem->icon }} {{ $event->prizeItem->name }}</p>
        @endif
    </div>
    <div class="game-card p-5">
        <h2 class="font-display text-xl text-amber-200 mb-3">Ranking</h2>
        @forelse($ranking as $index => $attempt)
            <div class="flex justify-between border-b border-purple-900/40 py-2 text-sm">
                <span>{{ $index + 1 }}. {{ $attempt->student?->name }} ({{ $attempt->schoolClass?->name }})</span>
                <span>{{ $attempt->correct_count }} · {{ number_format($attempt->correct_time_ms / 1000, 1) }}s</span>
            </div>
        @empty
            <p class="text-sm text-purple-200/60">Sem resultados.</p>
        @endforelse
    </div>
</div>
@endsection
