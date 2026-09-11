    <div
        class="space-y-6"
        x-data="{
            activityId: '{{ old('activity_id', $gradableActivities->first()?->id) }}',
            target: 'student'
        }"
    >
        <form method="POST" action="{{ route('teacher.grades.store', $class) }}" class="game-card p-5 space-y-4">
            @csrf
            <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-3">
                <div>
                    <h2 class="font-display text-xl text-amber-200">Nota de atividade</h2>
                    <p class="text-sm text-purple-200/70 mt-1">Atividades-evento não aparecem aqui (nota automática pelo quiz).</p>
                </div>
                <div class="sm:w-80">
                    <label class="block text-xs uppercase tracking-wide text-purple-300/70 mb-1">Atividade</label>
                    <select class="game-select" name="activity_id" x-model="activityId" required>
                        @forelse($gradableActivities as $activity)
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

            @foreach($gradableActivities as $activity)
                <div
                    x-show="String(activityId) === '{{ $activity->id }}'"
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
            <div x-show="target === 'team'">
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
