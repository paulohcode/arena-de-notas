@extends('layouts.game')

@section('title', 'Editar item — '.$class->name)

@section('content')
<div class="mb-6">
    <a href="{{ route('teacher.shop.show', $class) }}" class="game-btn-ghost text-sm">← Loja da turma</a>
</div>

<div class="game-card p-5 space-y-4">
    <div>
        <h1 class="font-display text-3xl text-amber-300">Editar item</h1>
        <p class="text-sm text-amber-100/60 mt-1">Quem já comprou continua com o item. A quantidade à venda continua no estoque da turma.</p>
    </div>
    @include('partials.shop-item-form', [
        'action' => route('teacher.shop.items.update', [$class, $item]),
        'item' => $item,
        'cancel' => route('teacher.shop.show', $class),
    ])
</div>
@endsection
