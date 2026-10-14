@extends('layouts.game')

@section('title', 'Forja — Admin')

@section('content')
<div class="flex flex-wrap items-center justify-between gap-4 mb-8 reveal">
    <div>
        <a href="{{ route('admin.dashboard') }}" class="game-btn-ghost text-sm mb-2 inline-block">← Admin</a>
        <p class="hero-kicker !mb-1">Economia da forja</p>
        <h1 class="font-display text-4xl text-amber-300">Cofre da Forja</h1>
        <p class="text-amber-100/65 mt-1 max-w-2xl">
            Taxas de 15% das forjas dos alunos. Sorteie o pote entre quem forjou desde o último sorteio.
        </p>
    </div>
</div>

@error('raffle')
    <div class="game-card p-4 mb-6 text-rose-300 reveal">{{ $message }}</div>
@enderror

<section class="mb-10 reveal space-y-4">
    <div>
        <h2 class="font-display text-2xl text-amber-200">Cofres por reino</h2>
        <p class="text-sm text-amber-100/60 mt-1">Você escolhe quando sortear o pote.</p>
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
                        <p class="text-sm text-amber-100/55 mt-1">{{ $eligible }} forjador(es) elegível(is)</p>
                    </div>
                    <form method="POST" action="{{ route('admin.forge.raffle', $area) }}" onsubmit="return confirm('Sortear o pote da forja de {{ $area->name }} entre os elegíveis?')">
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
@endsection
