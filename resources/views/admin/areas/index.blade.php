@extends('layouts.game')

@section('title', 'Reinos — Admin')

@section('content')
<div class="flex flex-wrap items-center justify-between gap-4 mb-8">
    <div>
        <a href="{{ route('admin.dashboard') }}" class="game-btn-ghost text-sm mb-2 inline-block">← Admin</a>
        <h1 class="font-display text-4xl text-amber-300">Reinos</h1>
        <p class="text-amber-100/65">Áreas de atuação isoladas no mapa.</p>
    </div>
    <a class="game-btn" href="{{ route('admin.areas.create') }}">Novo reino</a>
</div>

<div class="space-y-3">
    @forelse($areas as $area)
        <div class="game-card p-5 flex flex-wrap items-center justify-between gap-4">
            <div>
                <h2 class="font-display text-xl text-amber-200">{{ $area->emblemIcon() }} {{ $area->name }}</h2>
                <p class="text-sm text-amber-100/55 mt-1">
                    /{{ $area->slug }} · {{ $area->teachers_count }} professores · {{ $area->classes_count }} turmas
                    @unless($area->is_active) · <span class="text-rose-300">inativo</span> @endunless
                </p>
            </div>
            <div class="flex gap-2">
                <a href="{{ route('admin.areas.edit', $area) }}" class="game-btn-ghost text-sm">Editar</a>
                <form method="POST" action="{{ route('admin.areas.destroy', $area) }}" onsubmit="return confirm('Excluir este reino?')">
                    @csrf @method('DELETE')
                    <button type="submit" class="text-rose-300 text-sm">Excluir</button>
                </form>
            </div>
        </div>
    @empty
        <div class="game-card p-8 text-center text-amber-100/60">Nenhum reino ainda.</div>
    @endforelse
</div>
@endsection
