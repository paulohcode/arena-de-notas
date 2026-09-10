<div class="grid lg:grid-cols-3 gap-6 mb-8">
    <div class="game-card game-card-glow {{ $student->characterAuraClass() }} p-6 lg:col-span-2">
        @include('partials.class-fx', ['characterClass' => $student->character_class])
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <p class="text-sm text-purple-200/70">Nota atual</p>
                <p class="font-display text-6xl text-cyan-300" data-count-to="{{ $average }}" data-count-from="0">{{ number_format($average, 1) }}</p>
            </div>
            <div class="text-right">
                <p class="font-display text-2xl text-amber-300">{{ $level['name'] }}</p>
                <p class="text-sm text-purple-200/70">{{ $enrollment?->xp ?? 0 }} XP</p>
                <p class="text-sm text-cyan-300/80">{{ $enrollment?->glory ?? 0 }} Glória · {{ $enrollment?->relics ?? 0 }} Relíquias · {{ $enrollment?->seals ?? 0 }} Selos · {{ $enrollment?->arena_wins ?? 0 }}V–{{ $enrollment?->arena_losses ?? 0 }}D</p>
                <p class="text-sm">Jogador: {{ $position ? $position.'º' : '—' }} · Guilda: {{ $guildPosition ? $guildPosition.'º' : '—' }}</p>
            </div>
        </div>
        <div class="mt-4">
            <div class="flex justify-between text-xs text-purple-200/60 mb-1">
                <span>Próximo nível{{ $level['next'] ? ' ('.$level['next'].' XP)' : '' }}</span>
                <span>{{ $level['progress'] }}%</span>
            </div>
            <div class="xp-track"><div class="xp-fill" data-xp-fill="{{ $level['progress'] }}"></div></div>
        </div>
        @unless($viewerIsTeacher)
            <label class="block mt-4">
                <span class="text-xs uppercase tracking-wide text-purple-200/70">Chamada</span>
                <select class="game-select mt-1 w-full" data-attendance-dropdown aria-label="Chamada">
                    @forelse($attendanceRecords as $record)
                        <option value="{{ $record->id }}">
                            {{ $record->session->held_on->format('d/m/Y') }} · {{ $record->statusLabel() }}
                        </option>
                    @empty
                        <option value="">Nenhuma chamada ainda</option>
                    @endforelse
                </select>
            </label>
        @endunless
    </div>

    <div class="game-card p-6">
        @if($team)
            <p class="text-4xl">{{ $team->emblemIcon() }}</p>
            <h2 class="font-display text-2xl text-amber-200 mt-2">{{ $team->name }}</h2>
            <p class="text-purple-200/70">{{ $viewerIsTeacher ? 'A guilda está em' : 'Vocês estão em' }} {{ $guildPosition }}º no Hall</p>
            @unless($viewerIsTeacher)
                <a href="{{ route('ranking.guild', [$class, $team]) }}" class="text-xs text-amber-200/70 hover:text-amber-200 underline mt-2 inline-block">Ver a guilda</a>
            @endunless
            @include('partials.guild-mission-alerts')
        @else
            <h2 class="font-display text-2xl text-amber-200">Sem guilda</h2>
            <p class="text-purple-200/70">{{ $viewerIsTeacher ? 'Este aluno ainda não está em uma equipe.' : 'O professor ainda não te colocou em uma equipe.' }}</p>
        @endif
        @if($viewerIsTeacher)
            <p class="text-sm text-purple-200/70 mt-4">
                Ranking público:
                <span class="{{ $enrollment?->ranking_visible ? 'text-emerald-300' : 'text-amber-200' }}">
                    {{ $enrollment?->ranking_visible ? 'nome visível' : 'nome oculto' }}
                </span>
            </p>
        @else
            <form method="POST" action="{{ route('student.privacy') }}" class="mt-4">
                @csrf
                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" name="ranking_visible" value="1" @checked($enrollment?->ranking_visible) onchange="this.form.submit()">
                    Mostrar meu nome e nota no ranking
                </label>
            </form>
            <label class="flex items-center gap-2 text-sm mt-3 text-purple-200/70">
                <input type="checkbox" data-sound-toggle> Som da arena
            </label>
            <a href="{{ route('student.arena.index') }}" class="game-btn !py-1 !px-3 text-sm mt-4 inline-block">Arena de batalha</a>
        @endif
    </div>
</div>

<div class="grid lg:grid-cols-2 gap-6 mb-8">
    <div class="game-card p-6">
        <h2 class="font-display text-xl text-amber-200 mb-3">Composição da nota</h2>
        @if($viewerIsTeacher)
            <p class="text-sm text-purple-200/70 mb-4">A média ponderada é a soma de (nota × peso) dividida pela soma dos pesos. Ajustes entram depois.</p>
        @endif
        @foreach($breakdown as $line)
            <div class="flex justify-between gap-3 py-2 border-b border-purple-900/40">
                <span>
                    {{ $line['label'] }}
                    @if($line['weight'])
                        <span class="text-purple-300/50 text-xs">peso {{ $line['weight'] }}</span>
                    @endif
                    @if($viewerIsTeacher && $line['kind'] === 'individual' && ! $line['graded'])
                        <span class="block text-xs text-amber-200/70">sem lançamento · usa nota padrão</span>
                    @endif
                </span>
                <span class="text-right {{ $line['kind'] === 'adjust' && $line['score'] < 0 ? 'text-rose-300' : 'text-cyan-300' }}">
                    @if($viewerIsTeacher && $line['kind'] !== 'adjust')
                        <span class="block text-sm">{{ number_format($line['score'], 1) }} × {{ $line['weight'] }} = {{ number_format($line['contribution'], 1) }}</span>
                    @else
                        {{ number_format($line['score'], 1) }}
                    @endif
                </span>
            </div>
        @endforeach
        @if($viewerIsTeacher)
            <div class="mt-4 space-y-1 text-sm">
                <div class="flex justify-between gap-3 text-purple-200/70">
                    <span>Soma (nota × peso)</span>
                    <span>{{ number_format($averageBreakdown['weighted_sum'], 1) }}</span>
                </div>
                <div class="flex justify-between gap-3 text-purple-200/70">
                    <span>Soma dos pesos</span>
                    <span>{{ $averageBreakdown['weight_sum'] }}</span>
                </div>
                <div class="flex justify-between gap-3 text-purple-200/70">
                    <span>Média ponderada</span>
                    <span>{{ number_format($averageBreakdown['weighted_average'], 1) }}</span>
                </div>
                @if($averageBreakdown['adjustments'] != 0.0)
                    <div class="flex justify-between gap-3 {{ $averageBreakdown['adjustments'] < 0 ? 'text-rose-300' : 'text-emerald-300' }}">
                        <span>Ajustes</span>
                        <span>{{ sprintf('%+0.1f', $averageBreakdown['adjustments']) }}</span>
                    </div>
                @endif
                <div class="flex justify-between gap-3 pt-2 border-t border-purple-900/40 font-semibold text-cyan-300">
                    <span>Média final</span>
                    <span>{{ number_format($averageBreakdown['average'], 1) }}</span>
                </div>
            </div>
        @endif
    </div>
    <div class="game-card p-6">
        <h2 class="font-display text-xl text-amber-200 mb-3">Extrato</h2>
        @forelse($entries as $entry)
            <div class="flex justify-between py-2 text-sm border-b border-purple-900/40">
                <span>{{ $entry->activity?->name ?? $entry->reason }}</span>
                <span class="{{ ($entry->type === 'penalty' || ($entry->delta ?? 0) < 0) ? 'text-rose-300' : 'text-emerald-300' }}">
                    {{ $entry->type === 'activity' ? number_format($entry->raw_score, 1) : sprintf('%+0.1f', $entry->delta) }}
                </span>
            </div>
        @empty
            <p class="text-purple-200/60">Nenhum lançamento ainda.</p>
        @endforelse
    </div>
</div>

<div class="game-card p-6 mb-8">
    <h2 class="font-display text-xl text-amber-200 mb-3">Medalhas</h2>
    <div class="flex flex-wrap gap-3">
        @forelse($badges as $badge)
            <div class="px-3 py-2 rounded-xl border border-amber-300/30 bg-amber-300/10">{{ $badge->icon }} {{ $badge->name }}</div>
        @empty
            <p class="text-purple-200/60">{{ $viewerIsTeacher ? 'Nenhuma medalha nesta turma ainda.' : 'Nenhuma medalha ainda. Jogue a temporada.' }}</p>
        @endforelse
    </div>
</div>

<div class="grid lg:grid-cols-2 gap-6">
    <div>
        <h2 class="font-display text-xl text-amber-200 mb-3">Liga de jogadores</h2>
        <div class="space-y-2" data-player-list>
            @foreach($players as $row)
                <div class="game-card p-3 flex justify-between {{ $row['student']->characterAuraClass(true) }} {{ $row['student']->is($student) ? 'border-amber-300/50' : '' }}" data-player-id="{{ $row['student']->id }}">
                    @include('partials.class-fx', ['characterClass' => $row['student']->character_class])
                    <span class="flex items-center gap-2 min-w-0">
                        <span data-pos>{{ $row['position'] }}º</span>
                        @include('partials.player-avatar', ['student' => $row['student'], 'size' => 'sm'])
                        @if($viewerIsTeacher)
                            <a
                                href="{{ route('teacher.students.show', [$class, $row['student']]) }}"
                                class="hover:text-amber-300 transition-colors underline decoration-amber-300/40 underline-offset-2"
                                title="Ver ficha de {{ $row['student']->name }}"
                            >{{ $row['student']->name }}</a>
                        @else
                            {{ $row['student']->name }}
                        @endif
                        @if($row['student']->arenaName())
                            <span class="text-amber-300"> · {{ $row['student']->arenaName() }}</span>
                        @endif
                        @include('partials.cosmetic-title', ['student' => $row['student']])
                        <span class="text-xs text-amber-100/45">{{ $row['student']->characterClassLabel() }}</span>
                    </span>
                    <span class="text-cyan-300" data-avg>{{ number_format($row['average'], 1) }}</span>
                </div>
            @endforeach
        </div>
    </div>
    <div>
        <h2 class="font-display text-xl text-amber-200 mb-3">Hall das Guildas</h2>
        <div class="space-y-2" data-guild-list>
            @foreach($guilds as $row)
                <a href="{{ route('ranking.guild', [$class, $row['team']]) }}" class="game-card game-card-glow p-3 flex items-center justify-between" data-guild-id="{{ $row['team']->id }}">
                    <span>{{ $row['team']->emblemIcon() }} <span data-pos>{{ $row['position'] }}º</span> {{ $row['team']->name }}</span>
                    <span class="text-cyan-300" data-score>{{ number_format($row['score'], 1) }}</span>
                </a>
            @endforeach
        </div>
    </div>
</div>
