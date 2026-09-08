@extends('layouts.game')

@section('title', 'Loja — '.$class->name)

@section('content')
<div class="flex flex-wrap items-start justify-between gap-4 mb-8 reveal">
    <div>
        <a href="{{ auth()->user()->isAdmin() ? route('admin.shop.index') : route('teacher.classes.show', $class) }}" class="game-btn-ghost text-sm mb-2 inline-block">
            ← {{ auth()->user()->isAdmin() ? 'Lojas' : 'Turma' }}
        </a>
        <p class="hero-kicker !mb-1">Estoque e donos</p>
        <h1 class="font-display text-4xl text-amber-300">Loja · {{ $class->name }}</h1>
        <p class="text-amber-100/60 mt-1 max-w-2xl">
            Defina quantas cópias ainda estão à venda. O restante fica com quem comprou — e pode ser negociado entre os alunos.
        </p>
    </div>
    <a class="game-btn-ghost" href="{{ route('teacher.classes.show', $class) }}">Voltar à turma</a>
</div>

@foreach($slots as $slot => $slotLabel)
    <section class="mb-8 reveal">
        <h2 class="font-display text-xl text-amber-200 mb-3">{{ $slotLabel }}</h2>
        <div class="space-y-3">
            @foreach($catalog[$slot] as $item)
                <div class="game-card p-4">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div class="min-w-0">
                            <p class="font-semibold text-amber-100">{{ $item['name'] }}</p>
                            <p class="text-xs text-purple-200/60">{{ $item['rarity_label'] }} · {{ $item['price'] }} {{ $item['currency_label'] ?? 'Relíquias' }} na loja@if(! ($item['tradable'] ?? true)) · só presença@endif</p>
                            <p class="text-sm mt-2 {{ $item['stock'] > 0 ? 'text-cyan-300' : 'text-rose-300/80' }}">
                                {{ $item['stock'] > 0 ? $item['stock'].' à venda na loja' : 'Esgotado na loja' }}
                            </p>
                            <p class="text-sm text-amber-100/70 mt-2">
                                @if(count($item['owners']) === 0)
                                    Ninguém comprou ainda.
                                @else
                                    Donos:
                                    @foreach($item['owners'] as $owner)
                                        <span class="text-amber-100">
                                            {{ $owner['name'] }}
                                            @if($owner['character'])
                                                <span class="text-amber-300">({{ $owner['character'] }})</span>
                                            @endif
                                            @if($owner['equipped'])
                                                · equipado
                                            @endif
                                            @if(! $loop->last)
                                                ,
                                            @endif
                                        </span>
                                    @endforeach
                                @endif
                            </p>
                            @if(count($item['listings']) > 0)
                                <p class="text-sm text-violet-200/80 mt-1">
                                    No mercado:
                                    @foreach($item['listings'] as $listing)
                                        {{ $listing['seller'] }} por {{ $listing['price'] }} Relíquias
                                        @if(! $loop->last)
                                            ;
                                        @endif
                                    @endforeach
                                </p>
                            @endif
                        </div>
                        <form method="POST" action="{{ route('teacher.shop.restock', $class) }}" class="flex flex-wrap items-end gap-2">
                            @csrf
                            <input type="hidden" name="item" value="{{ $item['key'] }}">
                            <label class="space-y-1">
                                <span class="text-xs uppercase tracking-wide text-purple-200/70">À venda</span>
                                <input class="game-input w-24" type="number" name="quantity" min="0" max="99" value="{{ old('item') === $item['key'] ? old('quantity', $item['stock']) : $item['stock'] }}" required>
                            </label>
                            <button class="game-btn !py-1 !px-3 text-sm" type="submit">Atualizar</button>
                        </form>
                    </div>
                </div>
            @endforeach
        </div>
    </section>
@endforeach
@endsection
