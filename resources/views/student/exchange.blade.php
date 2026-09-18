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
            Escolha o que quer comprar e com qual moeda pagar. Cada oferta usa um lote; aumente os lotes para comprar mais.
        </p>
    </div>
    <div class="flex flex-wrap gap-2">
        <a class="game-btn-ghost" href="{{ route('student.shop.index') }}">Loja</a>
        <a class="game-btn-ghost" href="{{ route('student.pets.index') }}">Mascotes</a>
        <a class="game-btn-ghost" href="{{ route('student.dashboard') }}">Minha ficha</a>
    </div>
</div>

@error('lots')
    <div class="game-card p-4 mb-6 text-rose-300 reveal">{{ $message }}</div>
@enderror
@error('exchange_rate_id')
    <div class="game-card p-4 mb-6 text-rose-300 reveal">{{ $message }}</div>
@enderror
@error('exchange')
    <div class="game-card p-4 mb-6 text-rose-300 reveal">{{ $message }}</div>
@enderror

<div class="space-y-6">
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
@endsection
