@extends('layouts.game')

@section('title', 'Professor — Arena das Notas')

@section('content')
<div class="flex flex-wrap items-center justify-between gap-4 mb-8 reveal">
    <div>
        <p class="hero-kicker !mb-1">Comando de guerra</p>
        <h1 class="font-display text-4xl text-amber-300">{{ ($viewerIsAdmin ?? false) ? 'Todas as turmas' : 'Painel do professor' }}</h1>
        <p class="text-amber-100/65">{{ ($viewerIsAdmin ?? false) ? 'Como administrador, você opera qualquer turma do sistema.' : 'Gerencie turmas, guildas e notas no campo de batalha.' }}</p>
    </div>
    <div class="flex flex-wrap gap-2">
        <a class="game-btn-ghost" href="{{ route('teacher.seasons.index') }}">Campanhas</a>
        <a class="game-btn" href="{{ route('teacher.classes.create') }}">Nova turma</a>
    </div>
</div>

<div class="grid md:grid-cols-2 gap-4">
    @forelse($classes as $class)
        <a href="{{ route('teacher.classes.show', $class) }}" class="game-card game-card-glow p-6 block reveal reveal-delay-{{ $loop->iteration % 5 + 1 }}">
            <p class="text-xs uppercase tracking-[0.2em] text-orange-300/80 mb-2">{{ $class->area?->name ?? 'Turma' }}</p>
            <h2 class="font-display text-2xl text-amber-200">{{ $class->name }}</h2>
            @if($viewerIsAdmin ?? false)
                <p class="text-sm text-cyan-200/70 mt-1">Prof. {{ $class->teacher?->name ?? 'sem responsável' }}</p>
            @endif
            <p class="text-amber-100/65 mt-2">{{ $class->students_count }} alunos · {{ $class->teams_count }} guildas · {{ $class->activities_count }} atividades</p>
        </a>
    @empty
        <div class="game-card p-6 reveal">Nenhuma turma ainda. Crie a primeira.</div>
    @endforelse
</div>
@endsection
