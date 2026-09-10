@extends('layouts.game')

@section('title', 'Minha ficha — '.$class->name)

@section('content')
<div class="flex flex-wrap items-start justify-between gap-4 mb-6 reveal">
    <div class="game-card {{ $student->characterAuraClass() }} p-5 flex items-start gap-4 min-w-0 flex-1">
        @include('partials.class-fx', ['characterClass' => $student->character_class])
        @include('partials.player-avatar', [
            'student' => $student,
            'size' => 'lg',
            'enrollment' => $enrollment,
            'avatarKey' => $student->pending_character_avatar ?? $student->character_avatar,
        ])
        <div class="min-w-0">
            <p class="hero-kicker !mb-1">Ficha de combate</p>
            <h1 class="font-display text-4xl text-amber-300">{{ $student->name }}</h1>
        @if($student->arenaName())
            <p class="font-display text-xl text-amber-200 mt-1">{{ $student->arenaName() }}</p>
        @endif
        @if($enrollment?->equippedTitleLabel())
            <p class="cosmetic-title mt-1">{{ $enrollment->equippedTitleLabel() }}</p>
        @endif
        <p class="text-amber-100/65 mt-2 flex flex-wrap items-center gap-2">
            @include('partials.class-badge', ['student' => $student])
            <span>· {{ $class->name }}</span>
        </p>
        @if($student->isPersonaPending())
            <p class="text-xs text-amber-200/80 mt-2">Avatar e nome de jogo aguardando o professor.</p>
        @elseif($student->isPersonaRejected())
            <p class="text-xs text-rose-200 mt-2">O último personagem foi recusado. Envie outro pedido.</p>
        @endif
        <a href="{{ route('student.character.edit') }}" class="text-xs text-amber-200/70 hover:text-amber-200 underline mt-1 inline-block">Avatar e nome de jogo</a>
        <a href="{{ route('student.arena.index') }}" class="text-xs text-cyan-300/80 hover:text-cyan-200 underline mt-1 ml-3 inline-block">Entrar na arena</a>
        <a href="{{ route('student.arena.realm.index') }}" class="text-xs text-violet-300/80 hover:text-violet-200 underline mt-1 ml-3 inline-block">Arena entre turmas</a>
        <a href="{{ route('student.events.index') }}" class="text-xs text-amber-300/80 hover:text-amber-200 underline mt-1 ml-3 inline-block">Eventos / quizzes</a>
        <a href="{{ route('student.shop.index') }}" class="text-xs text-violet-300/80 hover:text-violet-200 underline mt-1 ml-3 inline-block">Loja de cosméticos</a>
        </div>
    </div>
    <div class="flex items-center gap-3">
        @if($classes->count() > 1)
            <form method="POST" action="{{ route('student.class.switch') }}">
                @csrf
                <select class="game-select" name="class_id" onchange="this.form.submit()">
                    @foreach($classes as $item)
                        <option value="{{ $item->id }}" @selected($item->id === $class->id)>{{ $item->name }}</option>
                    @endforeach
                </select>
            </form>
        @endif
        <div class="relative" x-data="{ open: false }">
            <button type="button" class="game-btn-ghost" @click="open = !open" data-mark-read>🔔 <span data-bell-count class="{{ $notifications->isEmpty() ? 'hidden' : '' }}">{{ $notifications->count() }}</span></button>
            <div x-show="open" x-cloak class="absolute right-0 mt-2 w-80 game-card p-4 z-20 space-y-2">
                @forelse($notifications as $note)
                    @php $noteUrl = \App\Support\ArenaUrl::toLocalPath($note->data['payload']['url'] ?? null); @endphp
                    <div>
                        @if($noteUrl)
                            <a href="{{ $noteUrl }}" class="block hover:opacity-90">
                                <p class="text-amber-200 text-sm">{{ $note->data['title'] ?? 'Aviso' }}</p>
                                <p class="text-xs text-purple-200/70">{{ $note->data['message'] ?? '' }}</p>
                                <p class="text-[10px] text-cyan-300/70 mt-1">Abrir →</p>
                            </a>
                        @else
                            <p class="text-amber-200 text-sm">{{ $note->data['title'] ?? 'Aviso' }}</p>
                            <p class="text-xs text-purple-200/70">{{ $note->data['message'] ?? '' }}</p>
                        @endif
                    </div>
                @empty
                    <p class="text-sm text-purple-200/60">Nenhum aviso novo.</p>
                @endforelse
            </div>
        </div>
    </div>
</div>

@if(($pendingMissions ?? collect())->isNotEmpty())
    <div class="game-card p-4 mb-6 border-amber-400/50 text-amber-100 reveal" role="alert">
        <p class="font-semibold text-amber-200">Missões pendentes</p>
        <ul class="mt-2 space-y-1">
            @foreach($pendingMissions as $mission)
                <li class="text-sm">Missão {{ $mission->name }} falta concluir.</li>
            @endforeach
        </ul>
    </div>
@endif

@include('student.sheet')
@endsection
