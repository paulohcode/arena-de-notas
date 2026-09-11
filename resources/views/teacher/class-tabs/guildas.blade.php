    <div>
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
