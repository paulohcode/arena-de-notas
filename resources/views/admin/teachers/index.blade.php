@extends('layouts.game')

@section('title', 'Professores — Admin')

@section('content')
<div class="flex flex-wrap items-center justify-between gap-4 mb-8">
    <div>
        <a href="{{ route('admin.dashboard') }}" class="game-btn-ghost text-sm mb-2 inline-block">← Admin</a>
        <h1 class="font-display text-4xl text-amber-300">Professores</h1>
        <p class="text-amber-100/65">Cada professor pode atuar em um ou mais reinos.</p>
    </div>
    <a class="game-btn" href="{{ route('admin.teachers.create') }}">Novo professor</a>
</div>

<div class="space-y-3">
    @forelse($teachers as $teacher)
        <div class="game-card p-5 flex flex-wrap items-center justify-between gap-4">
            <div>
                <h2 class="font-display text-xl text-amber-200">{{ $teacher->name }}</h2>
                <p class="text-sm text-amber-100/55 mt-1">{{ $teacher->email }}</p>
                <p class="text-xs text-amber-100/45 mt-1">
                    @forelse($teacher->areas as $area)
                        {{ $area->emblemIcon() }} {{ $area->name }}@unless($loop->last), @endunless
                    @empty
                        Sem reinos vinculados
                    @endforelse
                </p>
            </div>
            <div class="flex gap-2">
                <a href="{{ route('admin.teachers.edit', $teacher) }}" class="game-btn-ghost text-sm">Editar</a>
                <form method="POST" action="{{ route('admin.teachers.destroy', $teacher) }}" onsubmit="return confirm('Excluir este professor?')">
                    @csrf @method('DELETE')
                    <button type="submit" class="text-rose-300 text-sm">Excluir</button>
                </form>
            </div>
        </div>
    @empty
        <div class="game-card p-8 text-center text-amber-100/60">Nenhum professor cadastrado.</div>
    @endforelse
</div>
@endsection
