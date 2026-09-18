@extends('layouts.game')

@section('title', 'Editar cotação — Admin')

@section('content')
<div class="mb-8 reveal">
    <a href="{{ route('admin.exchange.index') }}" class="game-btn-ghost text-sm">← Casa de Câmbio</a>
    <h1 class="font-display text-4xl text-amber-300 mt-3">Editar cotação</h1>
    <p class="text-amber-100/60 mt-1">{{ $rate->parityLabel() }}</p>
</div>

<div class="game-card p-5 reveal">
    <form method="POST" action="{{ route('admin.exchange.update', $rate) }}" class="space-y-4">
        @csrf
        @method('PUT')
        @include('admin.exchange._form', [
            'currencyOptions' => $currencyOptions,
            'rate' => $rate,
        ])
        <label class="flex items-center gap-2 text-sm text-amber-100/80">
            <input
                type="checkbox"
                name="is_active"
                value="1"
                class="rounded border-amber-500/40"
                @checked(old('is_active', $rate->is_active))
            >
            Cotação ativa (visível para os alunos)
        </label>
        <div class="flex flex-wrap gap-3">
            <button class="game-btn" type="submit">Salvar cotação</button>
            <a class="game-btn-ghost" href="{{ route('admin.exchange.index') }}">Cancelar</a>
        </div>
    </form>
</div>
@endsection
