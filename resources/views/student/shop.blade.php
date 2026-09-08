@extends('layouts.game')

@section('title', 'Loja — '.$class->name)

@section('content')
<div class="flex flex-wrap items-start justify-between gap-4 mb-6 reveal">
    <div>
        <p class="hero-kicker !mb-1">Mercado da arena</p>
        <h1 class="font-display text-4xl text-amber-300">Loja de cosméticos</h1>
        <p class="text-amber-100/60 mt-1">
            {{ $class->name }} ·
            <span class="text-cyan-300">{{ $enrollment->relics }} Relíquias</span>
            ·
            <span class="text-emerald-300">{{ $enrollment->seals }} Selos</span>
            · Glória {{ $enrollment->glory }}
        </p>
        <p class="text-sm text-amber-100/45 mt-2 max-w-xl">
            Relíquias vêm da arena. Selos vêm da presença e compram itens exclusivos.
            Há poucas cópias na loja. Se esgotar, anuncie o seu ou compre de um colega da turma.
        </p>
    </div>
    <div class="flex flex-wrap gap-2">
        <a class="game-btn-ghost" href="{{ route('student.arena.index') }}">Arena</a>
        <a class="game-btn-ghost" href="{{ route('student.dashboard') }}">Minha ficha</a>
    </div>
</div>

<div class="game-card game-card-glow p-5 mb-8 reveal flex flex-wrap items-center gap-5">
    @include('partials.player-avatar', [
        'student' => $student,
        'enrollment' => $enrollment,
        'size' => 'lg',
        'avatarKey' => $student->pending_character_avatar ?? $student->character_avatar,
    ])
    <div class="min-w-0">
        <p class="text-sm text-purple-200/70">Prévia equipada</p>
        <p class="font-display text-2xl text-amber-200">
            {{ $student->arenaName() ?: $student->name }}
        </p>
        @if($enrollment->equippedTitleLabel())
            <p class="cosmetic-title mt-1">{{ $enrollment->equippedTitleLabel() }}</p>
        @endif
        <p class="text-xs text-amber-100/50 mt-2">Itens comprados ficam nesta turma. Equipe um por slot.</p>
    </div>
</div>

<section class="mb-8 reveal">
    <h2 class="font-display text-xl text-amber-200 mb-3">Mercado da turma</h2>
    @if(count($listings) === 0)
        <div class="game-card p-4 text-sm text-amber-100/55">
            Ninguém está negociando agora. Anuncie um item que você já possui.
        </div>
    @else
        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach($listings as $listing)
                <div class="game-card p-4 flex flex-col gap-3">
                    <div>
                        <p class="font-semibold text-amber-100">{{ $listing['name'] }}</p>
                        <p class="text-xs text-purple-200/60">{{ $listing['rarity_label'] }} · {{ $listing['seller_name'] }}</p>
                        <p class="text-sm text-cyan-300 mt-1">{{ $listing['price'] }} Relíquias</p>
                    </div>
                    <form method="POST" action="{{ route('student.shop.listings.buy', $listing['id']) }}" class="mt-auto">
                        @csrf
                        <button
                            type="submit"
                            class="game-btn !py-1 !px-3 text-sm"
                            @disabled($listing['already_owned'] || $enrollment->relics < $listing['price'])
                        >
                            {{ $listing['already_owned'] ? 'Você já tem este item' : 'Comprar de colega' }}
                        </button>
                    </form>
                </div>
            @endforeach
        </div>
    @endif
</section>

@foreach($slots as $slot => $slotLabel)
    <section class="mb-8 reveal">
        <div class="flex flex-wrap items-center justify-between gap-3 mb-3">
            <h2 class="font-display text-xl text-amber-200">{{ $slotLabel }}</h2>
            @php
                $equippedKey = $loadout[$slot] ?? null;
            @endphp
            @if($equippedKey)
                <form method="POST" action="{{ route('student.shop.unequip') }}">
                    @csrf
                    <input type="hidden" name="slot" value="{{ $slot }}">
                    <button type="submit" class="game-btn-ghost !py-1 !px-3 text-xs">Desequipar</button>
                </form>
            @endif
        </div>
        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach($catalog[$slot] as $item)
                <div class="game-card p-4 flex flex-col gap-3 {{ $item['equipped'] ? 'border-amber-400/50' : '' }}">
                    <div class="flex items-start gap-3">
                        <div class="shop-item-preview" aria-hidden="true">
                            @if($slot === 'frame')
                                <span class="hero-portrait hero-portrait--sm cosmetic-frame cosmetic-frame--{{ $item['css'] }}">
                                    <span>{{ $student->avatarIcon() }}</span>
                                </span>
                            @elseif($slot === 'accessory')
                                <span class="hero-portrait hero-portrait--sm">
                                    <span>{{ $student->avatarIcon() }}</span>
                                    <span class="cosmetic-accessory">{{ $item['icon'] }}</span>
                                </span>
                            @elseif($slot === 'title')
                                <span class="cosmetic-title">{{ $item['label'] }}</span>
                            @else
                                <span class="hero-portrait hero-portrait--sm cosmetic-aura cosmetic-aura--{{ $item['css'] }}">
                                    <span>{{ $student->avatarIcon() }}</span>
                                </span>
                            @endif
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="font-semibold text-amber-100 truncate">{{ $item['name'] }}</p>
                            <p class="text-xs text-purple-200/60">
                                {{ $item['rarity_label'] }}
                                @if(($item['currency'] ?? 'relics') === 'seals')
                                    · <span class="text-emerald-300/80">só presença</span>
                                @endif
                            </p>
                            <p class="text-sm mt-1 {{ ($item['currency'] ?? 'relics') === 'seals' ? 'text-emerald-300' : 'text-cyan-300' }}">
                                {{ $item['price'] }} {{ $item['currency_label'] ?? 'Relíquias' }}
                            </p>
                            <p class="text-xs mt-1 {{ $item['stock'] > 0 ? 'text-amber-100/50' : 'text-rose-300/70' }}">
                                {{ $item['stock'] > 0 ? 'Restam '.$item['stock'].' na loja' : 'Esgotado na loja' }}
                            </p>
                        </div>
                    </div>
                    <div class="mt-auto flex flex-col gap-2">
                        @if($item['owned'])
                            <div class="flex flex-wrap gap-2">
                                @if($item['equipped'])
                                    <span class="text-xs text-emerald-300 self-center">Equipado</span>
                                @else
                                    <form method="POST" action="{{ route('student.shop.equip') }}">
                                        @csrf
                                        <input type="hidden" name="item" value="{{ $item['key'] }}">
                                        <button type="submit" class="game-btn !py-1 !px-3 text-sm">Equipar</button>
                                    </form>
                                @endif
                            </div>
                            @if($item['tradable'] ?? true)
                                @if($item['listed_price'] !== null)
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="text-xs text-violet-200">À venda por {{ $item['listed_price'] }}</span>
                                        <form method="POST" action="{{ route('student.shop.unlist') }}">
                                            @csrf
                                            <input type="hidden" name="item" value="{{ $item['key'] }}">
                                            <button type="submit" class="game-btn-ghost !py-1 !px-3 text-xs">Tirar do mercado</button>
                                        </form>
                                    </div>
                                @else
                                    <form method="POST" action="{{ route('student.shop.list') }}" class="flex flex-wrap items-end gap-2">
                                        @csrf
                                        <input type="hidden" name="item" value="{{ $item['key'] }}">
                                        <label class="space-y-1">
                                            <span class="text-xs uppercase tracking-wide text-purple-200/70">Preço</span>
                                            <input class="game-input w-24 !py-1" type="number" name="price" min="1" max="9999" value="{{ $item['price'] }}" required>
                                        </label>
                                        <button type="submit" class="game-btn-ghost !py-1 !px-3 text-sm">Anunciar</button>
                                    </form>
                                @endif
                            @else
                                <p class="text-xs text-emerald-200/60">Item de presença — não pode ser negociado.</p>
                            @endif
                        @else
                            @php
                                $canAfford = ($item['currency'] ?? 'relics') === 'seals'
                                    ? $enrollment->seals >= $item['price']
                                    : $enrollment->relics >= $item['price'];
                            @endphp
                            <form method="POST" action="{{ route('student.shop.purchase') }}">
                                @csrf
                                <input type="hidden" name="item" value="{{ $item['key'] }}">
                                <button
                                    type="submit"
                                    class="game-btn !py-1 !px-3 text-sm"
                                    @disabled($item['stock'] < 1 || ! $canAfford)
                                >
                                    Comprar na loja
                                </button>
                            </form>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </section>
@endforeach
@endsection
