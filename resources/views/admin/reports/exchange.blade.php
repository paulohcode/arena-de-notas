@extends('layouts.game')

@section('title', 'Relatório do câmbio — Admin')

@section('content')
@php
    $summary = $report['summary'];
@endphp

<div class="flex flex-wrap items-center justify-between gap-4 mb-8 reveal">
    <div>
        <a href="{{ route('admin.exchange.index') }}" class="game-btn-ghost text-sm mb-2 inline-block">← Casa de Câmbio</a>
        <p class="hero-kicker !mb-1">Economia da arena</p>
        <h1 class="font-display text-4xl text-amber-300">Relatório do câmbio</h1>
        <p class="text-amber-100/65 mt-1 max-w-2xl">
            Compras na casa e negociações entre alunos em {{ $report['day_label'] }}
            @if($report['area'])
                · {{ $report['area']->name }}
            @else
                · todos os reinos
            @endif.
        </p>
    </div>
    <a class="game-btn-ghost" href="{{ route('admin.reports.daily', ['date' => $selectedDate, 'area' => $selectedAreaId]) }}">Relatório diário</a>
</div>

<form method="GET" action="{{ route('admin.reports.exchange') }}" class="game-card p-5 mb-8 reveal flex flex-wrap items-end gap-4">
    <label class="block">
        <span class="text-sm text-amber-100/70">Data</span>
        <input class="game-input mt-1 block" type="date" name="date" value="{{ $selectedDate }}" required>
    </label>
    <label class="block min-w-[12rem]">
        <span class="text-sm text-amber-100/70">Reino</span>
        <select class="game-input mt-1 block w-full" name="area">
            <option value="">Todos os reinos</option>
            @foreach($areas as $areaOption)
                <option value="{{ $areaOption->id }}" @selected((int) $selectedAreaId === (int) $areaOption->id)>
                    {{ $areaOption->name }}
                </option>
            @endforeach
        </select>
    </label>
    <button class="game-btn" type="submit">Filtrar</button>
</form>

<div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-3 mb-8">
    <a href="#casa" class="game-card p-4 block reveal">
        <p class="text-xs uppercase tracking-[0.2em] text-orange-300/80">Casa oficial</p>
        <p class="font-display text-2xl text-amber-200 mt-1">{{ $summary['house_purchases'] }}</p>
        <p class="text-sm text-amber-100/55">Compras nas ofertas</p>
    </a>
    <a href="#negociacoes" class="game-card p-4 block reveal">
        <p class="text-xs uppercase tracking-[0.2em] text-orange-300/80">Negociações</p>
        <p class="font-display text-2xl text-amber-200 mt-1">{{ $summary['peer_trades'] }}</p>
        <p class="text-sm text-amber-100/55">Trocas entre alunos</p>
    </a>
    <a href="#negociacoes" class="game-card p-4 block reveal">
        <p class="text-xs uppercase tracking-[0.2em] text-orange-300/80">Taxas do dia</p>
        <p class="font-display text-lg text-amber-200 mt-1">
            {{ \App\Models\GameCurrency::format('relics', $summary['fees_relics']) }}
        </p>
        <p class="text-sm text-amber-100/55">
            {{ \App\Models\GameCurrency::format('seals', $summary['fees_seals']) }}
            · {{ \App\Models\GameCurrency::format('auras', $summary['fees_auras']) }}
        </p>
    </a>
    <a href="#sorteios" class="game-card p-4 block reveal">
        <p class="text-xs uppercase tracking-[0.2em] text-orange-300/80">Sorteios</p>
        <p class="font-display text-2xl text-amber-200 mt-1">{{ $summary['raffles'] }}</p>
        <p class="text-sm text-amber-100/55">Potes sorteados</p>
    </a>
</div>

<section id="casa" class="mb-10 reveal space-y-3">
    <h2 class="font-display text-2xl text-amber-200">Compras na casa de câmbio</h2>
    @forelse($report['house_purchases'] as $row)
        <article class="game-card p-4 flex flex-wrap items-start justify-between gap-3">
            <div>
                <p class="font-display text-lg text-amber-100">{{ $row['student_name'] }}</p>
                <p class="text-sm text-amber-100/60">{{ $row['class_name'] }} · {{ $row['area_name'] }} · {{ $row['created_at'] }}</p>
                <p class="text-sm text-amber-100/80 mt-2">
                    Pagou {{ $row['pay_label'] }} → recebeu {{ $row['receive_label'] }}
                    @if($row['lots'] > 1)
                        · {{ $row['lots'] }} lotes
                    @endif
                </p>
            </div>
        </article>
    @empty
        <div class="game-card p-6 text-center text-amber-100/60">Nenhuma compra na casa neste dia.</div>
    @endforelse
</section>

<section id="negociacoes" class="mb-10 reveal space-y-3">
    <h2 class="font-display text-2xl text-amber-200">Negociações entre alunos</h2>
    @forelse($report['peer_trades'] as $row)
        <article class="game-card p-4 space-y-2">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p class="font-display text-lg text-amber-100">
                        {{ $row['seller_name'] }} → {{ $row['buyer_name'] }}
                    </p>
                    <p class="text-sm text-amber-100/60">
                        {{ $row['seller_class'] }} / {{ $row['buyer_class'] }}
                        · {{ $row['area_name'] }}
                        · {{ $row['created_at'] }}
                    </p>
                </div>
                <p class="text-sm text-violet-300">Taxa {{ $row['fee_label'] }}</p>
            </div>
            <p class="text-sm text-amber-100/80">
                Ofereceu {{ $row['offer_label'] }} · pediu {{ $row['ask_label'] }}
            </p>
        </article>
    @empty
        <div class="game-card p-6 text-center text-amber-100/60">Nenhuma negociação entre alunos neste dia.</div>
    @endforelse
</section>

<section id="sorteios" class="mb-10 reveal space-y-3">
    <h2 class="font-display text-2xl text-amber-200">Sorteios do pote</h2>
    @forelse($report['raffles'] as $row)
        <article class="game-card p-4 flex flex-wrap items-start justify-between gap-3">
            <div>
                <p class="font-display text-lg text-amber-100">{{ $row['winner_name'] }}</p>
                <p class="text-sm text-amber-100/60">{{ $row['area_name'] }} · {{ $row['created_at'] }}</p>
            </div>
            <p class="text-sm text-emerald-300">{{ $row['prize_label'] }}</p>
        </article>
    @empty
        <div class="game-card p-6 text-center text-amber-100/60">Nenhum sorteio neste dia.</div>
    @endforelse
</section>
@endsection
