@extends('layouts.game')

@section('title', 'Ficha de '.$student->name.' — '.$class->name)

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
            <p class="hero-kicker !mb-1">Ficha do aluno</p>
            <h1 class="font-display text-4xl text-amber-300">{{ $student->name }}</h1>
            <form method="POST" action="{{ route('teacher.students.update', [$class, $student]) }}" class="mt-3 flex flex-wrap items-end gap-2">
                @csrf
                @method('PUT')
                <label class="space-y-1 min-w-56">
                    <span class="text-xs uppercase tracking-wide text-purple-200/70">Nome do aluno</span>
                    <input class="game-input" name="name" value="{{ old('name', $student->name) }}" required maxlength="120">
                </label>
                <label class="space-y-1 min-w-56">
                    <span class="text-xs uppercase tracking-wide text-purple-200/70">E-mail de acesso</span>
                    <input class="game-input" type="email" name="email" value="{{ old('email', $student->email) }}" required maxlength="180">
                </label>
                <button class="game-btn !py-1 !px-3 text-sm" type="submit">Salvar cadastro</button>
            </form>
            <div class="mt-2">
                @include('partials.reset-student-password', ['buttonClass' => 'game-btn-ghost !py-1 !px-3 text-sm'])
            </div>
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
            <p class="text-amber-100/45 text-xs mt-2">Último acesso: {{ $student->lastAccessedLabel() ?? 'Nunca acessou' }}</p>
            @if($student->isPersonaPending())
                <div class="mt-3 flex flex-wrap gap-2">
                    <form method="POST" action="{{ route('teacher.characters.approve', [$class, $student]) }}">
                        @csrf
                        <button class="game-btn !py-1 !px-3 text-sm" type="submit">Aprovar personagem</button>
                    </form>
                    <form method="POST" action="{{ route('teacher.characters.reject', [$class, $student]) }}" class="flex gap-2">
                        @csrf
                        <input class="game-input !py-1" name="reason" placeholder="Motivo (opcional)" maxlength="200">
                        <button class="game-btn-ghost !py-1 !px-3 text-sm" type="submit">Recusar</button>
                    </form>
                </div>
            @endif
        </div>
    </div>
    <div class="flex flex-wrap gap-2">
        <a class="game-btn-ghost" href="{{ route('ranking.show', $class) }}">Voltar ao ranking</a>
        <a class="game-btn-ghost" href="{{ route('teacher.classes.show', ['schoolClass' => $class, 'tab' => 'alunos']) }}">Voltar à turma</a>
    </div>
</div>

<form method="POST" action="{{ route('teacher.characters.update', [$class, $student]) }}" class="game-card p-6 mb-8 space-y-6 reveal">
    @csrf
    @method('PUT')
    <div>
        <h2 class="font-display text-xl text-amber-200">Identidade do aluno</h2>
        <p class="text-sm text-amber-100/60 mt-1">Altere o nome de jogo, o avatar e a classe. A mudança entra na arena na hora, sem pedido de aprovação.</p>
    </div>

    <label class="space-y-1 block max-w-md">
        <span class="text-xs uppercase tracking-wide text-purple-200/70">Nome de jogo</span>
        <input class="game-input" name="character_name" value="{{ old('character_name', $student->pending_character_name ?? $student->character_name) }}" required minlength="2" maxlength="24" placeholder="Ex: Lobo Noturno">
    </label>

    <section class="space-y-3">
        <h3 class="font-display text-lg text-amber-200">Avatar</h3>
        <div class="grid grid-cols-3 sm:grid-cols-4 lg:grid-cols-6 gap-3">
            @foreach(\App\Models\User::CHARACTER_AVATARS as $key => $meta)
                <label class="game-card game-card-glow choice-card p-3 cursor-pointer text-center">
                    <input type="radio" name="character_avatar" value="{{ $key }}" class="sr-only"
                           @checked(old('character_avatar', $student->pending_character_avatar ?? $student->character_avatar) === $key) required>
                    <span class="hero-portrait mx-auto hero-portrait--sm" style="--portrait-tone: {{ $meta['tone'] }}">{{ $meta['icon'] }}</span>
                    <span class="block text-xs text-amber-100/70 mt-2">{{ $meta['name'] }}</span>
                </label>
            @endforeach
        </div>
    </section>

    <section class="space-y-3">
        <h3 class="font-display text-lg text-amber-200">Classe</h3>
        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-3">
            @foreach(\App\Models\User::CHARACTER_CLASSES as $key => $meta)
                <label class="game-card game-card-glow choice-card class-aura class-aura--{{ $key }} p-4 cursor-pointer block">
                    @include('partials.class-fx', ['characterClass' => $key])
                    <input type="radio" name="character_class" value="{{ $key }}" class="sr-only"
                           @checked(old('character_class', $student->character_class) === $key) required>
                    <div class="flex items-start gap-3">
                        <span class="rank-badge !min-w-10 !h-10 text-lg">{{ $meta['icon'] }}</span>
                        <div>
                            <p class="text-[10px] uppercase tracking-wide text-cyan-200/80">{{ \App\Models\User::CHARACTER_ROLES[$meta['role']] }}</p>
                            <p class="font-display text-lg text-amber-200">{{ $meta['name'] }}</p>
                            <p class="text-xs text-amber-100/60 mt-1">{{ $meta['blurb'] }}</p>
                            <p class="text-[11px] text-cyan-200/70 mt-1">{{ $meta['combat_blurb'] }}</p>
                        </div>
                    </div>
                </label>
            @endforeach
        </div>
    </section>

    <button class="game-btn" type="submit">Salvar identidade</button>
</form>

@include('student.sheet')
@endsection
