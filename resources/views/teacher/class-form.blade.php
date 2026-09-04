@extends('layouts.game')

@section('title', ($class->exists ? 'Editar' : 'Nova').' turma')

@section('content')
<div class="max-w-xl mx-auto game-card p-8">
    <h1 class="font-display text-3xl text-amber-300 mb-6">{{ $class->exists ? 'Editar turma' : 'Nova turma' }}</h1>
    <form method="POST" action="{{ $class->exists ? route('teacher.classes.update', $class) : route('teacher.classes.store') }}" class="space-y-4">
        @csrf
        @if($class->exists) @method('PUT') @endif
        <label class="block">
            <span class="text-sm">Reino (área de atuação)</span>
            <select class="game-select mt-1" name="area_id" required @disabled($areas->isEmpty())>
                <option value="">Selecione...</option>
                @foreach($areas as $area)
                    <option value="{{ $area->id }}" @selected((string) old('area_id', $class->area_id) === (string) $area->id)>
                        {{ $area->emblemIcon() }} {{ $area->name }}
                    </option>
                @endforeach
            </select>
            @if($areas->isEmpty())
                <p class="text-sm text-rose-300 mt-2">Você ainda não foi vinculado a nenhum reino. Peça ao administrador.</p>
            @endif
        </label>
        @if($viewerIsAdmin ?? false)
            <label class="block">
                <span class="text-sm">Professor responsável</span>
                <select class="game-select mt-1" name="teacher_id" required @disabled(($teachers ?? collect())->isEmpty())>
                    <option value="">Selecione...</option>
                    @foreach(($teachers ?? []) as $teacher)
                        <option value="{{ $teacher->id }}" @selected((string) old('teacher_id', $class->teacher_id) === (string) $teacher->id)>
                            {{ $teacher->name }}
                            @if($teacher->areas->isNotEmpty())
                                — {{ $teacher->areas->pluck('name')->join(', ') }}
                            @endif
                        </option>
                    @endforeach
                </select>
                <p class="text-xs text-amber-100/50 mt-1">O professor precisa pertencer ao reino escolhido. Use isto para destravar turmas órfãs.</p>
            </label>
        @endif
        <label class="block">
            <span class="text-sm">Nome</span>
            <input class="game-input mt-1" name="name" value="{{ old('name', $class->name) }}" required>
        </label>
        <label class="block">
            <span class="text-sm">Ano / período</span>
            <input class="game-input mt-1" name="year" value="{{ old('year', $class->year) }}">
        </label>
        <label class="block">
            <span class="text-sm">Modo da nota</span>
            <select class="game-select mt-1" name="score_mode">
                <option value="up_from_zero" @selected(old('score_mode', $class->score_mode) === 'up_from_zero')>Começam com 0 (sobe)</option>
                <option value="down_from_hundred" @selected(old('score_mode', $class->score_mode) === 'down_from_hundred')>Começam com 100 (pode cair)</option>
            </select>
        </label>
        <label class="block">
            <span class="text-sm">Peso da linha “Nota equipe”</span>
            <input class="game-input mt-1" type="number" min="1" max="10" name="team_grade_weight" value="{{ old('team_grade_weight', $class->team_grade_weight ?: 1) }}">
        </label>
        <label class="block">
            <span class="text-sm">Peso da nota “Comportamento”</span>
            <input class="game-input mt-1" type="number" min="1" max="10" name="behavior_grade_weight" value="{{ old('behavior_grade_weight', $class->behavior_grade_weight ?: 1) }}">
        </label>
        <button class="game-btn" type="submit">Salvar</button>
    </form>
</div>
@endsection
