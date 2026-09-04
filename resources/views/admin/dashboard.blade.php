@extends('layouts.game')

@section('title', 'Administração')

@section('content')
<div class="flex flex-wrap items-center justify-between gap-4 mb-8 reveal">
    <div>
        <p class="hero-kicker !mb-1">Comando supremo</p>
        <h1 class="font-display text-4xl text-amber-300">Painel do administrador</h1>
        <p class="text-amber-100/65">Cadastre reinos (áreas) e professores sem misturar os campos de batalha.</p>
    </div>
</div>

<div class="grid sm:grid-cols-3 gap-4 mb-8">
    <a href="{{ route('admin.areas.index') }}" class="game-card game-card-glow p-6 block reveal">
        <p class="text-xs uppercase tracking-[0.2em] text-orange-300/80 mb-2">Reinos</p>
        <h2 class="font-display text-3xl text-amber-200">{{ $areasCount }}</h2>
        <p class="text-sm text-amber-100/60 mt-2">Gerenciar áreas de atuação →</p>
    </a>
    <a href="{{ route('admin.teachers.index') }}" class="game-card game-card-glow p-6 block reveal reveal-delay-2">
        <p class="text-xs uppercase tracking-[0.2em] text-orange-300/80 mb-2">Professores</p>
        <h2 class="font-display text-3xl text-amber-200">{{ $teachersCount }}</h2>
        <p class="text-sm text-amber-100/60 mt-2">Cadastrar e vincular a reinos →</p>
    </a>
    <a href="{{ route('teacher.dashboard') }}" class="game-card game-card-glow p-6 block reveal reveal-delay-3">
        <p class="text-xs uppercase tracking-[0.2em] text-orange-300/80 mb-2">Operação</p>
        <h2 class="font-display text-3xl text-amber-200">Turmas</h2>
        <p class="text-sm text-amber-100/60 mt-2">Operar qualquer turma do sistema →</p>
    </a>
</div>

<div class="game-card p-5 reveal">
    <h2 class="font-display text-xl text-amber-200 mb-4">Mapa atual</h2>
    <div class="space-y-2">
        @forelse($areas as $area)
            <div class="flex flex-wrap items-center justify-between gap-2 py-2 border-b border-amber-500/10 last:border-0">
                <span>{{ $area->emblemIcon() }} {{ $area->name }} @unless($area->is_active)<span class="text-rose-300 text-xs">(inativo)</span>@endunless</span>
                <span class="text-sm text-amber-100/55">{{ $area->teachers_count }} prof. · {{ $area->classes_count }} turmas</span>
            </div>
        @empty
            <p class="text-amber-100/60">Nenhum reino cadastrado.</p>
        @endforelse
    </div>
</div>
@endsection
