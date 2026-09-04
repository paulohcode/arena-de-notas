@extends('layouts.game')

@section('title', ($teacher->exists ? 'Editar' : 'Novo').' professor')

@section('content')
<div class="mb-6">
    <a href="{{ route('admin.teachers.index') }}" class="game-btn-ghost text-sm">← Professores</a>
</div>

<div class="max-w-xl mx-auto game-card p-8">
    <h1 class="font-display text-3xl text-amber-300 mb-6">{{ $teacher->exists ? 'Editar professor' : 'Novo professor' }}</h1>
    <form method="POST" action="{{ $teacher->exists ? route('admin.teachers.update', $teacher) : route('admin.teachers.store') }}" class="space-y-4">
        @csrf
        @if($teacher->exists) @method('PUT') @endif

        <label class="block">
            <span class="text-sm">Nome</span>
            <input class="game-input mt-1" name="name" value="{{ old('name', $teacher->name) }}" required>
        </label>
        <label class="block">
            <span class="text-sm">E-mail</span>
            <input class="game-input mt-1" type="email" name="email" value="{{ old('email', $teacher->email) }}" required>
        </label>
        <label class="block">
            <span class="text-sm">{{ $teacher->exists ? 'Nova senha (opcional)' : 'Senha inicial' }}</span>
            <input class="game-input mt-1" type="password" name="password" @unless($teacher->exists) required @endunless>
        </label>
        <label class="block">
            <span class="text-sm">Confirmar senha</span>
            <input class="game-input mt-1" type="password" name="password_confirmation" @unless($teacher->exists) required @endunless>
        </label>

        <fieldset class="space-y-2">
            <legend class="text-sm mb-2">Reinos de atuação</legend>
            @forelse($areas as $area)
                <label class="flex items-center gap-2">
                    <input type="checkbox" name="area_ids[]" value="{{ $area->id }}"
                        @checked(in_array($area->id, old('area_ids', $selectedAreaIds)))>
                    <span>{{ $area->emblemIcon() }} {{ $area->name }}</span>
                </label>
            @empty
                <p class="text-sm text-rose-300">Cadastre um reino antes de vincular professores.</p>
            @endforelse
        </fieldset>

        <button class="game-btn" type="submit">Salvar</button>
    </form>
</div>
@endsection
