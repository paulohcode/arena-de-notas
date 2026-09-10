@extends('layouts.game')

@section('title', 'Loja — Admin')

@section('content')
<div class="flex flex-wrap items-center justify-between gap-4 mb-8 reveal">
    <div>
        <a href="{{ route('admin.dashboard') }}" class="game-btn-ghost text-sm mb-2 inline-block">← Admin</a>
        <p class="hero-kicker !mb-1">Mercado da arena</p>
        <h1 class="font-display text-4xl text-amber-300">Loja de cosméticos</h1>
        <p class="text-amber-100/65 mt-1 max-w-2xl">
            Cada turma tem poucas cópias à venda. Itens equipados aumentam o poder no duelo. Veja quem já comprou, o que ainda está na loja e o que os alunos estão negociando entre si.
        </p>
    </div>
</div>

<div class="game-card p-5 mb-8 reveal space-y-4">
    <div>
        <h2 class="font-display text-xl text-amber-200">Cadastrar item</h2>
        <p class="text-sm text-amber-100/60 mt-1">Itens criados aqui entram no catálogo de todas as turmas, com o estoque inicial informado.</p>
    </div>
    @include('partials.shop-item-form', ['action' => route('admin.shop.items.store')])
</div>

@if($customItems->isNotEmpty())
    <div class="game-card p-5 mb-8 reveal">
        <h2 class="font-display text-xl text-amber-200 mb-3">Itens cadastrados</h2>
        <div class="space-y-2">
            @foreach($customItems as $item)
                <div class="flex flex-wrap items-center justify-between gap-3 py-2 border-b border-purple-900/40">
                    <div class="flex items-center gap-3 min-w-0">
                        @include('partials.shop-item-art', [
                            'icon' => $item->icon,
                            'rarity' => $item->rarity,
                            'css' => $item->css,
                            'slot' => $item->slot,
                            'size' => 'sm',
                        ])
                        <div class="min-w-0">
                            <p class="font-semibold text-amber-100 truncate">{{ $item->name }}</p>
                            <p class="text-xs text-amber-100/55">
                                {{ $slots[$item->slot] ?? $item->slot }}
                                · {{ $item->price }} {{ $currencies[$item->currency] ?? $item->currency }}
                                · {{ $rarities[$item->rarity] ?? $item->rarity }}
                                · {{ $item->isGlobal() ? 'todas as turmas' : ($item->schoolClass?->name ?? 'turma') }}
                            </p>
                        </div>
                    </div>
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
            <a class="game-btn" href="{{ route('teacher.shop.show', $class) }}">Administrar loja</a>
        </div>
    @empty
        <div class="game-card p-8 text-center text-amber-100/60">Nenhuma turma cadastrada.</div>
    @endforelse
</div>
@endsection
