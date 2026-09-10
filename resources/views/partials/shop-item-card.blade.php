@php
    $currency = $item['currency'] ?? 'relics';
    $canAfford = match ($currency) {
        'seals' => $enrollment->seals >= $item['price'],
        'auras' => ($auras ?? 0) >= $item['price'],
        default => $enrollment->relics >= $item['price'],
    };
    $priceClass = match ($currency) {
        'seals' => 'text-emerald-300',
        'auras' => 'text-violet-300',
        default => 'text-cyan-300',
    };
@endphp
<article class="game-card shop-item-card {{ $item['equipped'] ? 'is-equipped' : '' }}">
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
                @if($currency === 'seals')
                    <span class="shop-item-card__badge shop-item-card__badge--seals">só presença</span>
                @elseif($currency === 'auras')
                    <span class="shop-item-card__badge">só reino</span>
                @endif
            </div>
            <p class="text-sm mt-3 {{ $priceClass }}">
                {{ $item['price'] }} {{ $item['currency_label'] ?? 'Relíquias' }}
            </p>
            <p class="text-xs mt-1 {{ $item['stock'] > 0 ? 'text-amber-100/50' : 'text-rose-300/70' }}">
                {{ $item['stock'] > 0 ? 'Restam '.$item['stock'].' na loja' : 'Esgotado na loja' }}
            </p>
            <p class="text-xs text-amber-200/70 mt-1">
                Fortalece na arena quando equipado
            </p>
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
                    <p class="text-xs {{ $currency === 'auras' ? 'text-violet-200/60' : 'text-emerald-200/60' }}">
                        {{ $currency === 'auras' ? 'Item de Aura — não pode ser negociado.' : 'Item de presença — não pode ser negociado.' }}
                    </p>
                @endif
            @else
                <form method="POST" action="{{ route('student.shop.purchase') }}">
                    @csrf
                    <input type="hidden" name="item" value="{{ $item['key'] }}">
                    <button
                        type="submit"
                        class="game-btn !py-1 !px-3 text-sm w-full"
                        @disabled($item['stock'] < 1 || ! $canAfford)
                    >
                        Comprar na loja
                    </button>
                </form>
            @endif
        </div>
    </div>
</article>
