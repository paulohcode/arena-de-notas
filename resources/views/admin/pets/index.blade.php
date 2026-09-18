@extends('layouts.game')

@section('title', 'Mascotes — Admin')

@section('content')
<div class="flex flex-wrap items-center justify-between gap-4 mb-8 reveal">
    <div>
        <a href="{{ route('admin.dashboard') }}" class="game-btn-ghost text-sm mb-2 inline-block">← Admin</a>
        <p class="hero-kicker !mb-1">Companheiros da arena</p>
        <h1 class="font-display text-4xl text-amber-300">Mascotes</h1>
        <p class="text-amber-100/65 mt-1 max-w-2xl">
            Escolha se o mascote entra em uma turma ou em todas (cada turma fica com estoque e preço próprios). Professores também gerenciam pela tela da turma.
        </p>
    </div>
</div>

<div class="game-card p-5 mb-8 reveal space-y-4">
    <div>
        <h2 class="font-display text-xl text-amber-200">Cadastrar mascote</h2>
        <p class="text-sm text-amber-100/60 mt-1">Uma turma específica ou todas de uma vez. Valores padrão já vêm altos (~10 dias de farm).</p>
    </div>
    @include('partials.pet-form', [
        'action' => route('admin.pets.store'),
        'scopeClasses' => $scopeClasses,
        'defaultScope' => 'all',
    ])
</div>

@if($customPets->isNotEmpty())
    <section class="mb-8 reveal">
        <h2 class="font-display text-xl text-amber-200 mb-1">Mascotes customizados recentes</h2>
        <p class="text-sm text-amber-100/55 mb-4">Um card por mascote. Cópias da mesma espécie nas turmas ficam juntas.</p>
        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach($customPets as $item)
                @php $pet = $item['pet']; @endphp
                <article class="game-card p-4 flex flex-col gap-3">
                    <div class="pet-card-preview">
                        @include('partials.pet-sprite', [
                            'spriteKey' => $pet->spriteKey(),
                            'gifUrl' => $pet->gifUrl(),
                            'size' => 'xl',
                            'name' => $pet->name,
                        ])
                    </div>
                    <div class="relative z-10 min-w-0">
                        <p class="font-display text-lg text-amber-100 truncate">{{ $pet->name }}</p>
                        <p class="text-xs text-amber-100/55">{{ $pet->rarityLabel() }} · +{{ number_format($pet->combatBonusPercent(), 1) }}%</p>
                        <p class="text-sm text-cyan-300 mt-2">{{ $pet->priceLine() }}</p>
                        <p class="text-xs text-amber-100/55 mt-1">
                            @if($item['count'] > 1)
                                {{ $item['count'] }} turmas · {{ $item['classes']->join(', ') }}
                            @else
                                {{ $item['classes']->first() ?? 'Turma' }}
                            @endif
                        </p>
                    </div>
                    <a class="game-btn-ghost !py-1 !px-3 text-sm mt-auto" href="{{ route('admin.pets.edit', $pet) }}">Editar</a>
                </article>
            @endforeach
        </div>
    </section>
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
