@extends('layouts.game')

@section('title', 'Entrar — Arena das Notas')

@section('content')
<div class="max-w-md mx-auto game-card p-8 reveal">
    <p class="hero-kicker !mb-2 text-left">Portal de entrada</p>
    <h1 class="font-display text-3xl text-amber-300">Entrar na arena</h1>
    <p class="text-amber-100/65 mt-2 mb-6">Administrador, professor e alunos usam o mesmo portal.</p>

    <form method="POST" action="{{ route('login') }}" class="space-y-4">
        @csrf
        <label class="block">
            <span class="text-sm text-amber-100/80">E-mail</span>
            <input class="game-input mt-1" type="email" name="email" value="{{ old('email') }}" required autofocus>
        </label>
        <label class="block">
            <span class="text-sm text-amber-100/80">Senha</span>
            <input class="game-input mt-1" type="password" name="password" required>
        </label>
        <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" name="remember"> Lembrar de mim
        </label>
        <button class="game-btn w-full" type="submit">Entrar na batalha</button>
    </form>
</div>
@endsection
