@extends('layouts.game')

@section('title', 'Mascotes — '.$class->name)

@section('content')
<div class="flex flex-wrap items-start justify-between gap-4 mb-8 reveal">
    <div>
        <a href="{{ auth()->user()->isAdmin() ? route('admin.pets.index') : route('teacher.classes.show', $class) }}" class="game-btn-ghost text-sm mb-2 inline-block">
            ← {{ auth()->user()->isAdmin() ? 'Mascotes' : 'Turma' }}
        </a>
        <p class="hero-kicker !mb-1">Companheiros de arena</p>
        <h1 class="font-display text-4xl text-amber-300">Mascotes · {{ $class->name }}</h1>
        <p class="text-amber-100/60 mt-1 max-w-2xl">
            Cada aluno pode comprar vários mascotes e equipar só um. Preços altos nas três moedas — o comum leva cerca de 10 dias de farm. {{ $listingsCount }} anúncio(s) no mercado.
        </p>
    </div>
    <a class="game-btn-ghost" href="{{ route('teacher.classes.show', $class) }}">Voltar à turma</a>
</div>

<div class="game-card p-5 mb-8 reveal space-y-4">
    <div>
        <h2 class="font-display text-xl text-amber-200">Cadastrar mascote</h2>
        <p class="text-sm text-amber-100/60 mt-1">Escolha se o mascote entra só em uma turma ou em todas as que você gerencia. Sem GIF, o sprite animado padrão é usado.</p>
    </div>
    @include('partials.pet-form', [
        'action' => route('teacher.pets.store', $class),
        'scopeClasses' => $scopeClasses,
        'defaultScope' => 'one',
        'selectedClassId' => $class->id,
    ])
</div>

<section class="mb-8 reveal">
    <h2 class="font-display text-xl text-amber-200 mb-3">Catálogo da turma</h2>
    <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
        @foreach($catalog as $pet)
            @php $petOwners = $owners[$pet->id] ?? []; @endphp
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
                    <p class="text-sm mt-1 {{ $pet->stock > 0 ? 'text-emerald-300/80' : 'text-rose-300/80' }}">
                        {{ $pet->stock > 0 ? $pet->stock.' à venda' : 'Esgotado' }}
                        @unless($pet->active)
                            · inativo
                        @endunless
                    </p>
                </div>
                <p class="text-sm text-amber-100/70">
                    @if(count($petOwners) === 0)
                        Ninguém comprou ainda.
                    @else
                        Donos:
                        @foreach($petOwners as $owner)
                            {{ $owner['student_name'] }} ({{ $owner['custom_name'] }})@if($owner['equipped']) · equipado@endif@if(! $loop->last), @endif
                        @endforeach
                    @endif
                </p>
                <form method="POST" action="{{ route('teacher.pets.restock', $class) }}" class="mt-auto flex flex-wrap items-end gap-2">
                    @csrf
                    <input type="hidden" name="pet_id" value="{{ $pet->id }}">
                    <label class="space-y-1">
                        <span class="text-xs uppercase tracking-wide text-purple-200/70">À venda</span>
                        <input class="game-input w-24" type="number" name="quantity" min="0" max="99" value="{{ old('pet_id') == $pet->id ? old('quantity', $pet->stock) : $pet->stock }}" required>
                    </label>
                    <button class="game-btn !py-1 !px-3 text-sm" type="submit">Atualizar</button>
                    <a class="game-btn-ghost !py-1 !px-3 text-sm" href="{{ route('teacher.pets.edit', [$class, $pet]) }}">Editar</a>
                </form>
            </article>
        @endforeach
    </div>
</section>
@endsection
