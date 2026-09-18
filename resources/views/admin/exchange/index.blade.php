@extends('layouts.game')

@section('title', 'Casa de Câmbio — Admin')

@section('content')
<div class="flex flex-wrap items-center justify-between gap-4 mb-8 reveal">
    <div>
        <a href="{{ route('admin.dashboard') }}" class="game-btn-ghost text-sm mb-2 inline-block">← Admin</a>
        <p class="hero-kicker !mb-1">Economia da arena</p>
        <h1 class="font-display text-4xl text-amber-300">Casa de Câmbio</h1>
        <p class="text-amber-100/65 mt-1 max-w-2xl">
            Ofertas oficiais, cofre das taxas P2P (10% do comprador) e sorteio do pote entre quem negociou.
        </p>
    </div>
    <a class="game-btn" href="{{ route('admin.reports.exchange') }}">Relatório do câmbio</a>
</div>

@error('raffle')
    <div class="game-card p-4 mb-6 text-rose-300 reveal">{{ $message }}</div>
@enderror

<section class="mb-10 reveal space-y-4">
    <div>
        <h2 class="font-display text-2xl text-amber-200">Cofres por reino</h2>
        <p class="text-sm text-amber-100/60 mt-1">Taxas das negociações entre alunos. Você escolhe quando sortear o pote.</p>
    </div>
    <div class="space-y-4">
        @forelse($vaults as $row)
            @php
                $area = $row['area'];
                $vault = $row['vault'];
                $eligible = $row['eligible_count'];
                $raffles = $row['raffles'];
            @endphp
            <article class="game-card p-5 space-y-4">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <p class="text-xs uppercase tracking-[0.2em] text-orange-300/80">Reino</p>
                        <h3 class="font-display text-xl text-amber-200 mt-1">{{ $area->emblemIcon() }} {{ $area->name }}</h3>
                        <p class="text-sm text-amber-100/70 mt-2">
                            <span class="text-cyan-300">{{ \App\Models\GameCurrency::format('relics', $vault->relics) }}</span>
                            ·
                            <span class="text-emerald-300">{{ \App\Models\GameCurrency::format('seals', $vault->seals) }}</span>
                            ·
                            <span class="text-violet-300">{{ \App\Models\GameCurrency::format('auras', $vault->auras) }}</span>
                        </p>
                        <p class="text-sm text-amber-100/55 mt-1">{{ $eligible }} negociador(es) elegível(is)</p>
                    </div>
                    <form method="POST" action="{{ route('admin.exchange.raffle', $area) }}" onsubmit="return confirm('Sortear o pote de {{ $area->name }} entre os elegíveis?')">
                        @csrf
                        <button
                            class="game-btn"
                            type="submit"
                            @disabled($vault->isEmpty() || $eligible < 1)
                        >Sortear pote</button>
                    </form>
                </div>
                @if($raffles->isNotEmpty())
                    <div class="border-t border-amber-500/10 pt-3 space-y-1">
                        <p class="text-xs uppercase tracking-[0.2em] text-orange-300/80">Sorteios recentes</p>
                        @foreach($raffles as $raffle)
                            <p class="text-sm text-amber-100/70">
                                {{ $raffle->created_at?->format('d/m/Y H:i') }}
                                · {{ $raffle->winner?->name ?? '—' }}
                                · {{ \App\Models\GameCurrency::format('relics', $raffle->relics) }}
                                / {{ \App\Models\GameCurrency::format('seals', $raffle->seals) }}
                                / {{ \App\Models\GameCurrency::format('auras', $raffle->auras) }}
                            </p>
                        @endforeach
                    </div>
                @endif
            </article>
        @empty
            <div class="game-card p-6 text-center text-amber-100/60">Nenhum reino ativo.</div>
        @endforelse
    </div>
</section>

<div class="game-card p-5 mb-8 reveal space-y-4">
    <div>
        <h2 class="font-display text-xl text-amber-200">Nova oferta oficial</h2>
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
    <h2 class="font-display text-xl text-amber-200 reveal">Ofertas oficiais</h2>
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
