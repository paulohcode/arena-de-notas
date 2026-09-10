@extends('layouts.game')

@section('title', 'Loja — '.$class->name)

@section('content')
<div class="flex flex-wrap items-start justify-between gap-4 mb-8 reveal">
    <div>
        <a href="{{ auth()->user()->isAdmin() ? route('admin.shop.index') : route('teacher.classes.show', $class) }}" class="game-btn-ghost text-sm mb-2 inline-block">
            ← {{ auth()->user()->isAdmin() ? 'Lojas' : 'Turma' }}
        </a>
        <p class="hero-kicker !mb-1">Estoque e donos</p>
        <h1 class="font-display text-4xl text-amber-300">Loja · {{ $class->name }}</h1>
        <p class="text-amber-100/60 mt-1 max-w-2xl">
            Defina quantas cópias ainda estão à venda. Itens equipados aumentam o poder no duelo. O restante fica com quem comprou — e pode ser negociado entre os alunos.
        </p>
    </div>
    <a class="game-btn-ghost" href="{{ route('teacher.classes.show', $class) }}">Voltar à turma</a>
</div>

@foreach($slots as $slot => $slotLabel)
    <section class="mb-8 reveal">
        <h2 class="font-display text-xl text-amber-200 mb-3">{{ $slotLabel }}</h2>
        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach($catalog[$slot] as $item)
                <article class="game-card shop-item-card">
                    @include('partials.shop-item-art', [
                        'icon' => $item['icon'],
                        'rarity' => $item['rarity'],
                        'css' => $item['css'] ?? null,
                        'slot' => $item['slot'],
                    ])
                    <div class="shop-item-card__body">
                        <div>
                            <p class="font-display text-lg text-amber-100">{{ $item['name'] }}</p>
                            <div class="shop-item-card__meta mt-2">
                                <span class="shop-item-card__badge">{{ $item['rarity_label'] }}</span>
                                @if(! ($item['tradable'] ?? true))
                                    <span class="shop-item-card__badge shop-item-card__badge--seals">só presença</span>
                                @endif
                            </div>
                            <p class="text-sm mt-3 {{ ($item['currency'] ?? 'relics') === 'seals' ? 'text-emerald-300' : 'text-cyan-300' }}">
                                {{ $item['price'] }} {{ $item['currency_label'] ?? 'Relíquias' }}
                            </p>
                            <p class="text-sm mt-1 {{ $item['stock'] > 0 ? 'text-cyan-300/80' : 'text-rose-300/80' }}">
                                {{ $item['stock'] > 0 ? $item['stock'].' à venda na loja' : 'Esgotado na loja' }}
                            </p>
                            <p class="text-xs text-amber-200/70 mt-1">
                                Equipado: +{{ number_format(\App\Support\CosmeticCatalog::combatBonusForKey($item['key']) * 100, 1) }}% no duelo
                            </p>
                            <p class="text-sm text-amber-100/70 mt-3">
                                @if(count($item['owners']) === 0)
                                    Ninguém comprou ainda.
                                @else
                                    Donos:
                                    @foreach($item['owners'] as $owner)
                                        <span class="text-amber-100">
                                            {{ $owner['name'] }}
                                            @if($owner['character'])
                                                <span class="text-amber-300">({{ $owner['character'] }})</span>
                                            @endif
                                            @if($owner['equipped'])
                                                · equipado
                                            @endif
                                            @if(! $loop->last)
                                                ,
                                            @endif
                                        </span>
                                    @endforeach
                                @endif
                            </p>
                            @if(count($item['listings']) > 0)
                                <p class="text-sm text-violet-200/80 mt-1">
                                    No mercado:
                                    @foreach($item['listings'] as $listing)
                                        {{ $listing['seller'] }} por {{ $listing['price'] }} Relíquias
                                        @if(! $loop->last)
                                            ;
                                        @endif
                                    @endforeach
                                </p>
                            @endif
                        </div>
                        <form method="POST" action="{{ route('teacher.shop.restock', $class) }}" class="mt-auto flex flex-wrap items-end gap-2">
                            @csrf
                            <input type="hidden" name="item" value="{{ $item['key'] }}">
                            <label class="space-y-1">
                                <span class="text-xs uppercase tracking-wide text-purple-200/70">À venda</span>
                                <input class="game-input w-24" type="number" name="quantity" min="0" max="99" value="{{ old('item') === $item['key'] ? old('quantity', $item['stock']) : $item['stock'] }}" required>
                            </label>
                            <button class="game-btn !py-1 !px-3 text-sm" type="submit">Atualizar</button>
                        </form>
                    </div>
                </article>
            @endforeach
        </div>
    </section>
@endforeach
@endsection
