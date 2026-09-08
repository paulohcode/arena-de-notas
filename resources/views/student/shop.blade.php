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
            · Glória {{ $enrollment->glory }}
        </p>
        <p class="text-sm text-amber-100/45 mt-2 max-w-xl">
            Relíquias sobem junto com a Glória nos duelos, mas só elas são gastas aqui. O ranking da arena não muda.
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
                            <p class="text-xs text-purple-200/60">{{ $item['rarity_label'] }}</p>
                            <p class="text-sm text-cyan-300 mt-1">{{ $item['price'] }} Relíquias</p>
                        </div>
                    </div>
                    <div class="mt-auto flex flex-wrap gap-2">
                        @if($item['owned'])
                            @if($item['equipped'])
                                <span class="text-xs text-emerald-300 self-center">Equipado</span>
                            @else
                                <form method="POST" action="{{ route('student.shop.equip') }}">
                                    @csrf
                                    <input type="hidden" name="item" value="{{ $item['key'] }}">
                                    <button type="submit" class="game-btn !py-1 !px-3 text-sm">Equipar</button>
                                </form>
                            @endif
                        @else
                            <form method="POST" action="{{ route('student.shop.purchase') }}">
                                @csrf
                                <input type="hidden" name="item" value="{{ $item['key'] }}">
                                <button
                                    type="submit"
                                    class="game-btn !py-1 !px-3 text-sm"
                                    @disabled($enrollment->relics < $item['price'])
                                >
                                    Comprar
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
