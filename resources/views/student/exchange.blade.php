@extends('layouts.game')

@section('title', 'Casa de Câmbio — '.$class->name)

@section('content')
<div class="flex flex-wrap items-start justify-between gap-4 mb-6 reveal">
    <div>
        <p class="hero-kicker !mb-1">Economia da arena</p>
        <h1 class="font-display text-4xl text-amber-300">Casa de Câmbio</h1>
        <p class="text-amber-100/60 mt-1">
            {{ $class->name }} ·
            <span class="text-cyan-300">{{ \App\Models\GameCurrency::format('relics', $enrollment->relics) }}</span>
            ·
            <span class="text-emerald-300">{{ \App\Models\GameCurrency::format('seals', $enrollment->seals) }}</span>
            ·
            <span class="text-violet-300">{{ \App\Models\GameCurrency::format('auras', $auras) }}</span>
        </p>
        <p class="text-sm text-amber-100/45 mt-2 max-w-xl">
            Compre nas ofertas oficiais ou negocie com colegas do mesmo reino. Em negociações, o comprador paga 10% de taxa para o cofre.
        </p>
    </div>
    <div class="flex flex-wrap gap-2">
        <a class="game-btn-ghost" href="{{ route('student.shop.index') }}">Loja</a>
        <a class="game-btn-ghost" href="{{ route('student.pets.index') }}">Mascotes</a>
        <a class="game-btn-ghost" href="{{ route('student.dashboard') }}">Minha ficha</a>
    </div>
</div>

@foreach(['lots', 'exchange_rate_id', 'exchange', 'listing', 'offer_amount', 'ask_amount', 'offer_currency', 'ask_currency'] as $errorKey)
    @error($errorKey)
        <div class="game-card p-4 mb-6 text-rose-300 reveal">{{ $message }}</div>
    @enderror
@endforeach

<div x-data="{ tab: {{ \Illuminate\Support\Js::from($tab) }} }">
    <div class="flex flex-wrap gap-2 mb-6 reveal">
        <button type="button" class="game-btn-ghost !py-1 !px-3 text-sm" :class="tab === 'cambio' && 'ring-1 ring-amber-400/50'" @click="tab = 'cambio'">Casa de Câmbio</button>
        <button type="button" class="game-btn-ghost !py-1 !px-3 text-sm" :class="tab === 'negociar' && 'ring-1 ring-amber-400/50'" @click="tab = 'negociar'">Negociar</button>
    </div>

    <div x-show="tab === 'cambio'" x-cloak class="space-y-6">
        @forelse($offers as $group)
            <section class="reveal space-y-3">
                <div>
                    <p class="text-xs uppercase tracking-[0.2em] text-orange-300/80">Comprar</p>
                    <h2 class="font-display text-2xl text-amber-200 mt-1">{{ $group['receive_label'] }}</h2>
                </div>
                <div class="grid md:grid-cols-2 gap-4">
                    @foreach($group['options'] as $option)
                        <form method="POST" action="{{ route('student.exchange.trade') }}" class="game-card p-4 space-y-3">
                            @csrf
                            <input type="hidden" name="exchange_rate_id" value="{{ $option['id'] }}">
                            <p class="font-display text-lg text-amber-100">
                                {{ \App\Models\GameCurrency::format($option['receive_currency'], $option['receive_amount']) }}
                            </p>
                            <p class="text-sm text-amber-100/70">
                                por {{ \App\Models\GameCurrency::format($option['pay_currency'], $option['pay_amount']) }}
                            </p>
                            <label class="block">
                                <span class="text-sm">Lotes</span>
                                <input
                                    class="game-input mt-1 w-full"
                                    type="number"
                                    name="lots"
                                    min="1"
                                    max="999"
                                    value="{{ old('lots', 1) }}"
                                    required
                                >
                            </label>
                            <button class="game-btn !py-1 !px-3 text-sm w-full" type="submit">Comprar</button>
                        </form>
                    @endforeach
                </div>
            </section>
        @empty
            <div class="game-card p-8 text-center text-amber-100/60">
                Nenhuma oferta disponível no momento.
                @unless($class->area_id)
                    <span class="block mt-2 text-sm">Esta turma não tem reino — ofertas com Aura ficam ocultas.</span>
                @endunless
            </div>
        @endforelse
    </div>

    <div x-show="tab === 'negociar'" x-cloak class="space-y-6">
        @unless($peerAvailable)
            <div class="game-card p-8 text-center text-amber-100/60">
                A negociação entre alunos exige que a turma pertença a um reino.
            </div>
        @else
            <section class="game-card p-5 reveal space-y-4" x-data="{
                askAmount: {{ (int) old('ask_amount', 100) }},
                fee() { const a = Math.max(1, Number(this.askAmount) || 0); return a < 1 ? 0 : Math.max(1, Math.ceil(a * 0.1)); }
            }">
                <div>
                    <h2 class="font-display text-xl text-amber-200">Novo anúncio</h2>
                    <p class="text-sm text-amber-100/60 mt-1">
                        As moedas que você oferece saem agora da sua carteira. O comprador pagará o preço + 10% de taxa para o cofre.
                    </p>
                </div>
                <form method="POST" action="{{ route('student.exchange.listings.store') }}" class="space-y-4">
                    @csrf
                    <div class="grid sm:grid-cols-2 gap-4">
                        <label class="block">
                            <span class="text-sm">Eu ofereço</span>
                            <select class="game-select mt-1 w-full" name="offer_currency" required>
                                @foreach($currencyOptions as $key => $label)
                                    <option value="{{ $key }}" @selected(old('offer_currency', 'seals') === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="block">
                            <span class="text-sm">Quantidade oferecida</span>
                            <input class="game-input mt-1 w-full" type="number" name="offer_amount" min="1" max="99999" value="{{ old('offer_amount', 50) }}" required>
                        </label>
                        <label class="block">
                            <span class="text-sm">Eu peço</span>
                            <select class="game-select mt-1 w-full" name="ask_currency" required>
                                @foreach($currencyOptions as $key => $label)
                                    <option value="{{ $key }}" @selected(old('ask_currency', 'relics') === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="block">
                            <span class="text-sm">Quantidade pedida</span>
                            <input class="game-input mt-1 w-full" type="number" name="ask_amount" min="1" max="99999" x-model.number="askAmount" value="{{ old('ask_amount', 100) }}" required>
                        </label>
                    </div>
                    <p class="text-sm text-amber-100/55">
                        Taxa do comprador: <span class="text-violet-300" x-text="fee()"></span> da moeda pedida (10%).
                    </p>
                    <button class="game-btn" type="submit">Publicar anúncio</button>
                </form>
            </section>

            @if($myListings->isNotEmpty())
                <section class="reveal space-y-3">
                    <h2 class="font-display text-xl text-amber-200">Meus anúncios</h2>
                    <div class="grid md:grid-cols-2 gap-4">
                        @foreach($myListings as $listing)
                            <article class="game-card p-4 space-y-3">
                                <p class="font-display text-lg text-amber-100">
                                    Ofereço {{ $listing->offerLabel() }}
                                </p>
                                <p class="text-sm text-amber-100/70">
                                    Peço {{ $listing->askLabel() }}
                                    · taxa do comprador {{ \App\Models\GameCurrency::format($listing->ask_currency, $listing->feeAmount()) }}
                                </p>
                                <form method="POST" action="{{ route('student.exchange.listings.cancel', $listing) }}">
                                    @csrf
                                    <button class="game-btn-ghost !py-1 !px-3 text-sm w-full text-rose-300" type="submit">Cancelar</button>
                                </form>
                            </article>
                        @endforeach
                    </div>
                </section>
            @endif

            <section class="reveal space-y-3">
                <h2 class="font-display text-xl text-amber-200">Anúncios do reino</h2>
                @forelse($openListings as $listing)
                    @php $buyerTotal = $listing->ask_amount + $listing->feeAmount(); @endphp
                    <article class="game-card p-4 flex flex-wrap items-center justify-between gap-4">
                        <div class="min-w-0">
                            <p class="font-display text-lg text-amber-100">
                                {{ $listing->seller?->arenaName() ?: $listing->seller?->name }} · {{ $listing->sellerClass?->name }}
                            </p>
                            <p class="text-sm text-amber-100/70 mt-1">
                                Oferece {{ $listing->offerLabel() }} · pede {{ $listing->askLabel() }}
                            </p>
                            <p class="text-sm text-violet-300 mt-1">
                                Você paga {{ \App\Models\GameCurrency::format($listing->ask_currency, $buyerTotal) }}
                                (inclui taxa de {{ \App\Models\GameCurrency::format($listing->ask_currency, $listing->feeAmount()) }})
                            </p>
                        </div>
                        <form method="POST" action="{{ route('student.exchange.listings.accept', $listing) }}">
                            @csrf
                            <button class="game-btn !py-1 !px-3 text-sm" type="submit">Aceitar</button>
                        </form>
                    </article>
                @empty
                    <div class="game-card p-6 text-center text-amber-100/60">Nenhum anúncio aberto no reino agora.</div>
                @endforelse
            </section>
        @endunless
    </div>
</div>
@endsection
