@extends('layouts.game')

@section('title', ($area->exists ? 'Editar' : 'Novo').' reino')

@section('content')
<div class="mb-6">
    <a href="{{ route('admin.areas.index') }}" class="game-btn-ghost text-sm">← Reinos</a>
</div>

<div class="max-w-xl mx-auto game-card p-8">
    <h1 class="font-display text-3xl text-amber-300 mb-6">{{ $area->exists ? 'Editar reino' : 'Novo reino' }}</h1>
    <form method="POST" action="{{ $area->exists ? route('admin.areas.update', $area) : route('admin.areas.store') }}" class="space-y-4">
        @csrf
        @if($area->exists) @method('PUT') @endif

        <label class="block">
            <span class="text-sm">Nome</span>
            <input class="game-input mt-1" name="name" value="{{ old('name', $area->name) }}" required>
        </label>
        <label class="block">
            <span class="text-sm">Slug (URL)</span>
            <input class="game-input mt-1" name="slug" value="{{ old('slug', $area->slug) }}" placeholder="gerado-automaticamente">
        </label>
        <label class="block">
            <span class="text-sm">Descrição</span>
            <textarea class="game-input mt-1" name="description" rows="2">{{ old('description', $area->description) }}</textarea>
        </label>
        <label class="block">
            <span class="text-sm">Cor</span>
            <input class="game-input mt-1" type="color" name="color" value="{{ old('color', $area->color ?: '#c2410c') }}">
        </label>
        <label class="block">
            <span class="text-sm">Emblema</span>
            <select class="game-select mt-1" name="emblem">
                @foreach(\App\Models\Area::EMBLEMS as $key => $icon)
                    <option value="{{ $key }}" @selected(old('emblem', $area->emblem) === $key)>{{ $icon }} {{ $key }}</option>
                @endforeach
            </select>
        </label>
        <div class="grid grid-cols-2 gap-4">
            <label class="block">
                <span class="text-sm">Posição X no mapa (5–95)</span>
                <input class="game-input mt-1" type="number" min="5" max="95" name="map_x" value="{{ old('map_x', $area->map_x ?: 50) }}">
            </label>
            <label class="block">
                <span class="text-sm">Posição Y no mapa (5–95)</span>
                <input class="game-input mt-1" type="number" min="5" max="95" name="map_y" value="{{ old('map_y', $area->map_y ?: 50) }}">
            </label>
        </div>
        <label class="flex items-center gap-2">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $area->is_active ?? true))>
            <span class="text-sm">Reino ativo no mapa</span>
        </label>
        <button class="game-btn" type="submit">Salvar</button>
    </form>
</div>
@endsection
