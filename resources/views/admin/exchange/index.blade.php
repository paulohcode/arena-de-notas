@extends('layouts.game')

@section('title', 'Casa de Câmbio — Admin')

@section('content')
<div class="flex flex-wrap items-center justify-between gap-4 mb-8 reveal">
    <div>
        <a href="{{ route('admin.dashboard') }}" class="game-btn-ghost text-sm mb-2 inline-block">← Admin</a>
        <p class="hero-kicker !mb-1">Economia da arena</p>
        <h1 class="font-display text-4xl text-amber-300">Casa de Câmbio</h1>
        <p class="text-amber-100/65 mt-1 max-w-2xl">
            Cada combinação de moedas tem uma única oferta. Ex.: comprar Relíquias pagando Selos
            existe só uma vez — se quiser mudar de 100/10 para 120/15, edite a oferta.
            Glória não entra no câmbio.
        </p>
    </div>
</div>

<div class="game-card p-5 mb-8 reveal space-y-4">
    <div>
        <h2 class="font-display text-xl text-amber-200">Nova oferta</h2>
        <p class="text-sm text-amber-100/60 mt-1">
            Não cadastre o mesmo par duas vezes com valores diferentes. Ajuste a oferta existente.
        </p>
    </div>
    <form method="POST" action="{{ route('admin.exchange.store') }}" class="space-y-4">
        @csrf
        @include('admin.exchange._form', [
            'currencyOptions' => $currencyOptions,
            'rate' => null,
        ])
        <button class="game-btn" type="submit">Cadastrar oferta</button>
    </form>
</div>

<div class="space-y-3">
    @forelse($rates as $rate)
        <article class="game-card p-5 flex flex-wrap items-center justify-between gap-4 reveal">
            <div class="min-w-0">
                <p class="font-display text-xl text-amber-200">{{ $rate->offerLabel() }}</p>
                <p class="text-sm text-amber-100/55 mt-1">
                    @if($rate->is_active)
                        <span class="text-emerald-300">Ativa</span>
                    @else
                        <span class="text-rose-300">Inativa</span>
                    @endif
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <a class="game-btn-ghost !py-1 !px-3 text-sm" href="{{ route('admin.exchange.edit', $rate) }}">Editar</a>
                <form method="POST" action="{{ route('admin.exchange.destroy', $rate) }}" onsubmit="return confirm('Remover esta oferta?')">
                    @csrf
                    @method('DELETE')
                    <button class="game-btn-ghost !py-1 !px-3 text-sm text-rose-300" type="submit">Excluir</button>
                </form>
            </div>
        </article>
    @empty
        <div class="game-card p-8 text-center text-amber-100/60">Nenhuma oferta cadastrada ainda.</div>
    @endforelse
</div>
@endsection
