@extends('layouts.game')

@section('title', 'Visão de aluno — Admin')

@section('content')
<div class="flex flex-wrap items-center justify-between gap-4 mb-8 reveal">
    <div>
        <a href="{{ route('admin.dashboard') }}" class="game-btn-ghost text-sm mb-2 inline-block">← Admin</a>
        <p class="hero-kicker !mb-1">Troca de visão</p>
        <h1 class="font-display text-4xl text-amber-300">Visão de aluno</h1>
        <p class="text-amber-100/65 mt-1 max-w-2xl">
            Escolha um aluno para navegar a arena como ele. Alterações ficam bloqueadas; use Visão de admin para voltar.
        </p>
    </div>
</div>

<form method="GET" action="{{ route('admin.impersonate.index') }}" class="game-card p-5 mb-8 reveal flex flex-wrap items-end gap-4">
    <label class="block min-w-[12rem]">
        <span class="text-sm text-amber-100/70">Reino</span>
        <select class="game-input mt-1 block w-full" name="area">
            <option value="">Todos os reinos</option>
            @foreach($areas as $areaOption)
                <option value="{{ $areaOption->id }}" @selected((int) $selectedAreaId === (int) $areaOption->id)>
                    {{ $areaOption->name }}
                </option>
            @endforeach
        </select>
    </label>
    <label class="block min-w-[12rem]">
        <span class="text-sm text-amber-100/70">Turma</span>
        <select class="game-input mt-1 block w-full" name="class">
            <option value="">Todas as turmas</option>
            @foreach($classOptions as $classOption)
                <option value="{{ $classOption->id }}" @selected((int) $selectedClassId === (int) $classOption->id)>
                    {{ $classOption->area?->name ? $classOption->area->name.' · ' : '' }}{{ $classOption->name }}
                </option>
            @endforeach
        </select>
    </label>
    <label class="block min-w-[14rem] flex-1">
        <span class="text-sm text-amber-100/70">Buscar aluno</span>
        <input class="game-input mt-1 block w-full" type="search" name="q" value="{{ $search }}" placeholder="Nome, e-mail ou personagem">
    </label>
    <button class="game-btn" type="submit">Filtrar</button>
</form>

<div class="space-y-8">
    @forelse($classes as $class)
        <section class="reveal space-y-3">
            <div>
                <h2 class="font-display text-2xl text-amber-200">{{ $class->name }}</h2>
                <p class="text-sm text-amber-100/55">
                    {{ $class->area?->emblemIcon() }} {{ $class->area?->name ?? 'Sem reino' }}
                    · {{ $class->students->count() }} aluno(s)
                </p>
            </div>
            <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-3">
                @foreach($class->students as $student)
                    <article class="game-card p-4 flex flex-col gap-3">
                        <div class="min-w-0">
                            <p class="font-display text-lg text-amber-100 truncate">{{ $student->name }}</p>
                            @if($student->arenaName())
                                <p class="text-sm text-amber-300/80 truncate">{{ $student->arenaName() }}</p>
                            @endif
                            <p class="text-xs text-amber-100/50 truncate mt-1">{{ $student->email }}</p>
                        </div>
                        <form method="POST" action="{{ route('admin.impersonate.start', [$class, $student]) }}" class="mt-auto">
                            @csrf
                            <input type="hidden" name="return_url" value="{{ $returnUrl }}">
                            <button class="game-btn !py-1 !px-3 text-sm w-full" type="submit">Entrar na visão</button>
                        </form>
                    </article>
                @endforeach
            </div>
        </section>
    @empty
        <div class="game-card p-8 text-center text-amber-100/60 reveal">
            Nenhum aluno encontrado com esses filtros.
        </div>
    @endforelse
</div>
@endsection
