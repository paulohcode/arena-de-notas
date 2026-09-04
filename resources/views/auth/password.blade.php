@extends('layouts.game')

@section('title', 'Trocar senha')

@section('content')
<div class="max-w-md mx-auto game-card p-8">
    <h1 class="font-display text-3xl text-amber-300">Nova senha</h1>
    <p class="text-purple-200/70 mt-2 mb-6">No primeiro acesso, troque a senha inicial.</p>
    <form method="POST" action="{{ route('password.update') }}" class="space-y-4">
        @csrf
        @method('PUT')
        <label class="block">
            <span class="text-sm">Nova senha</span>
            <input class="game-input mt-1" type="password" name="password" required>
        </label>
        <label class="block">
            <span class="text-sm">Confirmar senha</span>
            <input class="game-input mt-1" type="password" name="password_confirmation" required>
        </label>
        <button class="game-btn w-full" type="submit">Salvar senha</button>
    </form>
</div>
@endsection
