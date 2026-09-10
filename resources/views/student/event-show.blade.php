@extends('layouts.game')

@section('title', $event->title)

@section('content')
<div class="mb-6 flex flex-wrap items-start justify-between gap-4">
    <div>
        <a href="{{ route('student.events.index') }}" class="game-btn-ghost text-sm">← Eventos</a>
        <h1 class="font-display text-4xl text-amber-300 mt-2">{{ $event->title }}</h1>
        <p class="text-amber-100/65">{{ $event->kindLabel() }} · {{ $event->modeLabel() }} · {{ $event->statusLabel() }}</p>
    </div>
</div>

@if(($state['attempt']['finished'] ?? false) || $event->isClosed())
    <div class="game-card p-5 mb-6">
        <h2 class="font-display text-xl text-amber-200 mb-2">Seu resultado</h2>
        @if($state['attempt'] ?? null)
            <p class="text-amber-100">{{ $state['attempt']['correct_count'] }} acerto(s) · {{ number_format(($state['attempt']['correct_time_ms'] ?? 0) / 1000, 1) }}s nas certas</p>
        @else
            <p class="text-purple-200/70 text-sm">Você não participou.</p>
        @endif
        @if($event->prizeItem && $event->awarded_at)
            <p class="text-sm text-violet-200 mt-2">Prêmio do evento: {{ $event->prizeItem->icon }} {{ $event->prizeItem->name }}</p>
        @endif
    </div>

    <div class="game-card p-5">
        <h2 class="font-display text-xl text-amber-200 mb-3">Ranking</h2>
        @forelse($ranking as $index => $attempt)
            <div class="flex justify-between border-b border-purple-900/40 py-2 text-sm {{ $attempt->student_id === auth()->id() ? 'text-amber-200' : '' }}">
                <span>{{ $index + 1 }}. {{ $attempt->student?->arenaName() ?: $attempt->student?->name }}</span>
                <span>{{ $attempt->correct_count }} · {{ number_format($attempt->correct_time_ms / 1000, 1) }}s</span>
            </div>
        @empty
            <p class="text-sm text-purple-200/60">Sem resultados ainda.</p>
        @endforelse
    </div>
@else
    <div
        class="game-card p-5 space-y-4"
        x-data="quizEventPlayer({
            pollUrl: @js($pollUrl),
            answerUrl: @js($answerUrl),
            joinUrl: @js($joinUrl),
            csrf: @js(csrf_token()),
            initialState: @js($state),
        })"
        x-init="init()"
    >
        <div class="flex flex-wrap items-center justify-between gap-3">
            <p class="text-sm text-purple-200/70">
                <span x-text="(state.attempt?.answered_count || 0) + ' / ' + (state.event?.question_count || 0)"></span> respondidas
            </p>
            <p class="font-display text-2xl text-amber-300" x-show="secondsLeft !== null" x-text="secondsLeft + 's'"></p>
        </div>

        <template x-if="!state.question && state.event?.status !== 'live' && state.event?.mode === 'live'">
            <p class="text-purple-200/70">Aguardando o início ao vivo…</p>
        </template>

        <template x-if="!state.question && state.event?.status === 'live'">
            <p class="text-purple-200/70">Aguarde a próxima pergunta…</p>
        </template>

        <template x-if="state.question">
            <div class="space-y-4">
                <h2 class="font-display text-2xl text-amber-200" x-text="state.question.prompt"></h2>
                <div class="grid gap-2">
                    <template x-for="(option, index) in state.question.options" :key="index">
                        <button type="button" class="game-btn-ghost text-left justify-start" :disabled="loading" @click="answer(index)" x-text="option"></button>
                    </template>
                </div>
            </div>
        </template>

        <p class="text-sm text-rose-300" x-show="error" x-text="error"></p>
    </div>
@endif
@endsection
