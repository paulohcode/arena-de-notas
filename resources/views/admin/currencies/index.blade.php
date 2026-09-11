@extends('layouts.game')

@section('title', 'Moedas — Admin')

@section('content')
<div class="flex flex-wrap items-center justify-between gap-4 mb-8 reveal">
    <div>
        <a href="{{ route('admin.dashboard') }}" class="game-btn-ghost text-sm mb-2 inline-block">← Admin</a>
        <p class="hero-kicker !mb-1">Economia da arena</p>
        <h1 class="font-display text-4xl text-amber-300">Moedas</h1>
        <p class="text-amber-100/65 mt-1 max-w-2xl">
            Troque o nome e o ícone de cada moeda. O saldo dos alunos não muda — só o que aparece na loja, na ficha e na arena.
        </p>
    </div>
</div>

<form method="POST" action="{{ route('admin.currencies.update') }}" class="space-y-4">
    @csrf
    @method('PUT')

    <div class="grid md:grid-cols-2 gap-4">
        @foreach($currencies as $currency)
            @php
                $nameValue = old('currencies.'.$currency->key.'.name', $currency->name);
                $iconValue = old('currencies.'.$currency->key.'.icon', $currency->icon);
            @endphp
            <article
                class="game-card p-5 space-y-4 reveal"
                x-data="{ name: {{ \Illuminate\Support\Js::from($nameValue) }}, icon: {{ \Illuminate\Support\Js::from($iconValue) }} }"
            >
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-xs uppercase tracking-[0.2em] text-orange-300/80">{{ $currency->key }}</p>
                        <p class="font-display text-2xl text-amber-200 mt-1">
                            <span x-text="icon" aria-hidden="true"></span>
                            <span x-text="name"></span>
                        </p>
                    </div>
                </div>
                <p class="text-sm text-amber-100/60">{{ \App\Models\GameCurrency::blurb($currency->key) }}</p>
                <div class="grid sm:grid-cols-2 gap-3">
                    <label class="block">
                        <span class="text-sm">Nome</span>
                        <input
                            class="game-input mt-1 w-full"
                            name="currencies[{{ $currency->key }}][name]"
                            x-model="name"
                            value="{{ $nameValue }}"
                            required
                            maxlength="40"
                        >
                    </label>
                    <label class="block">
                        <span class="text-sm">Ícone</span>
                        <input
                            class="game-input mt-1 w-full"
                            name="currencies[{{ $currency->key }}][icon]"
                            x-model="icon"
                            value="{{ $iconValue }}"
                            required
                            maxlength="32"
                            placeholder="💠"
                        >
                    </label>
                </div>
                <div class="flex flex-wrap gap-1" role="group" aria-label="Sugestões de ícone">
                    @foreach($iconSuggestions as $emoji)
                        <button
                            type="button"
                            class="game-btn-ghost !py-1 !px-2 text-lg"
                            @click="icon = {{ \Illuminate\Support\Js::from($emoji) }}"
                        >{{ $emoji }}</button>
                    @endforeach
                </div>
            </article>
        @endforeach
    </div>

    <div class="flex flex-wrap items-center gap-3 pt-2">
        <button class="game-btn" type="submit">Salvar moedas</button>
        <p class="text-sm text-amber-100/50">Use um emoji no ícone. Ex.: 💠 💮 ✨ 🏆</p>
    </div>
</form>
@endsection
