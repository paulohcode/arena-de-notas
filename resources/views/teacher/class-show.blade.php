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
    $allowedTabs = ['visao', 'alunos', 'guildas', 'atividades', 'notas', 'chamada', 'pesos', 'personagens', 'arena'];
    $currentTab = request('tab', old('tab', 'visao'));
    $currentTab = in_array($currentTab, $allowedTabs, true) ? $currentTab : 'visao';
    $editingActivity = $class->activities->firstWhere('id', (int) request('activity'));
    if ($editingActivity) {
        $currentTab = 'atividades';
    }
@endphp
<div x-data="{ tab: {{ \Illuminate\Support\Js::from($currentTab) }} }">
    <div class="flex flex-wrap gap-2 mb-6">
        @foreach(['visao' => 'Visão', 'alunos' => 'Alunos', 'guildas' => 'Guildas', 'atividades' => 'Atividades', 'notas' => 'Lançar notas', 'chamada' => 'Chamada', 'pesos' => 'Pesos', 'personagens' => 'Personagens', 'arena' => 'Arena'] as $key => $label)
            <button type="button" class="tab-btn game-btn-ghost" :data-active="tab === '{{ $key }}'" @click="tab = '{{ $key }}'">
                {{ $label }}
                @if($key === 'personagens' && $pendingPersonas->isNotEmpty())
                    <span class="ml-1 text-amber-300">{{ $pendingPersonas->count() }}</span>
                @endif
                @if($key === 'arena' && $pendingDuels->isNotEmpty())
                    <span class="ml-1 text-cyan-300">{{ $pendingDuels->count() }}</span>
                @endif
            </button>
        @endforeach
    </div>

    <div x-show="tab === 'visao'" class="space-y-4">
        @if($pendingPersonas->isNotEmpty())
            <button type="button" class="game-card p-4 w-full text-left border-amber-400/40" @click="tab = 'personagens'">
                <p class="text-amber-200 font-semibold">{{ $pendingPersonas->count() }} personagem(ns) aguardando aprovação</p>
                <p class="text-sm text-amber-100/60">Abra a aba Personagens para aprovar avatar e nome de jogo.</p>
            </button>
        @endif
        <div class="game-card p-4 flex flex-wrap items-center justify-between gap-3 {{ $class->isArenaOpen() ? 'border-cyan-400/30' : 'border-purple-900/40' }}">
            <div>
                <p class="font-semibold {{ $class->isArenaOpen() ? 'text-cyan-300' : 'text-amber-100/70' }}">
                    Arena {{ $class->isArenaOpen() ? 'aberta' : 'fechada' }}
                </p>
                <p class="text-sm text-amber-100/55">Duelos RPG e batalhas de guildas geram Glória — não mexem na média nem no XP. Com a arena aberta, cada guilda pode batalhar 1 vez por dia. Alunos também podem desafiar outras turmas do mesmo reino por Aura.</p>
            </div>
            <button type="button" class="game-btn-ghost !py-1 !px-3 text-sm" @click="tab = 'arena'">Gerenciar arena</button>
        </div>
        <a href="{{ route('teacher.shop.show', $class) }}" class="game-card p-4 flex flex-wrap items-center justify-between gap-3 border-amber-400/20">
            <div>
                <p class="font-semibold text-amber-100">Loja de cosméticos</p>
                <p class="text-sm text-amber-100/55">Estoque à venda, quem já comprou e o mercado entre alunos.</p>
            </div>
            <span class="game-btn-ghost !py-1 !px-3 text-sm">Administrar</span>
        </a>
        <div class="grid lg:grid-cols-2 gap-4">
            <div class="game-card p-5">
                <h2 class="font-display text-xl text-amber-200 mb-3">Jogadores</h2>
                @foreach($players as $row)
                    <div class="flex justify-between py-2 border-b border-purple-900/50">
                        <a class="hover:text-amber-300" href="{{ route('teacher.students.show', [$class, $row['student']]) }}">
                            {{ $row['position'] }}º
                            {{ $row['student']->name }}
                            @if($row['student']->arenaName())
                                <span class="text-amber-300"> · {{ $row['student']->arenaName() }}</span>
                            @endif
                            {{ $row['visible'] ? '' : '🙈' }}
                        </a>
                        <span class="text-cyan-300">{{ number_format($row['average'], 1) }}</span>
                    </div>
                @endforeach
            </div>
            <div class="game-card p-5">
                <h2 class="font-display text-xl text-amber-200 mb-3">Guildas</h2>
                @foreach($guilds as $row)
                    <div class="flex justify-between py-2 border-b border-purple-900/50">
                        <span>{{ $row['team']->emblemIcon() }} {{ $row['position'] }}º {{ $row['team']->name }}</span>
                        <span class="text-cyan-300">{{ number_format($row['score'], 1) }}</span>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Card de atenção --}}
        <div class="game-card p-5 border border-rose-500/30">
            <h2 class="font-display text-xl text-rose-300 mb-4">⚠️ Atenção</h2>
            @if(count($lowScorePlayers) === 0 && $ungradedStudents->isEmpty())
                <p class="text-emerald-400">🎉 Tudo em ordem — nenhum aluno precisa de atenção agora.</p>
            @else
                <div class="grid md:grid-cols-2 gap-6">
                    <div>
                        <h3 class="text-sm font-semibold text-rose-200 mb-2 uppercase tracking-wide">Nota baixa (abaixo de {{ $scoreThreshold }})</h3>
                        @if(count($lowScorePlayers) === 0)
                            <p class="text-purple-200/60 text-sm">Nenhum aluno nesta categoria.</p>
                        @else
                            <ul class="space-y-1">
                                @foreach($lowScorePlayers as $row)
                                    <li class="flex items-center justify-between py-1 border-b border-purple-900/40">
                                        <a class="text-left text-rose-200 hover:text-amber-300 transition-colors text-sm"
                                            href="{{ route('teacher.students.show', [$class, $row['student']]) }}">
                                            {{ $row['student']->name }}
                                        </a>
                                        <span class="text-rose-300 font-display">{{ number_format($row['average'], 1) }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                    <div>
                        <h3 class="text-sm font-semibold text-amber-200 mb-2 uppercase tracking-wide">Sem notas lançadas</h3>
                        @if($ungradedStudents->isEmpty())
                            <p class="text-purple-200/60 text-sm">Todos os alunos têm ao menos uma nota.</p>
                        @else
                            <ul class="space-y-1">
                                @foreach($ungradedStudents as $student)
                                    <li class="py-1 border-b border-purple-900/40">
                                        <a class="text-left text-amber-200 hover:text-amber-300 transition-colors text-sm"
                                            href="{{ route('teacher.students.show', [$class, $student]) }}">
                                            {{ $student->name }}
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    </div>

    <div x-show="tab === 'alunos'" x-cloak class="space-y-6">
        <form method="POST" action="{{ route('teacher.students.store', $class) }}" class="game-card p-5 space-y-3">
            @csrf
            <h2 class="font-display text-xl text-amber-200">Novo aluno</h2>
            <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-3 items-end">
                <input class="game-input" name="name" placeholder="Nome" required>
                <input class="game-input" type="email" name="email" placeholder="E-mail" required>
                <button class="game-btn" type="submit">Cadastrar</button>
            </div>
            <p class="text-xs text-purple-200/60">Senha inicial: aluno123</p>
        </form>

        <div>
            <div class="mb-3 flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="font-display text-xl text-amber-200">Turma</h2>
                    <p class="text-xs text-amber-100/50">Comportamento inicia em 100. Use − / + para ajustar rápido.</p>
                </div>
                <a class="game-btn-ghost !px-3 !py-1 text-sm" href="{{ route('teacher.students.export', $class) }}">Exportar PDF</a>
            </div>
            @if($class->students->isEmpty())
                <div class="game-card p-8 text-center text-amber-100/60">Nenhum aluno cadastrado ainda.</div>
            @else
                <section data-student-roster class="space-y-8">
                    @foreach($rosterGroups as $group)
                        <div class="space-y-3">
                            <h3 class="font-display text-lg text-amber-200 flex items-center gap-2">
                                @if($group['emblem'])
                                    <span aria-hidden="true">{{ $group['emblem'] }}</span>
                                @endif
                                <span>{{ $group['title'] }}</span>
                                <span class="text-xs text-amber-100/50 font-sans">{{ $group['students']->count() }}</span>
                            </h3>
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                                @foreach($group['students'] as $student)
                                    @php
                                        $behavior = (float) ($student->pivot->behavior_score ?? 100);
                                        $editingStudent = (int) old('edited_student_id') === (int) $student->id;
                                    @endphp
                                    <div class="game-card p-4 flex flex-col gap-2 min-w-0" x-data="{ editing: {{ $editingStudent ? 'true' : 'false' }} }">
                                        <div class="min-w-0">
                                            <div x-show="!editing">
                                                <a class="font-display text-lg leading-tight text-amber-200 hover:text-amber-300 block" href="{{ route('teacher.students.show', [$class, $student]) }}">{{ $student->name }}</a>
                                                @if($student->arenaName())
                                                    <p class="text-amber-300 text-sm truncate">{{ $student->arenaName() }}</p>
                                                @elseif($student->isPersonaPending())
                                                    <p class="text-amber-100/50 text-xs">Personagem aguardando aprovação</p>
                                                @endif
                                            </div>
                                            <form
                                                x-show="editing"
                                                x-cloak
                                                method="POST"
                                                action="{{ route('teacher.students.update', [$class, $student]) }}"
                                                class="flex flex-col gap-2"
                                            >
                                                @csrf
                                                @method('PUT')
                                                <input type="hidden" name="edited_student_id" value="{{ $student->id }}">
                                                <label>
                                                    <span class="sr-only">Nome do aluno</span>
                                                    <input
                                                        class="game-input"
                                                        name="name"
                                                        value="{{ $editingStudent ? old('name', $student->name) : $student->name }}"
                                                        required
                                                        maxlength="120"
                                                    >
                                                </label>
                                                <label>
                                                    <span class="sr-only">E-mail de acesso</span>
                                                    <input
                                                        class="game-input"
                                                        type="email"
                                                        name="email"
                                                        value="{{ $editingStudent ? old('email', $student->email) : $student->email }}"
                                                        required
                                                        maxlength="180"
                                                    >
                                                </label>
                                                <div class="flex flex-wrap gap-2">
                                                    <button class="game-btn !px-3 !py-1 text-xs" type="submit">Salvar cadastro</button>
                                                    <button class="game-btn-ghost !px-2 !py-1 text-xs" type="button" @click="editing = false">Cancelar</button>
                                                </div>
                                            </form>
                                            <p class="text-amber-100/60 text-sm truncate" x-show="!editing">{{ $student->email }}</p>
                                            <p class="text-amber-100/45 text-xs">Último acesso: {{ $student->lastAccessedLabel() ?? 'Nunca acessou' }}</p>
                                        </div>
                                        <div class="mt-auto flex flex-col gap-2">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <button class="game-btn-ghost !px-2 !py-1 text-xs" type="button" @click="editing = true">Editar cadastro</button>
                                                <a class="game-btn-ghost !px-2 !py-1 text-xs" href="{{ route('teacher.students.show', [$class, $student]) }}">Ficha</a>
                                                @include('partials.reset-student-password')
                                            </div>
                                            <div class="flex flex-wrap items-center gap-1">
                                                <span class="text-xs text-amber-100/50 uppercase tracking-wide">Comp.</span>
                                                <form method="POST" action="{{ route('teacher.grades.behavior', [$class, $student]) }}">
                                                    @csrf
                                                    <input type="hidden" name="delta" value="-5">
                                                    <button class="game-btn-ghost !px-2 !py-1 text-xs" type="submit" title="-5">−5</button>
                                                </form>
                                                <form method="POST" action="{{ route('teacher.grades.behavior', [$class, $student]) }}">
                                                    @csrf
                                                    <input type="hidden" name="delta" value="-1">
                                                    <button class="game-btn-ghost !px-2 !py-1 text-sm" type="submit" title="-1">−</button>
                                                </form>
                                                <span class="font-display text-lg w-10 text-center {{ $behavior < 50 ? 'text-rose-300' : ($behavior < 80 ? 'text-amber-300' : 'text-emerald-300') }}">
                                                    {{ number_format($behavior, 0) }}
                                                </span>
                                                <form method="POST" action="{{ route('teacher.grades.behavior', [$class, $student]) }}">
                                                    @csrf
                                                    <input type="hidden" name="delta" value="1">
                                                    <button class="game-btn-ghost !px-2 !py-1 text-sm" type="submit" title="+1">+</button>
                                                </form>
                                                <form method="POST" action="{{ route('teacher.grades.behavior', [$class, $student]) }}">
                                                    @csrf
                                                    <input type="hidden" name="delta" value="5">
                                                    <button class="game-btn-ghost !px-2 !py-1 text-xs" type="submit" title="+5">+5</button>
                                                </form>
                                            </div>
                                            @if($transferClasses->isNotEmpty())
                                                <form
                                                    method="POST"
                                                    action="{{ route('teacher.students.transfer', [$class, $student]) }}"
                                                    class="flex items-center gap-1"
                                                    onsubmit="return confirm(@js('Transferir '.$student->name."?\n\nEle sai da guilda e perde TODAS as notas, XP, medalhas e o histórico desta turma.\nNa nova turma começa do zero.\n\nEsta ação não pode ser desfeita."))"
                                                >
                                                    @csrf
                                                    <select class="game-select !py-1 !text-xs min-w-0 flex-1" name="target_class_id" required title="Troca apaga notas, XP, medalhas e guilda desta turma">
                                                        <option value="">Trocar de turma</option>
                                                        @foreach($transferClasses as $target)
                                                            <option value="{{ $target->id }}">
                                                                {{ $target->area?->name ? $target->area->name.' · ' : '' }}{{ $target->name }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                    <button class="game-btn-ghost !px-2 !py-1 text-xs" type="submit" title="Apaga progresso desta turma">Mover</button>
                                                </form>
                                            @endif
                                            <form method="POST" action="{{ route('teacher.students.destroy', [$class, $student]) }}">
                                                @csrf @method('DELETE')
                                                <button class="text-rose-300 text-sm" type="submit">Remover</button>
                                            </form>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </section>
            @endif
        </div>
    </div>

    <div x-show="tab === 'guildas'" x-cloak>
        @php
            $availableStudents = $class->students->filter(fn ($student) => ! isset($guildMemberships[$student->id]));
            $takenStudents = $class->students->filter(fn ($student) => isset($guildMemberships[$student->id]));
        @endphp
        <form method="POST" action="{{ route('teacher.teams.store', $class) }}" class="game-card p-5 mb-6 grid md:grid-cols-2 gap-3">
            @csrf
            <h2 class="font-display text-xl text-amber-200 md:col-span-2">Nova guilda</h2>
            <input class="game-input" name="name" placeholder="Nome da guilda" required>
            <input class="game-input" type="color" name="color" value="#7c3aed">
            <select class="game-select" name="emblem">
                @foreach(\App\Models\Team::EMBLEMS as $key => $icon)
                    <option value="{{ $key }}">{{ $icon }} {{ $key }}</option>
                @endforeach
            </select>
            <div class="md:col-span-2 space-y-3">
                <p class="text-xs uppercase tracking-wide text-purple-200/70">Integrantes</p>
                @if($class->students->isEmpty())
                    <p class="text-sm text-amber-100/50">Cadastre alunos antes de montar a guilda.</p>
                @else
                    @if($availableStudents->isNotEmpty())
                        <p class="text-sm text-emerald-300/80">Disponíveis</p>
                        <div class="flex flex-wrap gap-3">
                            @foreach($availableStudents as $student)
                                <label class="text-sm flex items-center gap-2">
                                    <input type="checkbox" name="members[]" value="{{ $student->id }}">
                                    {{ $student->name }}
                                </label>
                            @endforeach
                        </div>
                    @else
                        <p class="text-sm text-amber-100/50">Nenhum aluno disponível. Todos já estão em uma guilda.</p>
                    @endif
                    @if($takenStudents->isNotEmpty())
                        <p class="text-sm text-amber-100/50">Já em outra guilda</p>
                        <div class="flex flex-wrap gap-3">
                            @foreach($takenStudents as $student)
                                <span class="text-sm text-amber-100/40">{{ $student->name }} · {{ $guildMemberships[$student->id] }}</span>
                            @endforeach
                        </div>
                    @endif
                @endif
            </div>
            <button class="game-btn" type="submit">Criar guilda</button>
        </form>

        <div class="grid md:grid-cols-2 gap-4">
            @foreach($class->teams as $team)
                @php
                    $editingMembers = (int) request('edit_team') === (int) $team->id;
                    $teamMembers = $team->members->sortBy('name');
                @endphp
                <div class="space-y-2">
                    <form method="POST" action="{{ route('teacher.teams.update', [$class, $team]) }}" class="game-card p-5 space-y-3">
                        @csrf @method('PUT')
                        <div class="flex items-center gap-2">
                            <span class="text-3xl">{{ $team->emblemIcon() }}</span>
                            <input class="game-input" name="name" value="{{ $team->name }}">
                        </div>
                        <input class="game-input" type="color" name="color" value="{{ $team->color }}">
                        <select class="game-select" name="emblem">
                            @foreach(\App\Models\Team::EMBLEMS as $key => $icon)
                                <option value="{{ $key }}" @selected($team->emblem === $key)>{{ $icon }} {{ $key }}</option>
                            @endforeach
                        </select>
                        <div class="space-y-2">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <p class="text-xs uppercase tracking-wide text-purple-200/70">Integrantes ({{ $teamMembers->count() }})</p>
                                @if($editingMembers)
                                    <a class="game-btn-ghost !px-2 !py-1 text-xs" href="{{ route('teacher.classes.show', ['schoolClass' => $class, 'tab' => 'guildas']) }}">Cancelar</a>
                                @else
                                    <a class="game-btn-ghost !px-2 !py-1 text-xs" href="{{ route('teacher.classes.show', ['schoolClass' => $class, 'tab' => 'guildas', 'edit_team' => $team->id]) }}">Editar integrantes</a>
                                @endif
                            </div>
                            @if($editingMembers)
                                <div class="flex flex-wrap gap-3">
                                    @foreach($teamMembers as $member)
                                        <label class="text-sm flex items-center gap-2">
                                            <input type="checkbox" name="members[]" value="{{ $member->id }}" checked>
                                            {{ $member->name }}
                                        </label>
                                    @endforeach
                                    @foreach($availableStudents as $student)
                                        <label class="text-sm flex items-center gap-2">
                                            <input type="checkbox" name="members[]" value="{{ $student->id }}">
                                            {{ $student->name }}
                                        </label>
                                    @endforeach
                                </div>
                                @if($teamMembers->isEmpty() && $availableStudents->isEmpty())
                                    <p class="text-sm text-amber-100/50">Nenhum aluno disponível.</p>
                                @endif
                            @else
                                @forelse($teamMembers as $member)
                                    <p class="text-sm text-amber-100/80">{{ $member->name }}</p>
                                    <input type="hidden" name="members[]" value="{{ $member->id }}">
                                @empty
                                    <p class="text-sm text-amber-100/50">Nenhum integrante.</p>
                                @endforelse
                            @endif
                        </div>
                        <div class="flex gap-2">
                            <button class="game-btn" type="submit">Salvar</button>
                        </div>
                    </form>
                    <form method="POST" action="{{ route('teacher.teams.destroy', [$class, $team]) }}">
                        @csrf @method('DELETE')
                        <button class="text-rose-300 text-sm" type="submit">Excluir {{ $team->name }}</button>
                    </form>
                </div>
            @endforeach
        </div>
    </div>

    <div x-show="tab === 'atividades'" x-cloak class="space-y-6">
        <form method="POST"
              action="{{ $editingActivity
                  ? route('teacher.activities.update', [$class, $editingActivity])
                  : route('teacher.activities.store', $class) }}"
              class="game-card p-5 space-y-4">
            @csrf
            @if($editingActivity)
                @method('PUT')
            @endif

            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="font-display text-xl text-amber-200">
                    {{ $editingActivity ? 'Editar atividade' : 'Nova atividade' }}
                </h2>
                @if($editingActivity)
                    <a class="game-btn-ghost text-sm" href="{{ route('teacher.classes.show', ['schoolClass' => $class, 'tab' => 'atividades']) }}">Cancelar</a>
                @endif
            </div>

            <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-3">
                <label class="space-y-1 sm:col-span-2">
                    <span class="text-xs uppercase tracking-wide text-purple-200/70">Nome</span>
                    <input class="game-input" name="name" value="{{ old('name', $editingActivity->name ?? '') }}" placeholder="Ex: Prova 1" required maxlength="120">
                </label>
                <label class="space-y-1">
                    <span class="text-xs uppercase tracking-wide text-purple-200/70">Tipo</span>
                    <select class="game-select" name="type">
                        <option value="individual" @selected(old('type', $editingActivity->type ?? 'individual') === 'individual')>Individual</option>
                        <option value="team" @selected(old('type', $editingActivity->type ?? 'individual') === 'team')>Equipe / guilda</option>
                    </select>
                </label>
                <div class="grid grid-cols-2 gap-3">
                    <label class="space-y-1">
                        <span class="text-xs uppercase tracking-wide text-purple-200/70">Nota máx.</span>
                        <input class="game-input" type="number" name="max_score" value="{{ old('max_score', $editingActivity->max_score ?? 100) }}" min="1" max="100" required>
                    </label>
                    <label class="space-y-1">
                        <span class="text-xs uppercase tracking-wide text-purple-200/70">Peso</span>
                        <input class="game-input" type="number" name="weight" value="{{ old('weight', $editingActivity->weight ?? 1) }}" min="1" max="10" required>
                    </label>
                </div>
            </div>

            <button class="game-btn" type="submit">
                {{ $editingActivity ? 'Salvar alterações' : 'Cadastrar atividade' }}
            </button>
        </form>

        <div class="game-card p-5">
            <h2 class="font-display text-xl text-amber-200 mb-4">Atividades cadastradas</h2>
            @if($class->activities->isEmpty())
                <p class="text-purple-200/60 text-sm">Nenhuma atividade cadastrada ainda.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-amber-200/80 border-b border-purple-900/50">
                                <th class="py-2 pr-3 font-medium">Nome</th>
                                <th class="py-2 pr-3 font-medium">Tipo</th>
                                <th class="py-2 pr-3 font-medium">Nota máx.</th>
                                <th class="py-2 pr-3 font-medium">Peso</th>
                                <th class="py-2 font-medium text-right">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($class->activities as $activity)
                                <tr class="border-b border-purple-900/40 last:border-0 {{ $editingActivity?->is($activity) ? 'bg-amber-500/10' : '' }}">
                                    <td class="py-3 pr-3">{{ $activity->name }}</td>
                                    <td class="py-3 pr-3 text-purple-200/80">{{ $activity->type === 'team' ? 'Guilda' : 'Individual' }}</td>
                                    <td class="py-3 pr-3">{{ $activity->max_score }}</td>
                                    <td class="py-3 pr-3">{{ $activity->weight }}</td>
                                    <td class="py-3">
                                        <div class="flex justify-end flex-wrap gap-2">
                                            <form method="POST" action="{{ route('teacher.activities.warn', [$class, $activity]) }}" onsubmit="return confirm('Avisar os alunos que ainda não têm nota nesta atividade?')">
                                                @csrf
                                                <button class="game-btn-ghost px-3 py-1 text-xs" type="submit">Avisar pendentes</button>
                                            </form>
                                            <a class="game-btn-ghost px-3 py-1 text-xs"
                                               href="{{ route('teacher.classes.show', ['schoolClass' => $class, 'tab' => 'atividades', 'activity' => $activity->id]) }}">Editar</a>
                                            <form method="POST" action="{{ route('teacher.activities.destroy', [$class, $activity]) }}" onsubmit="return confirm('Excluir esta atividade?')">
                                                @csrf
                                                @method('DELETE')
                                                <button class="px-3 py-1 text-xs text-rose-300 hover:text-rose-200" type="submit">Excluir</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    <div
        x-show="tab === 'notas'"
        x-cloak
        class="space-y-6"
        x-data="{
            activityId: '{{ old('activity_id', $class->activities->first()?->id) }}',
            target: 'student'
        }"
    >
        <form method="POST" action="{{ route('teacher.grades.store', $class) }}" class="game-card p-5 space-y-4">
            @csrf
            <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-3">
                <div>
                    <h2 class="font-display text-xl text-amber-200">Nota de atividade</h2>
                    <p class="text-sm text-purple-200/70 mt-1">Escolha a atividade e lance as notas de todos de uma vez.</p>
                </div>
                <div class="sm:w-80">
                    <label class="block text-xs uppercase tracking-wide text-purple-300/70 mb-1">Atividade</label>
                    <select class="game-select" name="activity_id" x-model="activityId" required>
                        @forelse($class->activities as $activity)
                            <option value="{{ $activity->id }}">
                                {{ $activity->name }}
                                ({{ $activity->type === 'team' ? 'guilda' : 'individual' }} · máx {{ $activity->max_score }})
                            </option>
                        @empty
                            <option value="" disabled selected>Cadastre uma atividade primeiro</option>
                        @endforelse
                    </select>
                </div>
            </div>

            @error('scores')
                <p class="text-sm text-rose-300">{{ $message }}</p>
            @enderror

            @foreach($class->activities as $activity)
                <div
                    x-show="String(activityId) === '{{ $activity->id }}'"
                    x-cloak
                    class="space-y-2"
                >
                    <div class="flex items-center justify-between text-xs uppercase tracking-wide text-purple-300/60 pb-1 border-b border-purple-500/20">
                        <span>{{ $activity->type === 'team' ? 'Guilda' : 'Aluno' }}</span>
                        <span>Nota (0–{{ $activity->max_score }})</span>
                    </div>

                    @if($activity->isTeam())
                        @forelse($class->teams as $team)
                            <label class="flex items-center gap-3 py-1.5">
                                <span class="min-w-0 flex-1 truncate text-purple-100">{{ $team->emblemIcon() }} {{ $team->name }}</span>
                                <input
                                    class="game-input !w-20 shrink-0 !py-1 !px-2 text-right tabular-nums"
                                    type="number"
                                    step="0.1"
                                    min="0"
                                    max="{{ $activity->max_score }}"
                                    name="scores[{{ $team->id }}]"
                                    value="{{ old('scores.'.$team->id, $teamScoresByActivity[$activity->id][$team->id] ?? '') }}"
                                    placeholder="—"
                                    x-bind:disabled="String(activityId) !== '{{ $activity->id }}'"
                                >
                            </label>
                        @empty
                            <p class="text-sm text-purple-300/70 py-2">Nenhuma guilda cadastrada nesta turma.</p>
                        @endforelse
                    @else
                        @forelse($class->students->sortBy('name') as $student)
                            <label class="flex items-center gap-3 py-1.5">
                                <span class="min-w-0 flex-1 truncate text-purple-100">{{ $student->name }}</span>
                                <input
                                    class="game-input !w-20 shrink-0 !py-1 !px-2 text-right tabular-nums"
                                    type="number"
                                    step="0.1"
                                    min="0"
                                    max="{{ $activity->max_score }}"
                                    name="scores[{{ $student->id }}]"
                                    value="{{ old('scores.'.$student->id, $studentScoresByActivity[$activity->id][$student->id] ?? '') }}"
                                    placeholder="—"
                                    x-bind:disabled="String(activityId) !== '{{ $activity->id }}'"
                                >
                            </label>
                        @empty
                            <p class="text-sm text-purple-300/70 py-2">Nenhum aluno matriculado nesta turma.</p>
                        @endforelse
                    @endif
                </div>
            @endforeach

            @if($class->activities->isNotEmpty())
                <button class="game-btn" type="submit">Salvar notas</button>
            @endif
        </form>

        <form method="POST" action="{{ route('teacher.grades.adjust', $class) }}" class="game-card p-5 space-y-3 max-w-xl">
            @csrf
            <h2 class="font-display text-xl text-amber-200">Ajuste +/−</h2>
            <select class="game-select" name="target_type" x-model="target">
                <option value="student">Aluno</option>
                <option value="team">Guilda</option>
            </select>
            <div x-show="target === 'student'">
                <select class="game-select" name="student_id">
                    @foreach($class->students as $student)
                        <option value="{{ $student->id }}">{{ $student->name }}</option>
                    @endforeach
                </select>
            </div>
            <div x-show="target === 'team'" x-cloak>
                <select class="game-select" name="team_id">
                    @foreach($class->teams as $team)
                        <option value="{{ $team->id }}">{{ $team->name }}</option>
                    @endforeach
                </select>
            </div>
            <input class="game-input" type="number" step="0.1" min="-100" max="100" name="delta" placeholder="Ex: -20 ou 30" required>
            <input class="game-input" name="reason" placeholder="Motivo (conduta, extra...)" required>
            <button class="game-btn" type="submit">Lançar ajuste</button>
        </form>
    </div>

    <div x-show="tab === 'chamada'" x-cloak class="space-y-6">
        <div class="game-card p-5 space-y-4">
            <div>
                <h2 class="font-display text-xl text-amber-200">Chamada</h2>
                <p class="text-sm text-amber-100/60 mt-1">
                    Escolha a data, gere a lista e marque Presente, Ausente ou Falta justificada.
                    Só o Presente gera Selos para a loja. Justificada conta na nota de frequência.
                </p>
            </div>
            <form method="POST" action="{{ route('teacher.attendance.store', $class) }}" class="flex flex-wrap items-end gap-3">
                @csrf
                <label class="space-y-1">
                    <span class="text-xs uppercase tracking-wide text-purple-200/70">Data</span>
                    <input class="game-input" type="date" name="held_on" value="{{ old('held_on', now()->toDateString()) }}" required>
                </label>
                <button class="game-btn" type="submit">Gerar chamada</button>
            </form>
        </div>

        @if($attendanceSessions->isNotEmpty())
            <div class="game-card p-5">
                <h3 class="font-display text-lg text-amber-200 mb-3">Histórico</h3>
                <div class="flex flex-wrap gap-2">
                    @foreach($attendanceSessions as $session)
                        <a
                            class="game-btn-ghost !py-1 !px-3 text-sm {{ $activeAttendanceSession && $activeAttendanceSession->id === $session->id ? 'border-amber-400/50' : '' }}"
                            href="{{ route('teacher.classes.show', ['schoolClass' => $class, 'tab' => 'chamada', 'session' => $session->id]) }}"
                        >
                            {{ $session->held_on->format('d/m/Y') }}
                            @if($session->records->whereNotNull('status')->isEmpty())
                                <span class="text-amber-100/40">· rascunho</span>
                            @endif
                        </a>
                    @endforeach
                </div>
            </div>
        @endif

        @if($activeAttendanceSession)
            @php
                $sessionStudents = $class->students->sortBy('name')->values();
                $recordsByStudent = $activeAttendanceSession->records->keyBy('student_id');
            @endphp
            <form
                method="POST"
                action="{{ route('teacher.attendance.update', [$class, $activeAttendanceSession]) }}"
                class="game-card p-5 space-y-4"
                data-attendance-form
            >
                @csrf
                @method('PUT')
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h3 class="font-display text-lg text-amber-200">{{ $activeAttendanceSession->held_on->format('d/m/Y') }}</h3>
                        <p class="text-xs text-amber-100/50">Todos começam como presentes. Desligue quem faltou e salve para atualizar a média e os Selos.</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button type="button" class="game-btn-ghost !py-1 !px-3 text-xs" data-attendance-mark-all="present">Todos presentes</button>
                        <button type="button" class="game-btn-ghost !py-1 !px-3 text-xs" data-attendance-mark-all="absent">Todos ausentes</button>
                    </div>
                </div>

                <div class="space-y-3">
                    @foreach($sessionStudents as $student)
                        @php
                            $savedStatus = old('statuses.'.$student->id, $recordsByStudent->get($student->id)?->status);
                            $currentStatus = in_array($savedStatus, ['present', 'absent', 'justified'], true)
                                ? $savedStatus
                                : 'present';
                            $isPresent = $currentStatus === 'present';
                            $isJustified = $currentStatus === 'justified';
                        @endphp
                        <div class="flex flex-wrap items-center justify-between gap-3 py-3 border-b border-purple-900/40" data-attendance-row>
                            <div class="min-w-0">
                                <p class="font-semibold truncate">{{ $student->name }}</p>
                                @if($student->arenaName())
                                    <p class="text-xs text-amber-100/45">{{ $student->arenaName() }}</p>
                                @endif
                            </div>
                            <div class="attendance-controls">
                                <input type="hidden" name="statuses[{{ $student->id }}]" value="{{ $currentStatus }}" data-attendance-value>
                                <label class="attendance-toggle">
                                    <span class="attendance-toggle__label attendance-toggle__label--off">Ausente</span>
                                    <span class="attendance-toggle__switch">
                                        <input
                                            type="checkbox"
                                            role="switch"
                                            data-attendance-present
                                            @checked($isPresent)
                                            aria-label="Presença de {{ $student->name }}"
                                        >
                                        <span class="attendance-toggle__track"></span>
                                    </span>
                                    <span class="attendance-toggle__label attendance-toggle__label--on">Presente</span>
                                </label>
                                <label class="attendance-justified">
                                    <input type="checkbox" data-attendance-justified @checked($isJustified) @disabled($isPresent)>
                                    <span class="attendance-justified__chip">Justificada</span>
                                </label>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="flex flex-wrap items-center gap-3 pt-2">
                    <button class="game-btn" type="submit">Salvar chamada</button>
                </div>
            </form>

            <form method="POST" action="{{ route('teacher.attendance.destroy', [$class, $activeAttendanceSession]) }}" onsubmit="return confirm('Remover a chamada deste dia? Os Selos de presença serão revertidos.')">
                @csrf
                @method('DELETE')
                <button class="game-btn-ghost !py-1 !px-3 text-sm text-rose-300" type="submit">Excluir esta chamada</button>
            </form>
        @elseif($attendanceSessions->isEmpty())
            <div class="game-card p-5 text-sm text-amber-100/55">
                Nenhuma chamada ainda. Escolha a data e clique em Gerar chamada.
            </div>
        @endif
    </div>

    <div x-show="tab === 'pesos'" x-cloak>
        <form method="POST" action="{{ route('teacher.activities.weights', $class) }}" class="game-card p-5 space-y-3">
            @csrf @method('PUT')
            @foreach($class->activities as $activity)
                <label class="flex items-center justify-between gap-4">
                    <span>{{ $activity->name }} <span class="text-purple-300/60">({{ $activity->type === 'team' ? 'fecha a nota da guilda' : 'individual' }})</span></span>
                    <input class="game-input w-24" type="number" name="weights[{{ $activity->id }}]" value="{{ $activity->weight }}" min="1" max="10">
                </label>
            @endforeach
            <label class="flex items-center justify-between gap-4">
                <span>Peso da linha “Nota equipe” no aluno</span>
                <input class="game-input w-24" type="number" name="team_grade_weight" value="{{ $class->team_grade_weight }}" min="1" max="10">
            </label>
            <label class="flex items-center justify-between gap-4">
                <span>Peso da nota “Comportamento”</span>
                <input class="game-input w-24" type="number" name="behavior_grade_weight" value="{{ $class->behavior_grade_weight ?? 1 }}" min="1" max="10">
            </label>
            <label class="flex items-center justify-between gap-4">
                <span>Peso da nota “Frequência”</span>
                <input class="game-input w-24" type="number" name="attendance_grade_weight" value="{{ $class->attendance_grade_weight ?? 1 }}" min="1" max="10">
            </label>
            <button class="game-btn" type="submit">Salvar pesos</button>
        </form>
    </div>

    <div x-show="tab === 'personagens'" x-cloak class="space-y-4">
        <div class="game-card p-5">
            <h2 class="font-display text-xl text-amber-200 mb-2">Pedidos de personagem</h2>
            <p class="text-sm text-amber-100/60 mb-4">O nome verdadeiro continua visível. O nome de jogo e o avatar só entram na arena depois da sua aprovação.</p>

            @forelse($pendingPersonas as $student)
                <div class="flex flex-wrap items-center justify-between gap-4 py-4 border-b border-purple-900/40">
                    <div class="flex items-center gap-3 min-w-0">
                        @include('partials.player-avatar', ['student' => $student, 'avatarKey' => $student->pending_character_avatar, 'size' => 'md'])
                        <div class="min-w-0">
                            <p class="font-semibold truncate">{{ $student->name }}</p>
                            <p class="text-amber-300">{{ $student->pending_character_name }}</p>
                            <p class="text-xs text-amber-100/50">{{ $student->characterClassLabel() }} · {{ $student->avatarMeta($student->pending_character_avatar)['name'] ?? 'Avatar' }}</p>
                        </div>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <form method="POST" action="{{ route('teacher.characters.approve', [$class, $student]) }}">
                            @csrf
                            <button class="game-btn !py-1 !px-3 text-sm" type="submit">Aprovar</button>
                        </form>
                        <form method="POST" action="{{ route('teacher.characters.reject', [$class, $student]) }}" class="flex gap-2">
                            @csrf
                            <input class="game-input !py-1 !w-40" name="reason" placeholder="Motivo (opcional)" maxlength="200">
                            <button class="game-btn-ghost !py-1 !px-3 text-sm" type="submit">Recusar</button>
                        </form>
                    </div>
                </div>
            @empty
                <p class="text-purple-200/60 text-sm">Nenhum pedido pendente.</p>
            @endforelse
        </div>
    </div>

    <div x-show="tab === 'arena'" x-cloak class="space-y-4">
        <div class="game-card p-5 flex flex-wrap items-center justify-between gap-4">
            <div>
                <h2 class="font-display text-xl text-amber-200">Arena de batalha</h2>
                <p class="text-sm text-amber-100/60 mt-1">
                    Status:
                    <span class="{{ $class->isArenaOpen() ? 'text-emerald-300' : 'text-rose-300' }}">
                        {{ $class->isArenaOpen() ? 'aberta' : 'fechada' }}
                    </span>
                    · Vitória +{{ \App\Models\Duel::GLORY_WIN }} Glória · Derrota +{{ \App\Models\Duel::GLORY_LOSS }}
                    · Espera {{ $class->arenaCooldownLabel() }}
                    · Limite {{ $class->arenaDailyLimit() }}/dia
                </p>
                <p class="text-sm text-cyan-200/70 mt-2">
                    Batalhas de guildas: com a arena aberta, qualquer membro pode desafiar outra guilda.
                    Cada guilda resolve no máximo <strong>1 batalha por dia</strong>. Quem lutou ganha Glória/Relíquias; a média e o XP não mudam.
                    Com a arena aberta, alunos também podem desafiar outras turmas do mesmo reino por <strong>Aura</strong>.
                </p>
            </div>
            @if($class->isArenaOpen())
                <form method="POST" action="{{ route('teacher.arena.close', $class) }}">
                    @csrf
                    <button class="game-btn-ghost" type="submit">Fechar arena</button>
                </form>
            @else
                <form method="POST" action="{{ route('teacher.arena.open', $class) }}">
                    @csrf
                    <button class="game-btn" type="submit">Abrir arena</button>
                </form>
            @endif
        </div>

        <form method="POST" action="{{ route('teacher.arena.update', $class) }}" class="game-card p-5 space-y-4">
            @csrf
            @method('PUT')
            <input type="hidden" name="tab" value="arena">
            <div>
                <h3 class="font-display text-lg text-amber-200">Configurações da arena</h3>
                <p class="text-sm text-amber-100/60 mt-1">Defina se a arena está aberta, o intervalo entre desafios e o limite diário de batalhas.</p>
            </div>
            <div class="grid md:grid-cols-3 gap-4">
                <label class="block">
                    <span class="text-sm">Estado</span>
                    <select class="game-select mt-1 w-full" name="arena_open" required>
                        <option value="1" @selected((string) old('arena_open', $class->isArenaOpen() ? '1' : '0') === '1')>Aberta</option>
                        <option value="0" @selected((string) old('arena_open', $class->isArenaOpen() ? '1' : '0') === '0')>Fechada</option>
                    </select>
                </label>
                <label class="block">
                    <span class="text-sm">Espera entre batalhas (minutos)</span>
                    <input class="game-input mt-1 w-full" type="number" name="arena_cooldown_minutes" min="0" max="10080" required
                        value="{{ old('arena_cooldown_minutes', $class->arenaCooldownMinutes()) }}">
                    <span class="block text-xs text-amber-100/50 mt-1">0 = sem espera. Ex.: 30 para meia hora, 120 para 2 horas.</span>
                </label>
                <label class="block">
                    <span class="text-sm">Batalhas permitidas no dia</span>
                    <input class="game-input mt-1 w-full" type="number" name="arena_daily_limit" min="1" max="50" required
                        value="{{ old('arena_daily_limit', $class->arenaDailyLimit()) }}">
                    <span class="block text-xs text-amber-100/50 mt-1">Cada aluno pode concluir no máximo esse número de duelos por dia.</span>
                </label>
            </div>
            <button class="game-btn" type="submit">Salvar configurações</button>
        </form>

        <div class="grid lg:grid-cols-2 gap-4">
            <div class="game-card p-5">
                <h3 class="font-display text-lg text-amber-200 mb-3">Hall da Arena</h3>
                @forelse($arenaHall as $row)
                    <div class="flex justify-between py-2 border-b border-purple-900/40 text-sm">
                        <span>
                            {{ $row['position'] }}º
                            <a class="hover:text-amber-300" href="{{ route('teacher.students.show', [$class, $row['student']]) }}">{{ $row['student']->name }}</a>
                            @if($row['student']->arenaName())
                                <span class="text-amber-300"> · {{ $row['student']->arenaName() }}</span>
                            @endif
                        </span>
                        <span class="text-cyan-300">{{ $row['glory'] }} · {{ $row['arena_wins'] }}V–{{ $row['arena_losses'] }}D</span>
                    </div>
                @empty
                    <p class="text-sm text-purple-200/60">Nenhum duelo resolvido ainda.</p>
                @endforelse
            </div>

            <div class="game-card p-5">
                <h3 class="font-display text-lg text-amber-200 mb-3">Desafios pendentes</h3>
                @forelse($pendingDuels as $duel)
                    <p class="text-sm py-2 border-b border-purple-900/40">
                        {{ $duel->challenger->name }}
                        @if($duel->challenger->arenaName()) <span class="text-amber-300">· {{ $duel->challenger->arenaName() }}</span> @endif
                        →
                        {{ $duel->opponent->name }}
                        @if($duel->opponent->arenaName()) <span class="text-amber-300">· {{ $duel->opponent->arenaName() }}</span> @endif
                    </p>
                @empty
                    <p class="text-sm text-purple-200/60">Nenhum desafio aguardando aceite.</p>
                @endforelse
            </div>
        </div>

        <div class="game-card p-5">
            <h3 class="font-display text-lg text-amber-200 mb-3">Histórico recente</h3>
            @forelse($recentDuels as $duel)
                <div class="flex flex-wrap justify-between gap-2 py-2 border-b border-purple-900/40 text-sm">
                    <span>
                        {{ $duel->challenger->arenaName() ?: $duel->challenger->name }}
                        vs
                        {{ $duel->opponent->arenaName() ?: $duel->opponent->name }}
                    </span>
                    <span class="text-emerald-300">
                        Venceu: {{ $duel->winner?->arenaName() ?: $duel->winner?->name }}
                        · +{{ $duel->glory_winner }}/+{{ $duel->glory_loser }} Glória
                    </span>
                </div>
            @empty
                <p class="text-sm text-purple-200/60">Sem duelos resolvidos.</p>
            @endforelse
        </div>

        @include('partials.combat-rules', ['detailed' => true])
    </div>
</div>
@endsection
