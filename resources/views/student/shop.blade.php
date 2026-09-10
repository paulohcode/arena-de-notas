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
            ·
            <span class="text-violet-300">{{ $auras }} Aura</span>
            · Glória {{ $enrollment->glory }}
        </p>
        <p class="text-sm text-amber-100/45 mt-2 max-w-xl">
            Relíquias vêm da arena da turma. Selos vêm da presença. Aura vem de duelos entre turmas do mesmo reino e compra só itens da Loja de Aura.
            Itens <strong>equipados</strong> fortalecem você na arena. Há poucas cópias na loja.
        </p>
    </div>
    <div class="flex flex-wrap gap-2">
        <a class="game-btn-ghost" href="{{ route('student.arena.realm.index') }}">Entre turmas</a>
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
                <article class="game-card shop-item-card">
                    @include('partials.shop-item-art', [
                        'icon' => $listing['icon'],
                        'rarity' => $listing['rarity'],
                        'css' => $listing['css'] ?? null,
                        'slot' => $listing['slot'],
                    ])
                    <div class="shop-item-card__body">
                        <div>
                            <p class="font-display text-lg text-amber-100">{{ $listing['name'] }}</p>
                            <div class="shop-item-card__meta mt-2">
                                <span class="shop-item-card__badge">{{ $listing['rarity_label'] }}</span>
                            </div>
                            <p class="text-xs text-purple-200/60 mt-2">{{ $listing['seller_name'] }}</p>
                            <p class="text-sm text-cyan-300 mt-1">{{ $listing['price'] }} Relíquias</p>
                        </div>
                        <form method="POST" action="{{ route('student.shop.listings.buy', $listing['id']) }}" class="mt-auto">
                            @csrf
                            <button
                                type="submit"
                                class="game-btn !py-1 !px-3 text-sm w-full"
                                @disabled($listing['already_owned'] || $enrollment->relics < $listing['price'])
                            >
                                {{ $listing['already_owned'] ? 'Você já tem este item' : 'Comprar de colega' }}
                            </button>
                        </form>
                    </div>
                </article>
            @endforeach
        </div>
    @endif
</section>

<section class="mb-8 reveal" x-data="{ tab: 'turma' }">
    <div class="flex flex-wrap gap-2 mb-4">
        <button type="button" class="game-btn-ghost !py-1 !px-3 text-sm" :class="tab === 'turma' && 'ring-1 ring-amber-400/50'" @click="tab = 'turma'">Loja da turma</button>
        <button type="button" class="game-btn-ghost !py-1 !px-3 text-sm" :class="tab === 'aura' && 'ring-1 ring-violet-400/50'" @click="tab = 'aura'">Loja de Aura</button>
    </div>

    <div x-show="tab === 'turma'" x-cloak>
@foreach($slots as $slot => $slotLabel)
    <section class="mb-8">
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
        <div class="grid sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
            @foreach($catalog[$slot] as $item)
                @continue(($item['currency'] ?? 'relics') === 'auras')
                @include('partials.shop-item-card', ['item' => $item, 'enrollment' => $enrollment, 'auras' => $auras])
            @endforeach
        </div>
    </section>
@endforeach
    </div>

    <div x-show="tab === 'aura'" x-cloak>
        <p class="text-sm text-violet-200/70 mb-4">Itens compráveis só com Aura do reino. São únicos no reino: todas as turmas compartilham o mesmo estoque. Não entram no mercado P2P.</p>
        @php $auraItems = collect($catalog)->flatten(1)->filter(fn ($item) => ($item['currency'] ?? '') === 'auras'); @endphp
        @if($auraItems->isEmpty())
            <div class="game-card p-4 text-sm text-amber-100/55">Nenhum item de Aura cadastrado ainda.</div>
        @else
            <div class="grid sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                @foreach($auraItems as $item)
                    @include('partials.shop-item-card', ['item' => $item, 'enrollment' => $enrollment, 'auras' => $auras])
                @endforeach
            </div>
        @endif
    </div>
</section>
@endsection
