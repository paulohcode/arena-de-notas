@extends('layouts.game')

@section('title', 'Mascotes — Admin')

@section('content')
<div class="flex flex-wrap items-center justify-between gap-4 mb-8 reveal">
    <div>
        <a href="{{ route('admin.dashboard') }}" class="game-btn-ghost text-sm mb-2 inline-block">← Admin</a>
        <p class="hero-kicker !mb-1">Companheiros da arena</p>
        <h1 class="font-display text-4xl text-amber-300">Mascotes</h1>
        <p class="text-amber-100/65 mt-1 max-w-2xl">
            Cadastre um mascote para todas as turmas (cada uma com estoque e preço próprios). Professores também gerenciam pela tela da turma.
        </p>
    </div>
</div>

<div class="game-card p-5 mb-8 reveal space-y-4">
    <div>
        <h2 class="font-display text-xl text-amber-200">Cadastrar mascote global</h2>
        <p class="text-sm text-amber-100/60 mt-1">Cria uma cópia em cada turma. Valores padrão já vêm altos (~10 dias de farm).</p>
    </div>
    @include('partials.pet-form', ['action' => route('admin.pets.store')])
</div>

@if($customPets->isNotEmpty())
    <div class="game-card p-5 mb-8 reveal">
        <h2 class="font-display text-xl text-amber-200 mb-3">Mascotes customizados recentes</h2>
        <div class="space-y-2">
            @foreach($customPets as $pet)
                <div class="flex flex-wrap items-center justify-between gap-3 py-2 border-b border-purple-900/40">
                    <div class="flex items-center gap-3 min-w-0">
                        @include('partials.pet-sprite', [
                            'spriteKey' => $pet->spriteKey(),
                            'gifUrl' => $pet->gifUrl(),
                            'size' => 'sm',
                            'name' => $pet->name,
                        ])
                        <div class="min-w-0">
                            <p class="font-semibold text-amber-100 truncate">{{ $pet->name }}</p>
                            <p class="text-xs text-amber-100/55">
                                {{ $pet->schoolClass?->name ?? 'Turma' }}
                                · {{ $pet->priceLine() }}
                                · +{{ number_format($pet->combatBonusPercent(), 1) }}%
                            </p>
                        </div>
                    </div>
                    <a class="game-btn-ghost !py-1 !px-3 text-sm" href="{{ route('admin.pets.edit', $pet) }}">Editar</a>
                </div>
            @endforeach
        </div>
    </div>
@endif

<div class="space-y-3">
    @forelse($classes as $class)
        @php
            $stats = $overview[$class->id] ?? ['stock_remaining' => 0, 'owned_copies' => 0, 'listings_count' => 0];
        @endphp
        <div class="game-card p-5 flex flex-wrap items-center justify-between gap-4 reveal">
            <div class="min-w-0">
                <p class="text-xs uppercase tracking-[0.2em] text-orange-300/80">{{ $class->area?->name ?? 'Turma' }}</p>
                <h2 class="font-display text-xl text-amber-200">{{ $class->name }}</h2>
                <p class="text-sm text-amber-100/55 mt-1">
                    Prof. {{ $class->teacher?->name ?? 'sem responsável' }}
                    · {{ $class->students_count }} alunos
                </p>
                <p class="text-sm text-cyan-200/80 mt-2">
                    {{ $stats['stock_remaining'] }} à venda
                    · {{ $stats['owned_copies'] }} com dono
                    · {{ $stats['listings_count'] }} no mercado
                </p>
            </div>
            <a class="game-btn" href="{{ route('teacher.pets.show', $class) }}">Administrar mascotes</a>
        </div>
    @empty
        <div class="game-card p-8 text-center text-amber-100/60">Nenhuma turma cadastrada.</div>
    @endforelse
</div>
@endsection
