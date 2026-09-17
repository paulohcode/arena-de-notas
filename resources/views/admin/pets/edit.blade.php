@extends('layouts.game')

@section('title', 'Editar mascote — Admin')

@section('content')
<div class="mb-8 reveal">
    <a href="{{ route('admin.pets.index') }}" class="game-btn-ghost text-sm">← Mascotes</a>
    <h1 class="font-display text-4xl text-amber-300 mt-3">Editar · {{ $pet->name }}</h1>
    <p class="text-amber-100/60 mt-1">Turma: {{ $pet->schoolClass?->name ?? '—' }}</p>
</div>

<div class="game-card p-5 reveal">
    @include('partials.pet-form', [
        'action' => route('admin.pets.update', $pet),
        'pet' => $pet,
        'cancel' => route('admin.pets.index'),
        'scopeClasses' => $scopeClasses,
    ])
</div>
@endsection
