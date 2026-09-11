@extends('layouts.game')

@section('title', $class->name.' — Professor')

@section('content')
<div class="flex flex-wrap items-start justify-between gap-4 mb-6">
    <div>
        <h1 class="font-display text-4xl text-amber-300">{{ $class->name }}</h1>
        <p class="text-amber-100/70">{{ $class->area?->emblemIcon() }} {{ $class->area?->name }} · Média da turma {{ number_format($average, 1) }} · modo {{ $class->score_mode === 'up_from_zero' ? 'sobe do 0' : 'cai do 100' }}</p>
    </div>
    <div class="flex gap-2">
        <a class="game-btn-ghost" href="{{ route('ranking.show', $class) }}">Ver ranking</a>
        <a class="game-btn-ghost" href="{{ route('teacher.shop.show', $class) }}">Loja</a>
        <a class="game-btn-ghost" href="{{ route('teacher.classes.edit', $class) }}">Editar turma</a>
    </div>
</div>

@php
    $allowedTabs = ['visao', 'alunos', 'guildas', 'atividades', 'eventos', 'notas', 'chamada', 'pesos', 'personagens', 'arena'];
    $currentTab = request('tab', old('tab', 'visao'));
    $currentTab = in_array($currentTab, $allowedTabs, true) ? $currentTab : 'visao';
    $editingActivity = $class->activities->firstWhere('id', (int) request('activity'));
    if ($editingActivity) {
        $currentTab = 'atividades';
    }
    $gradableActivities = $class->activities->where('type', '!=', 'event');
    $realmEvents = $class->area_id
        ? \App\Models\GameEvent::query()->with('prizeItem')->where('area_id', $class->area_id)->where('kind', 'realm')->latest()->get()
        : collect();
    $tabLabels = [
        'visao' => 'Visão',
        'alunos' => 'Alunos',
        'guildas' => 'Guildas',
        'atividades' => 'Atividades',
        'eventos' => 'Eventos',
        'notas' => 'Lançar notas',
        'chamada' => 'Chamada',
        'pesos' => 'Pesos',
        'personagens' => 'Personagens',
        'arena' => 'Arena',
    ];
@endphp

<div class="flex flex-wrap gap-2 mb-6">
    @foreach($tabLabels as $key => $label)
        <a class="tab-btn game-btn-ghost" href="{{ route('teacher.classes.show', ['schoolClass' => $class, 'tab' => $key]) }}" data-active="{{ $currentTab === $key ? 'true' : 'false' }}">
            {{ $label }}
            @if($key === 'personagens' && $pendingPersonas->isNotEmpty())
                <span class="ml-1 text-amber-300">{{ $pendingPersonas->count() }}</span>
            @endif
            @if($key === 'arena' && $pendingDuels->isNotEmpty())
                <span class="ml-1 text-cyan-300">{{ $pendingDuels->count() }}</span>
            @endif
        </a>
    @endforeach
</div>

@include('teacher.class-tabs.'.$currentTab)
@endsection
