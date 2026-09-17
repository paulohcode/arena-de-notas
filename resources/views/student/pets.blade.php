@extends('layouts.game')

@section('title', 'Mascotes — '.$class->name)

@section('content')
<div class="flex flex-wrap items-start justify-between gap-4 mb-6 reveal">
    <div>
        <p class="hero-kicker !mb-1">Companheiros de arena</p>
        <h1 class="font-display text-4xl text-amber-300">Mascotes</h1>
        <p class="text-amber-100/65 mt-1 max-w-2xl">
            Compre com as três moedas, dê um nome e escolha a aura. Você pode ter vários, mas só um equipado. O comércio é só dentro da turma, em {{ \App\Models\GameCurrency::label('relics') }}.
        </p>
    </div>
    <div class="flex flex-wrap gap-2">
        <a class="game-btn-ghost" href="{{ route('student.shop.index') }}">Loja</a>
        <a class="game-btn-ghost" href="{{ route('student.dashboard') }}">Minha ficha</a>
    </div>
</div>

<div class="grid sm:grid-cols-3 gap-3 mb-6 reveal">
    <div class="game-card p-4">
        <p class="text-[10px] uppercase tracking-wide text-purple-200/60">{{ \App\Models\GameCurrency::label('relics') }}</p>
        <p class="text-cyan-300 mt-1">{{ \App\Models\GameCurrency::format('relics', $relics) }}</p>
    </div>
    <div class="game-card p-4">
        <p class="text-[10px] uppercase tracking-wide text-purple-200/60">{{ \App\Models\GameCurrency::label('seals') }}</p>
        <p class="text-emerald-300 mt-1">{{ \App\Models\GameCurrency::format('seals', $seals) }}</p>
    </div>
    <div class="game-card p-4">
        <p class="text-[10px] uppercase tracking-wide text-purple-200/60">{{ \App\Models\GameCurrency::label('auras') }}</p>
        <p class="text-violet-300 mt-1">{{ \App\Models\GameCurrency::format('auras', $auras) }}</p>
    </div>
</div>

@if($equipped)
    <div class="game-card p-5 mb-6 reveal border-emerald-400/30 flex flex-wrap items-center gap-4">
        @include('partials.pet-companion', ['pet' => $equipped, 'size' => 'lg'])
        <form method="POST" action="{{ route('student.pets.unequip') }}">
            @csrf
            <button class="game-btn-ghost !py-1 !px-3 text-sm" type="submit">Desequipar</button>
        </form>
    </div>
@endif

<div
    class="mb-8 reveal"
    x-data="{
        buying: null,
        customName: '',
        auraColor: 'ember',
        open(pet) {
            this.buying = pet;
            this.customName = '';
            this.auraColor = 'ember';
        },
        close() { this.buying = null; }
    }"
>
    <h2 class="font-display text-xl text-amber-200 mb-3">Loja da turma</h2>
    <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
        @foreach($catalog as $pet)
            <article class="game-card p-4 flex flex-col gap-3 {{ $pet['owned'] ? 'opacity-80' : '' }}">
                <div class="pet-card-preview">
                    @include('partials.pet-sprite', [
                        'spriteKey' => $pet['sprite_key'],
                        'gifUrl' => $pet['gif_url'],
                        'size' => 'xl',
                        'name' => $pet['name'],
                    ])
                </div>
                <div class="min-w-0">
                    <p class="font-display text-lg text-amber-100 truncate">{{ $pet['name'] }}</p>
                    <p class="text-xs text-amber-100/55">{{ $pet['rarity_label'] }} · +{{ number_format($pet['combat_bonus_percent'], 1) }}%</p>
                    @if($pet['description'])
                        <p class="text-xs text-amber-100/50 mt-1">{{ $pet['description'] }}</p>
                    @endif
                </div>
                <p class="text-sm text-cyan-300">
                    @if($pet['price_relics'] > 0){{ \App\Models\GameCurrency::format('relics', $pet['price_relics']) }}@endif
                    @if($pet['price_seals'] > 0) · {{ \App\Models\GameCurrency::format('seals', $pet['price_seals']) }}@endif
                    @if($pet['price_auras'] > 0) · {{ \App\Models\GameCurrency::format('auras', $pet['price_auras']) }}@endif
                </p>
                <p class="text-xs {{ $pet['stock'] > 0 ? 'text-emerald-300/70' : 'text-rose-300/70' }}">
                    {{ $pet['stock'] > 0 ? $pet['stock'].' à venda' : 'Esgotado' }}
                </p>
                @if($pet['owned'])
                    <p class="text-sm text-emerald-300 mt-auto">Você já possui</p>
                @elseif($pet['stock'] < 1)
                    <p class="text-sm text-rose-300 mt-auto">Esgotado — negocie no mercado</p>
                @else
                    <button
                        type="button"
                        class="game-btn !py-1 !px-3 text-sm mt-auto"
                        @click="open({{ \Illuminate\Support\Js::from($pet) }})"
                    >Comprar</button>
                @endif
            </article>
        @endforeach
    </div>

    <div
        x-show="buying"
        x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70"
        @keydown.escape.window="close()"
    >
        <div class="game-card p-6 w-full max-w-md" @click.outside="close()">
            <h3 class="font-display text-2xl text-amber-200" x-text="buying?.name"></h3>
            <p class="text-sm text-amber-100/60 mt-1">Escolha o nome e a cor da aura do seu mascote.</p>
            <form method="POST" action="{{ route('student.pets.purchase') }}" class="mt-4 space-y-3">
                @csrf
                <input type="hidden" name="pet_id" :value="buying?.id">
                <label class="block">
                    <span class="text-sm">Nome do mascote</span>
                    <input class="game-input mt-1 w-full" name="custom_name" x-model="customName" required minlength="2" maxlength="20" placeholder="Ex: Fúria">
                </label>
                <label class="block">
                    <span class="text-sm">Cor da aura</span>
                    <select class="game-select mt-1 w-full" name="aura_color" x-model="auraColor" required>
                        @foreach($auraColors as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <p class="text-sm text-cyan-300">
                    <span x-text="buying?.price_relics > 0 ? ({{ \Illuminate\Support\Js::from(\App\Models\GameCurrency::label('relics')) }} + ': ' + buying.price_relics) : ''"></span>
                    <span x-show="buying?.price_seals > 0" x-text="' · ' + {{ \Illuminate\Support\Js::from(\App\Models\GameCurrency::label('seals')) }} + ': ' + buying?.price_seals"></span>
                    <span x-show="buying?.price_auras > 0" x-text="' · ' + {{ \Illuminate\Support\Js::from(\App\Models\GameCurrency::label('auras')) }} + ': ' + buying?.price_auras"></span>
                </p>
                <div class="flex flex-wrap gap-2 pt-2">
                    <button class="game-btn" type="submit">Confirmar compra</button>
                    <button class="game-btn-ghost" type="button" @click="close()">Cancelar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<section class="mb-8 reveal">
    <h2 class="font-display text-xl text-amber-200 mb-3">Sua coleção</h2>
    @if(count($owned) === 0)
        <div class="game-card p-6 text-amber-100/60">Nenhum mascote ainda. Os preços são altos — prepare as três moedas.</div>
    @else
        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach($owned as $item)
                <article class="game-card p-4 flex flex-col gap-3 {{ $item['equipped'] ? 'border-emerald-400/40' : '' }}">
                    @include('partials.pet-companion', ['pet' => $item, 'size' => 'md'])
                    <p class="text-xs text-amber-100/55">{{ $item['species_name'] }} · {{ $item['rarity_label'] }} · aura {{ $item['aura_label'] }}</p>
                    <div class="mt-auto flex flex-wrap gap-2">
                        @if($item['equipped'])
                            <span class="text-xs text-emerald-300">Equipado</span>
                        @elseif($item['listed_price'])
                            <form method="POST" action="{{ route('student.pets.unlist') }}">
                                @csrf
                                <input type="hidden" name="enrollment_pet_id" value="{{ $item['id'] }}">
                                <button class="game-btn-ghost !py-1 !px-3 text-xs" type="submit">Tirar do mercado ({{ $item['listed_price'] }})</button>
                            </form>
                        @else
                            <form method="POST" action="{{ route('student.pets.equip') }}">
                                @csrf
                                <input type="hidden" name="enrollment_pet_id" value="{{ $item['id'] }}">
                                <button class="game-btn !py-1 !px-3 text-xs" type="submit">Equipar</button>
                            </form>
                            <form method="POST" action="{{ route('student.pets.list') }}" class="flex items-end gap-1">
                                @csrf
                                <input type="hidden" name="enrollment_pet_id" value="{{ $item['id'] }}">
                                <input class="game-input w-20 !py-1 text-xs" type="number" name="price" min="1" max="9999" placeholder="Preço" required>
                                <button class="game-btn-ghost !py-1 !px-2 text-xs" type="submit">Anunciar</button>
                            </form>
                        @endif
                    </div>
                </article>
            @endforeach
        </div>
    @endif
</section>

<section class="mb-8 reveal">
    <h2 class="font-display text-xl text-amber-200 mb-3">Mercado da turma</h2>
    @if(count($listings) === 0)
        <div class="game-card p-6 text-amber-100/60">Nenhum mascote anunciado no momento.</div>
    @else
        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach($listings as $listing)
                <article class="game-card p-4 flex flex-col gap-3">
                    <div class="pet-card-preview">
                        @include('partials.pet-sprite', [
                            'spriteKey' => $listing['sprite_key'],
                            'gifUrl' => $listing['gif_url'],
                            'auraColor' => $listing['aura_color'],
                            'name' => $listing['custom_name'],
                            'size' => 'xl',
                        ])
                    </div>
                    <div>
                        <p class="font-display text-amber-100">{{ $listing['custom_name'] }}</p>
                        <p class="text-xs text-amber-100/55">{{ $listing['species_name'] }} · +{{ number_format($listing['combat_bonus_percent'], 1) }}%</p>
                        <p class="text-sm text-cyan-300 mt-1">{{ \App\Models\GameCurrency::format('relics', $listing['price']) }}</p>
                        <p class="text-xs text-amber-100/50 mt-1">Vendedor: {{ $listing['seller_name'] }}</p>
                    </div>
                    @if($listing['already_owned'])
                        <p class="text-sm text-amber-200/70 mt-auto">Você já tem esta espécie</p>
                    @else
                        <form method="POST" action="{{ route('student.pets.listings.buy', $listing['id']) }}" class="mt-auto">
                            @csrf
                            <button class="game-btn !py-1 !px-3 text-sm" type="submit">Comprar</button>
                        </form>
                    @endif
                </article>
            @endforeach
        </div>
    @endif
</section>
@endsection
