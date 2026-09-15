@extends('layouts.game')

@section('title', 'Pote do Chefão — '.$season->name)

@section('content')
<a href="{{ route('teacher.seasons.boss', $season) }}" class="game-btn-ghost mb-6 inline-flex">← Mesa do chefão</a>

<div class="hero-banner !mb-6 reveal">
    <p class="hero-kicker">Arrecadação da temporada</p>
    <h1 class="hero-title flex flex-wrap items-center gap-3">
        <span class="text-5xl">{{ $boss['icon'] ?? '🏺' }}</span>
        <span>Pote e desafios</span>
    </h1>
    <p class="text-amber-100/70 mt-2 max-w-2xl">
        {{ $season->bossDisplayName() }} · {{ $season->name }}
    </p>
    <p class="text-xs text-amber-100/45 mt-2">
        Os potes mostram o saldo atual. A lista abaixo filtra só os desafios pagos do dia escolhido.
    </p>
</div>

<form method="GET" action="{{ route('teacher.seasons.boss.bank', $season) }}" class="game-card p-5 mb-8 reveal flex flex-wrap items-end gap-4">
    <label class="block">
        <span class="text-sm text-amber-100/70">Data</span>
        <input class="game-input mt-1 block" type="date" name="date" value="{{ $selectedDate }}" required>
    </label>
    <button class="game-btn" type="submit">Ver o dia</button>
</form>

<section class="mb-8 reveal">
    <h2 class="font-display text-2xl text-amber-200 mb-4">Potes atuais</h2>
    @if(empty($banks))
        <div class="game-card p-6 text-amber-100/60 text-sm">
            Vincule turmas a esta temporada para acumular taxas.
        </div>
    @else
        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach($banks as $row)
                <div class="game-card p-5 flex flex-wrap items-center gap-4">
                    <div class="boss-pot {{ $row['fill_percent'] >= 100 ? 'is-full' : '' }}" aria-label="Pote da turma {{ $row['class']->name }}">
                        <div class="boss-pot-vessel">
                            <div class="boss-pot-fill" data-boss-pot-fill="{{ $row['fill_percent'] }}"></div>
                        </div>
                    </div>
                    <div class="min-w-0">
                        <p class="font-display text-lg text-amber-200">{{ $row['class']->name }}</p>
                        <p class="text-sm text-amber-100/80 mt-1">
                            {{ \App\Models\GameCurrency::format('relics', $row['relics']) }}
                        </p>
                        <p class="text-xs text-amber-100/45 mt-1">
                            {{ $row['battles'] }} batalha(s) desde o último sorteio
                        </p>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</section>

<section class="reveal">
    <div class="flex flex-wrap items-end justify-between gap-3 mb-4">
        <div>
            <h2 class="font-display text-2xl text-violet-200">Desafios pagos · {{ $dayLabel }}</h2>
            <p class="text-xs text-amber-100/45 mt-1">
                {{ $totals['battles'] }} combate(s)
                · {{ $totals['wins'] }} vitória(s) do aluno
                · {{ $totals['losses'] }} derrota(s)
                · taxas {{ \App\Models\GameCurrency::format('relics', $totals['fees']) }}
                @if($totals['jackpot_relics'] > 0)
                    · jackpot {{ \App\Models\GameCurrency::format('relics', $totals['jackpot_relics']) }}
                @endif
            </p>
        </div>
    </div>

    @if($challenges->isEmpty())
        <div class="game-card p-8 text-center text-amber-100/60 text-sm">
            Nenhum desafio pago ao chefão neste dia.
        </div>
    @else
        <div class="game-card p-5 space-y-1">
            <ul class="divide-y divide-purple-900/40">
                @foreach($challenges as $challenge)
                    @php
                        $loot = $challenge->lootTotals();
                        $jackpot = $challenge->jackpotTotals();
                        $when = $challenge->resolved_at
                            ? $challenge->resolved_at->timezone(config('app.display_timezone'))->format('H:i')
                            : '—';
                    @endphp
                    <li class="flex flex-wrap items-start justify-between gap-3 py-3 text-sm">
                        <div class="min-w-0 space-y-1">
                            <p>
                                <span class="text-amber-100/45">{{ $when }}</span>
                                ·
                                <span class="{{ $challenge->won ? 'text-emerald-300' : 'text-rose-300' }}">
                                    {{ $challenge->won ? 'Vitória' : 'Derrota' }}
                                </span>
                                ·
                                <span class="font-semibold">
                                    {{ $challenge->student?->name }}
                                    @if($challenge->student?->arenaName())
                                        <span class="text-amber-300 font-normal"> · {{ $challenge->student->arenaName() }}</span>
                                    @endif
                                </span>
                                <span class="text-amber-100/45">({{ $challenge->schoolClass?->name }})</span>
                            </p>
                            <p class="text-xs text-amber-100/55">
                                Taxa {{ \App\Models\GameCurrency::format('relics', (int) $challenge->fee_relics) }}
                                · Loot
                                {{ \App\Models\GameCurrency::format('relics', $loot['relics']) }}
                                · {{ \App\Models\GameCurrency::format('seals', $loot['seals']) }}
                                · {{ \App\Models\GameCurrency::format('auras', $loot['auras']) }}
                            </p>
                            @if($jackpot)
                                <p class="text-xs text-amber-200 font-semibold">
                                    Pote: +{{ \App\Models\GameCurrency::format('relics', $jackpot['relics']) }}
                                    <span class="font-normal text-amber-100/55">
                                        ({{ $jackpot['percent'] }}% de {{ $jackpot['bank_before'] }})
                                    </span>
                                </p>
                            @endif
                        </div>
                        <a class="text-amber-200 underline text-xs shrink-0" href="{{ route('teacher.seasons.vigil.show', [$season, $challenge]) }}">
                            Replay
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</section>
@endsection
