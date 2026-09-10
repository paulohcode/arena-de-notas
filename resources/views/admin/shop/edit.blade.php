@extends('layouts.game')

@section('title', 'Editar item — Loja')

@section('content')
<div class="mb-6">
    <a href="{{ route('admin.shop.index') }}" class="game-btn-ghost text-sm">← Loja</a>
</div>

<div class="game-card p-5 space-y-4">
    <div>
        <h1 class="font-display text-3xl text-amber-300">Editar item</h1>
        <p class="text-sm text-amber-100/60 mt-1">Quem já comprou continua com o item. O estoque de cada turma se ajusta na loja da turma.</p>
    </div>
    @include('partials.shop-item-form', [
        'action' => route('admin.shop.items.update', $item),
        'item' => $item,
        'cancel' => route('admin.shop.index'),
    ])
</div>
@endsection
