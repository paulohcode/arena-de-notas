@extends('layouts.game')

@section('title', 'Editar mascote — '.$class->name)

@section('content')
<div class="mb-8 reveal">
    <a href="{{ route('teacher.pets.show', $class) }}" class="game-btn-ghost text-sm">← Mascotes da turma</a>
    <h1 class="font-display text-4xl text-amber-300 mt-3">Editar · {{ $pet->name }}</h1>
</div>

<div class="game-card p-5 reveal">
    @if($pet->gifUrl())
        <div class="mb-4">
            @include('partials.pet-sprite', [
                'spriteKey' => $pet->spriteKey(),
                'gifUrl' => $pet->gifUrl(),
                'size' => 'lg',
                'name' => $pet->name,
            ])
        </div>
    @endif
    @include('partials.pet-form', [
        'action' => route('teacher.pets.update', [$class, $pet]),
        'pet' => $pet,
        'cancel' => route('teacher.pets.show', $class),
        'scopeClasses' => $scopeClasses,
    ])
</div>
@endsection
