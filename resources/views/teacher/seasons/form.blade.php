@extends('layouts.game')

@section('title', $season->exists ? 'Editar temporada' : 'Nova temporada')

@section('content')
<div class="mb-6">
    <a href="{{ route('teacher.seasons.index') }}" class="game-btn-ghost text-sm">← Voltar</a>
</div>

<h1 class="font-display text-3xl text-amber-300 mb-6">
    {{ $season->exists ? 'Editar temporada' : 'Nova temporada' }}
</h1>

<form method="POST"
      action="{{ $season->exists ? route('teacher.seasons.update', $season) : route('teacher.seasons.store') }}"
      class="space-y-6 max-w-2xl"
      x-data="{ areaId: '{{ old('area_id', $season->area_id) }}' }">
    @csrf
    @if($season->exists)
        @method('PUT')
    @endif

    <div class="game-card p-6 space-y-4">
        <div>
            <label class="block text-sm text-amber-100/70 mb-1">Reino</label>
            <select class="game-select w-full" name="area_id" x-model="areaId" required>
                <option value="">Selecione...</option>
                @foreach($areas as $area)
                    <option value="{{ $area->id }}">{{ $area->emblemIcon() }} {{ $area->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-sm text-amber-100/70 mb-1">Nome da temporada</label>
            <input class="game-input w-full" name="name" placeholder="Ex: 1º Trimestre 2026"
                   value="{{ old('name', $season->name) }}" required maxlength="120">
        </div>
        <div>
            <label class="block text-sm text-amber-100/70 mb-1">Descrição (opcional)</label>
            <textarea class="game-input w-full" name="description" rows="2"
                      placeholder="Descreva o objetivo desta temporada"
                      maxlength="500">{{ old('description', $season->description) }}</textarea>
        </div>
        <div>
            <label class="block text-sm text-amber-100/70 mb-1">Chefão do Rito (opcional)</label>
            <select class="game-select w-full" name="boss_archetype">
                <option value="">Sem chefão nesta temporada</option>
                @foreach($archetypes as $key => $meta)
                    <option value="{{ $key }}" @selected(old('boss_archetype', $season->boss_archetype) === $key)>
                        {{ $meta['icon'] }} {{ $meta['name'] }}
                    </option>
                @endforeach
            </select>
            <p class="text-xs text-amber-100/45 mt-1">Arquétipo fantasia da temporada. A nota continua mandando no poder do aluno.</p>
        </div>
        <div>
            <label class="block text-sm text-amber-100/70 mb-1">Dificuldade do chefão</label>
            <select class="game-select w-full" name="boss_difficulty">
                @foreach($difficulties as $key => $label)
                    <option value="{{ $key }}" @selected(old('boss_difficulty', $season->boss_difficulty ?: 'normal') === $key)>
                        {{ $label }}
                    </option>
                @endforeach
            </select>
            <p class="text-xs text-amber-100/45 mt-1">Poder fixo do chefão — não escala no aluno.</p>
        </div>
    </div>

    <div class="game-card p-6 space-y-3">
        <div>
            <h2 class="font-display text-xl text-amber-200">Desafios do chefão por dia</h2>
            <p class="text-sm text-amber-100/55 mt-1">
                Quantas vezes o aluno pode pagar {{ \App\Support\BossArchetypeCatalog::BOSS_CHALLENGE_FEE }}
                {{ \App\Models\GameCurrency::label('relics') }} para enfrentar o chefão completo.
                Use 0 para fechar aquele dia. A Vigília (Sombra) continua com o limite próprio.
            </p>
        </div>
        @php
            $todayWeekday = \App\Support\ArenaSchedule::todayWeekday();
            $weekLimits = old('boss_challenge_days', null);
        @endphp
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-amber-100/55">
                        <th class="py-2 pr-3 font-medium">Dia</th>
                        <th class="py-2 font-medium">Desafios no dia</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-purple-900/40">
                    @foreach(\App\Support\ArenaSchedule::WEEKDAY_LABELS as $weekday => $label)
                        @php
                            $isToday = $weekday === $todayWeekday;
                            $limit = is_array($weekLimits)
                                ? (int) data_get($weekLimits, $weekday.'.daily_limit', $bossChallengeWeek[$weekday] ?? 1)
                                : (int) ($bossChallengeWeek[$weekday] ?? \App\Support\BossArchetypeCatalog::BOSS_CHALLENGE_DAILY_DEFAULT);
                        @endphp
                        <tr class="{{ $isToday ? 'bg-amber-400/10' : '' }}">
                            <td class="py-3 pr-3 align-top">
                                <span class="{{ $isToday ? 'text-amber-200 font-semibold' : 'text-amber-100/80' }}">{{ $label }}</span>
                                @if($isToday)
                                    <span class="block text-[11px] text-cyan-300/80">hoje</span>
                                @endif
                            </td>
                            <td class="py-3 align-top min-w-28">
                                <input class="game-input w-full max-w-[8rem]" type="number"
                                       name="boss_challenge_days[{{ $weekday }}][daily_limit]"
                                       min="0" max="{{ \App\Support\BossArchetypeCatalog::BOSS_CHALLENGE_DAILY_MAX }}"
                                       required value="{{ $limit }}">
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p class="text-xs text-amber-100/50">0 = fechado naquele dia. O limite vale só naquele dia da semana.</p>
    </div>

    <div class="game-card p-6">
        <h2 class="font-display text-xl text-amber-200 mb-4">Turmas participantes (mesmo reino)</h2>
        @if($classes->isEmpty())
            <p class="text-amber-100/60 text-sm">Você não tem turmas cadastradas ainda.</p>
        @else
            <div class="space-y-2">
                @foreach($classes as $class)
                    <label
                        class="flex items-center gap-3 p-3 rounded-lg hover:bg-black/20 cursor-pointer"
                        x-show="String(areaId) === '{{ $class->area_id }}'"
                        x-cloak
                    >
                        <input type="checkbox"
                               name="class_ids[]"
                               value="{{ $class->id }}"
                               @checked(in_array($class->id, $selectedClassIds))
                               x-bind:disabled="String(areaId) !== '{{ $class->area_id }}'"
                               class="w-4 h-4">
                        <div>
                            <p class="font-semibold text-amber-100">{{ $class->name }}</p>
                            <p class="text-xs text-amber-100/60">{{ $class->area?->name }} · {{ $class->year ?: 'Sem ano' }}</p>
                        </div>
                    </label>
                @endforeach
            </div>
            <p class="text-xs text-amber-100/45 mt-3" x-show="!areaId" x-cloak>Escolha um reino para listar as turmas.</p>
        @endif
    </div>

    <div class="flex gap-3">
        <button class="game-btn" type="submit">
            {{ $season->exists ? 'Salvar alterações' : 'Criar temporada' }}
        </button>
        <a href="{{ route('teacher.seasons.index') }}" class="game-btn-ghost">Cancelar</a>
    </div>
</form>
@endsection
