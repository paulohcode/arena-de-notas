@extends('layouts.game')

@section('title', 'Relatório diário — Admin')

@section('content')
@php
    $summary = $report['summary'];
    $attendance = $report['attendance'];
    $battles = $report['battles'];
@endphp

<div class="flex flex-wrap items-center justify-between gap-4 mb-8 reveal">
    <div>
        <a href="{{ route('admin.dashboard') }}" class="game-btn-ghost text-sm mb-2 inline-block">← Admin</a>
        <p class="hero-kicker !mb-1">Cronista da arena</p>
        <h1 class="font-display text-4xl text-amber-300">Relatório diário</h1>
        <p class="text-amber-100/65 mt-1 max-w-2xl">
            Acontecimentos de {{ $report['day_label'] }}
            @if($report['area'])
                em {{ $report['area']->name }}
            @else
                em todos os reinos
            @endif.
        </p>
    </div>
    <a class="game-btn-ghost" href="{{ route('admin.reports.exchange', ['date' => $selectedDate, 'area' => $selectedAreaId]) }}">Relatório do câmbio</a>
</div>

<form method="GET" action="{{ route('admin.reports.daily') }}" class="game-card p-5 mb-8 reveal flex flex-wrap items-end gap-4">
    <label class="block">
        <span class="text-sm text-amber-100/70">Data</span>
        <input class="game-input mt-1 block" type="date" name="date" value="{{ $selectedDate }}" required>
    </label>
    <label class="block min-w-[12rem]">
        <span class="text-sm text-amber-100/70">Reino</span>
        <select class="game-input mt-1 block w-full" name="area">
            <option value="">Todos os reinos</option>
            @foreach($areas as $areaOption)
                <option value="{{ $areaOption->id }}" @selected((int) $selectedAreaId === (int) $areaOption->id)>
                    {{ $areaOption->name }}
                </option>
            @endforeach
        </select>
    </label>
    <button class="game-btn" type="submit">Filtrar</button>
</form>

<div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-3 mb-8">
    <a href="#chamada" class="game-card p-4 block reveal">
        <p class="text-xs uppercase tracking-[0.2em] text-orange-300/80">Chamada</p>
        <p class="font-display text-2xl text-amber-200 mt-1">{{ $summary['sessions'] }}</p>
        <p class="text-sm text-amber-100/55">{{ $summary['absent'] }} falta(s) · {{ $summary['present'] }} presente(s)</p>
    </a>
    <a href="#batalhas" class="game-card p-4 block reveal">
        <p class="text-xs uppercase tracking-[0.2em] text-orange-300/80">Batalhas</p>
        <p class="font-display text-2xl text-amber-200 mt-1">{{ $summary['battles'] }}</p>
        <p class="text-sm text-amber-100/55">Duelos, guildas, reino e vigílias</p>
    </a>
    <a href="#notas" class="game-card p-4 block reveal">
        <p class="text-xs uppercase tracking-[0.2em] text-orange-300/80">Notas</p>
        <p class="font-display text-2xl text-amber-200 mt-1">{{ $summary['grades'] }}</p>
        <p class="text-sm text-amber-100/55">Atividades e ajustes</p>
    </a>
    <a href="#itens" class="game-card p-4 block reveal">
        <p class="text-xs uppercase tracking-[0.2em] text-orange-300/80">Itens</p>
        <p class="font-display text-2xl text-amber-200 mt-1">{{ $summary['items'] }}</p>
        <p class="text-sm text-amber-100/55">Compras e prêmios</p>
    </a>
    <a href="#eventos" class="game-card p-4 block reveal">
        <p class="text-xs uppercase tracking-[0.2em] text-orange-300/80">Eventos</p>
        <p class="font-display text-2xl text-amber-200 mt-1">{{ $summary['event_attempts'] }}</p>
        <p class="text-sm text-amber-100/55">Tentativas concluídas</p>
    </a>
    <a href="#badges" class="game-card p-4 block reveal">
        <p class="text-xs uppercase tracking-[0.2em] text-orange-300/80">Badges</p>
        <p class="font-display text-2xl text-amber-200 mt-1">{{ $summary['badges'] }}</p>
        <p class="text-sm text-amber-100/55">Conquistas do dia</p>
    </a>
    <a href="#ativos" class="game-card p-4 block reveal sm:col-span-2 lg:col-span-2">
        <p class="text-xs uppercase tracking-[0.2em] text-orange-300/80">Alunos ativos</p>
        <p class="font-display text-2xl text-amber-200 mt-1">{{ $summary['active_students'] }}</p>
        <p class="text-sm text-amber-100/55">Entraram no sistema neste dia</p>
    </a>
</div>

<section id="chamada" class="game-card p-5 mb-6 reveal scroll-mt-24">
    <h2 class="font-display text-2xl text-amber-200 mb-4">Chamada e faltantes</h2>

    @if($attendance['absentees'] !== [])
        <div class="mb-5 rounded-lg border border-rose-400/30 bg-rose-950/30 p-4">
            <h3 class="text-rose-200 font-semibold mb-2">Faltantes</h3>
            <ul class="space-y-1 text-sm">
                @foreach($attendance['absentees'] as $row)
                    <li>
                        {{ $row['student_name'] }} · {{ $row['class_name'] }} · {{ $row['area_name'] }}
                        · {{ $row['status_label'] }}
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    @if($attendance['sessions'] === [])
        <p class="text-amber-100/60">Nenhuma chamada registrada neste dia.</p>
    @else
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead class="text-amber-100/50 uppercase tracking-wide text-xs">
                    <tr>
                        <th class="py-2 pr-3">Turma</th>
                        <th class="py-2 pr-3">Reino</th>
                        <th class="py-2 pr-3">Presentes</th>
                        <th class="py-2 pr-3">Faltas</th>
                        <th class="py-2 pr-3">Justificadas</th>
                        <th class="py-2">Sem status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($attendance['sessions'] as $session)
                        <tr class="border-t border-amber-500/10">
                            <td class="py-2 pr-3">{{ $session['class_name'] }}</td>
                            <td class="py-2 pr-3">{{ $session['area_name'] }}</td>
                            <td class="py-2 pr-3">{{ $session['present'] }}</td>
                            <td class="py-2 pr-3 text-rose-300">{{ $session['absent'] }}</td>
                            <td class="py-2 pr-3">{{ $session['justified'] }}</td>
                            <td class="py-2">{{ $session['unmarked'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @if($attendance['classes_without_session'] !== [])
        <div class="mt-5 rounded-lg border border-amber-400/20 bg-amber-950/20 p-4">
            <h3 class="text-amber-200 font-semibold mb-2">Turmas sem chamada</h3>
            <ul class="space-y-1 text-sm text-amber-100/70">
                @foreach($attendance['classes_without_session'] as $row)
                    <li>{{ $row['class_name'] }} · {{ $row['area_name'] }}</li>
                @endforeach
            </ul>
        </div>
    @endif
</section>

<section id="batalhas" class="game-card p-5 mb-6 reveal scroll-mt-24">
    <h2 class="font-display text-2xl text-amber-200 mb-4">Batalhas realizadas</h2>

    @if($summary['battles'] === 0 && $battles['rites'] === [])
        <p class="text-amber-100/60">Nenhuma batalha resolvida neste dia.</p>
    @endif

    @if($battles['duels'] !== [])
        <h3 class="text-amber-100/80 font-semibold mt-2 mb-2">Duelos 1v1</h3>
        <ul class="space-y-2 text-sm mb-4">
            @foreach($battles['duels'] as $duel)
                <li class="border-b border-amber-500/10 pb-2">
                    {{ $duel['challenger'] }} vs {{ $duel['opponent'] }}
                    · {{ $duel['class_name'] }} ({{ $duel['area_name'] }})
                    · venceu {{ $duel['winner'] ?? '—' }}
                    · glória {{ $duel['glory_winner'] }}/{{ $duel['glory_loser'] }}
                </li>
            @endforeach
        </ul>
    @endif

    @if($battles['team_battles'] !== [])
        <h3 class="text-amber-100/80 font-semibold mt-2 mb-2">Guerras de guilda</h3>
        <ul class="space-y-2 text-sm mb-4">
            @foreach($battles['team_battles'] as $battle)
                <li class="border-b border-amber-500/10 pb-2">
                    {{ $battle['challenger_team'] }} vs {{ $battle['opponent_team'] }}
                    · {{ $battle['class_name'] }} ({{ $battle['area_name'] }})
                    · venceu {{ $battle['winner_team'] ?? '—' }}
                </li>
            @endforeach
        </ul>
    @endif

    @if($battles['realm_duels'] !== [])
        <h3 class="text-amber-100/80 font-semibold mt-2 mb-2">Duelos de reino</h3>
        <ul class="space-y-2 text-sm mb-4">
            @foreach($battles['realm_duels'] as $duel)
                <li class="border-b border-amber-500/10 pb-2">
                    {{ $duel['challenger'] }} ({{ $duel['challenger_class'] }}) vs {{ $duel['opponent'] }} ({{ $duel['opponent_class'] }})
                    · {{ $duel['area_name'] }}
                    · venceu {{ $duel['winner'] ?? '—' }}
                    · aura {{ $duel['aura_winner'] }}/{{ $duel['aura_loser'] }}
                </li>
            @endforeach
        </ul>
    @endif

    @if($battles['vigils'] !== [])
        <h3 class="text-amber-100/80 font-semibold mt-2 mb-2">Vigílias</h3>
        <ul class="space-y-2 text-sm mb-4">
            @foreach($battles['vigils'] as $vigil)
                <li class="border-b border-amber-500/10 pb-2">
                    {{ $vigil['student_name'] }} · {{ $vigil['class_name'] }} ({{ $vigil['area_name'] }})
                    · {{ $vigil['won'] ? 'vitória' : 'derrota' }}
                    @if($vigil['mark_earned']) · marca @endif
                    · glória {{ $vigil['glory'] }}
                </li>
            @endforeach
        </ul>
    @endif

    @if($battles['rites'] !== [])
        <h3 class="text-amber-100/80 font-semibold mt-2 mb-2">Ritos da turma</h3>
        <ul class="space-y-2 text-sm">
            @foreach($battles['rites'] as $rite)
                <li class="border-b border-amber-500/10 pb-2">
                    {{ $rite['class_name'] }} · {{ $rite['season_name'] }} · {{ $rite['event'] }}
                    @if($rite['outcome']) · {{ $rite['outcome'] }} @endif
                </li>
            @endforeach
        </ul>
    @endif
</section>

<section id="notas" class="game-card p-5 mb-6 reveal scroll-mt-24">
    <h2 class="font-display text-2xl text-amber-200 mb-4">Notas adquiridas</h2>
    @if($report['grades'] === [])
        <p class="text-amber-100/60">Nenhuma nota lançada neste dia.</p>
    @else
        <ul class="space-y-2 text-sm">
            @foreach($report['grades'] as $grade)
                <li class="border-b border-amber-500/10 pb-2">
                    {{ $grade['student_name'] ?? $grade['team_name'] ?? '—' }}
                    · {{ $grade['class_name'] }} ({{ $grade['area_name'] }})
                    · {{ $grade['type_label'] }}
                    · {{ number_format($grade['value'], 1, ',', '.') }}
                    @if($grade['activity_name']) · {{ $grade['activity_name'] }} @endif
                    @if($grade['reason']) · {{ $grade['reason'] }} @endif
                </li>
            @endforeach
        </ul>
    @endif
</section>

<section id="itens" class="game-card p-5 mb-6 reveal scroll-mt-24">
    <h2 class="font-display text-2xl text-amber-200 mb-4">Itens adquiridos</h2>
    @if($report['items'] === [])
        <p class="text-amber-100/60">Nenhum item adquirido neste dia.</p>
    @else
        <ul class="space-y-2 text-sm">
            @foreach($report['items'] as $item)
                <li class="border-b border-amber-500/10 pb-2">
                    {{ $item['item_icon'] }} {{ $item['item_name'] }}
                    · {{ $item['student_name'] }}
                    · {{ $item['class_name'] }} ({{ $item['area_name'] }})
                </li>
            @endforeach
        </ul>
    @endif
</section>

<section id="eventos" class="game-card p-5 mb-6 reveal scroll-mt-24">
    <h2 class="font-display text-2xl text-amber-200 mb-4">Eventos / quiz</h2>
    @if($report['event_attempts'] === [])
        <p class="text-amber-100/60">Nenhuma tentativa concluída neste dia.</p>
    @else
        <ul class="space-y-2 text-sm">
            @foreach($report['event_attempts'] as $attempt)
                <li class="border-b border-amber-500/10 pb-2">
                    {{ $attempt['student_name'] }} · {{ $attempt['event_title'] }}
                    · {{ $attempt['class_name'] }} ({{ $attempt['area_name'] }})
                    · {{ $attempt['correct_count'] }} acerto(s)
                    @if($attempt['rewards_granted']) · recompensa @endif
                </li>
            @endforeach
        </ul>
    @endif
</section>

<section id="badges" class="game-card p-5 mb-6 reveal scroll-mt-24">
    <h2 class="font-display text-2xl text-amber-200 mb-4">Badges</h2>
    @if($report['badges'] === [])
        <p class="text-amber-100/60">Nenhum badge conquistado neste dia.</p>
    @else
        <ul class="space-y-2 text-sm">
            @foreach($report['badges'] as $badge)
                <li class="border-b border-amber-500/10 pb-2">
                    {{ $badge['badge_icon'] }} {{ $badge['badge_name'] }}
                    · {{ $badge['student_name'] }}
                    · {{ $badge['class_name'] }} ({{ $badge['area_name'] }})
                </li>
            @endforeach
        </ul>
    @endif
</section>

<section id="ativos" class="game-card p-5 mb-6 reveal scroll-mt-24">
    <h2 class="font-display text-2xl text-amber-200 mb-4">Alunos ativos</h2>
    @if($report['active_students'] === [])
        <p class="text-amber-100/60">Nenhum aluno acessou o sistema neste dia.</p>
    @else
        <ul class="space-y-2 text-sm">
            @foreach($report['active_students'] as $student)
                <li class="border-b border-amber-500/10 pb-2">
                    {{ $student['student_name'] }} · {{ $student['last_accessed_at'] }}
                    @if($student['classes'] !== [])
                        · {{ implode(', ', $student['classes']) }}
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
</section>
@endsection
