    <div class="space-y-6"
         x-data="{ type: '{{ old('type', $editingActivity->type ?? 'individual') }}' }">
        <form method="POST"
              :action="type === 'event'
                  ? '{{ route('teacher.activities.event.store', $class) }}'
                  : '{{ $editingActivity
                      ? route('teacher.activities.update', [$class, $editingActivity])
                      : route('teacher.activities.store', $class) }}'"
              class="game-card p-5 space-y-4">
            @csrf
            @if($editingActivity && ($editingActivity->type ?? '') !== 'event')
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
                <label class="space-y-1 sm:col-span-2" x-show="type !== 'event'">
                    <span class="text-xs uppercase tracking-wide text-purple-200/70">Nome</span>
                    <input class="game-input" name="name" value="{{ old('name', $editingActivity->name ?? '') }}" placeholder="Ex: Prova 1" maxlength="120" :required="type !== 'event'" :disabled="type === 'event'">
                </label>
                <label class="space-y-1 sm:col-span-2" x-show="type === 'event'">
                    <span class="text-xs uppercase tracking-wide text-purple-200/70">Nome do evento</span>
                    <input class="game-input" name="title" value="{{ old('title', old('name', '')) }}" placeholder="Ex: Quiz da semana" maxlength="120" :required="type === 'event'" :disabled="type !== 'event'">
                </label>
                <label class="space-y-1">
                    <span class="text-xs uppercase tracking-wide text-purple-200/70">Tipo</span>
                    <select class="game-select" name="type" x-model="type" @if($editingActivity) disabled @endif>
                        <option value="individual" @selected(old('type', $editingActivity->type ?? 'individual') === 'individual')>Individual</option>
                        <option value="team" @selected(old('type', $editingActivity->type ?? 'individual') === 'team')>Equipe / guilda</option>
                        <option value="event" @selected(old('type', $editingActivity->type ?? 'individual') === 'event')>Evento (quiz)</option>
                    </select>
                    @if($editingActivity)
                        <input type="hidden" name="type" value="{{ $editingActivity->type }}">
                    @endif
                </label>
                <div class="grid grid-cols-2 gap-3" x-show="type !== 'event'">
                    <label class="space-y-1">
                        <span class="text-xs uppercase tracking-wide text-purple-200/70">Nota máx.</span>
                        <input class="game-input" type="number" name="max_score" value="{{ old('max_score', $editingActivity->max_score ?? 100) }}" min="1" max="100" :required="type !== 'event'" :disabled="type === 'event'">
                    </label>
                    <label class="space-y-1">
                        <span class="text-xs uppercase tracking-wide text-purple-200/70">Peso</span>
                        <input class="game-input" type="number" name="weight" value="{{ old('weight', $editingActivity->weight ?? 1) }}" min="1" max="10" :required="type !== 'event'" :disabled="type === 'event'">
                    </label>
                </div>
                <label class="space-y-1" x-show="type === 'event'">
                    <span class="text-xs uppercase tracking-wide text-purple-200/70">Peso na média</span>
                    <input class="game-input" type="number" name="weight" value="{{ old('weight', 1) }}" min="1" max="10" :required="type === 'event'" :disabled="type !== 'event'">
                    <span class="text-xs text-purple-200/50">Nota máx. 100 · proporcional aos acertos</span>
                </label>
            </div>

            <div x-show="type === 'event'" class="space-y-4 border-t border-purple-800/40 pt-4">
                <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-3">
                    <label class="space-y-1">
                        <span class="text-xs uppercase tracking-wide text-purple-200/70">Modo</span>
                        <select class="game-select" name="mode">
                            <option value="window" @selected(old('mode', 'window') === 'window')>Janela de tempo</option>
                            <option value="live" @selected(old('mode') === 'live')>Ao vivo</option>
                        </select>
                    </label>
                    <label class="space-y-1">
                        <span class="text-xs uppercase tracking-wide text-purple-200/70">Segundos por pergunta</span>
                        <input class="game-input" type="number" name="question_seconds" value="{{ old('question_seconds', 30) }}" min="5" max="300" required>
                    </label>
                    <label class="space-y-1">
                        <span class="text-xs uppercase tracking-wide text-purple-200/70">Início</span>
                        <input class="game-input" type="datetime-local" name="starts_at" value="{{ old('starts_at') }}">
                    </label>
                    <label class="space-y-1">
                        <span class="text-xs uppercase tracking-wide text-purple-200/70">Fim</span>
                        <input class="game-input" type="datetime-local" name="ends_at" value="{{ old('ends_at') }}">
                    </label>
                </div>
                <div class="grid sm:grid-cols-3 gap-3">
                    <label class="space-y-1">
                        <span class="text-xs uppercase tracking-wide text-purple-200/70">{{ \App\Models\GameCurrency::label('relics') }} / acerto</span>
                        <input class="game-input" type="number" name="relics_per_correct" value="{{ old('relics_per_correct', 0) }}" min="0" max="100">
                    </label>
                    <label class="space-y-1">
                        <span class="text-xs uppercase tracking-wide text-purple-200/70">{{ \App\Models\GameCurrency::label('seals') }} / acerto</span>
                        <input class="game-input" type="number" name="seals_per_correct" value="{{ old('seals_per_correct', 0) }}" min="0" max="100">
                    </label>
                    <label class="space-y-1">
                        <span class="text-xs uppercase tracking-wide text-purple-200/70">{{ \App\Models\GameCurrency::label('auras') }} / acerto</span>
                        <input class="game-input" type="number" name="auras_per_correct" value="{{ old('auras_per_correct', 0) }}" min="0" max="100">
                    </label>
                </div>
                @include('partials.quiz-questions-form')
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
                                    <td class="py-3 pr-3 text-purple-200/80">
                                        @if($activity->type === 'team') Guilda
                                        @elseif($activity->type === 'event') Evento
                                        @else Individual
                                        @endif
                                    </td>
                                    <td class="py-3 pr-3">{{ $activity->max_score }}</td>
                                    <td class="py-3 pr-3">{{ $activity->weight }}</td>
                                    <td class="py-3">
                                        <div class="flex justify-end flex-wrap gap-2">
                                            @if($activity->type === 'event' && $activity->gameEvent)
                                                <a class="game-btn-ghost px-3 py-1 text-xs" href="{{ route('teacher.events.show', [$class, $activity->gameEvent]) }}">Quiz</a>
                                            @endif
                                            <form method="POST" action="{{ route('teacher.activities.warn', [$class, $activity]) }}" onsubmit="return confirm('Avisar os alunos que ainda não têm nota nesta atividade?')">
                                                @csrf
                                                <button class="game-btn-ghost px-3 py-1 text-xs" type="submit">Avisar pendentes</button>
                                            </form>
                                            @if($activity->type !== 'event')
                                                <a class="game-btn-ghost px-3 py-1 text-xs"
                                                   href="{{ route('teacher.classes.show', ['schoolClass' => $class, 'tab' => 'atividades', 'activity' => $activity->id]) }}">Editar</a>
                                            @endif
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
