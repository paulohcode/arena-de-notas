@extends('layouts.game')

@section('title', 'Arenas — Admin')

@section('content')
<div class="flex flex-wrap items-center justify-between gap-4 mb-8">
    <div>
        <a href="{{ route('admin.dashboard') }}" class="game-btn-ghost text-sm mb-2 inline-block">← Admin</a>
        <p class="hero-kicker !mb-1">Campo de duelos</p>
        <h1 class="font-display text-4xl text-amber-300">Arenas</h1>
        <p class="text-amber-100/65">Abra a arena de um reino para configurar a turma e os duelos entre turmas.</p>
    </div>
</div>

<div class="space-y-3">
    @forelse($areas as $area)
        <div class="game-card p-5 flex flex-wrap items-center justify-between gap-4">
            <div>
                <h2 class="font-display text-xl text-amber-200">{{ $area->emblemIcon() }} {{ $area->name }}</h2>
                <p class="text-sm text-amber-100/55 mt-1">
                    {{ $area->classes_count }} {{ $area->classes_count === 1 ? 'turma' : 'turmas' }}
                    · Hoje ({{ \App\Support\ArenaSchedule::todayLabel() }}):
                    <span class="{{ $area->isRealmArenaOpen() ? 'text-emerald-300' : 'text-rose-300' }}">
                        entre turmas {{ $area->isRealmArenaOpen() ? 'aberta' : 'fechada' }}
                    </span>
                    @unless($area->is_active) · <span class="text-rose-300">inativo</span> @endunless
                </p>
            </div>
            <a href="{{ route('admin.areas.arena', $area) }}" class="game-btn text-sm">Abrir arena</a>
        </div>
    @empty
        <div class="game-card p-8 text-center">
            <p class="text-amber-100/60">Nenhum reino cadastrado.</p>
            <a href="{{ route('admin.areas.create') }}" class="game-btn mt-4 inline-block">Criar reino</a>
        </div>
    @endforelse
</div>
@endsection
