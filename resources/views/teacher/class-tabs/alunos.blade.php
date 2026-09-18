    <div class="space-y-6">
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

        <div data-student-roster>
            <div class="mb-3 flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="font-display text-xl text-amber-200">Turma</h2>
                    <p class="text-xs text-amber-100/50">Comportamento inicia em 100. Busque pelo nome e informe os pontos para tirar ou adicionar.</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    @if($class->students->isNotEmpty())
                        <label class="min-w-0 w-full sm:w-64">
                            <span class="sr-only">Buscar aluno</span>
                            <input
                                data-student-search
                                class="game-input !py-1"
                                type="search"
                                placeholder="Buscar por nome..."
                                autocomplete="off"
                            >
                        </label>
                    @endif
                    <a class="game-btn-ghost !px-3 !py-1 text-sm" href="{{ route('teacher.students.export', $class) }}">Exportar PDF</a>
                </div>
            </div>
            @if($class->students->isEmpty())
                <div class="game-card p-8 text-center text-amber-100/60">Nenhum aluno cadastrado ainda.</div>
            @else
                <section class="space-y-8">
                    @foreach($rosterGroups as $group)
                        <div class="space-y-3" data-roster-group>
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
                                        $searchHaystack = mb_strtolower(trim(implode(' ', array_filter([
                                            $student->name,
                                            $student->email,
                                            $student->arenaName(),
                                        ]))));
                                    @endphp
                                    <div
                                        class="game-card p-4 flex flex-col gap-2 min-w-0"
                                        data-student-card
                                        data-search="{{ $searchHaystack }}"
                                        x-data="{ editing: {{ $editingStudent ? 'true' : 'false' }} }"
                                    >
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
                                                @if(auth()->user()?->isAdmin())
                                                    <form method="POST" action="{{ route('admin.impersonate.start', [$class, $student]) }}">
                                                        @csrf
                                                        <input type="hidden" name="return_url" value="{{ route('teacher.classes.show', ['schoolClass' => $class, 'tab' => 'alunos']) }}">
                                                        <button class="game-btn !px-2 !py-1 text-xs" type="submit">Visão aluno</button>
                                                    </form>
                                                @endif
                                                @include('partials.reset-student-password')
                                            </div>
                                            <form
                                                method="POST"
                                                action="{{ route('teacher.grades.behavior', [$class, $student]) }}"
                                                data-behavior-form
                                                class="flex flex-col gap-2"
                                            >
                                                @csrf
                                                <div class="flex flex-wrap items-center gap-1">
                                                    <span class="text-xs text-amber-100/50 uppercase tracking-wide">Comp.</span>
                                                    <button class="game-btn-ghost !px-2 !py-1 text-xs" type="submit" name="delta" value="-5" title="-5">−5</button>
                                                    <button class="game-btn-ghost !px-2 !py-1 text-sm" type="submit" name="delta" value="-1" title="-1">−</button>
                                                    <span
                                                        data-behavior-score
                                                        class="font-display text-lg w-10 text-center {{ $behavior < 50 ? 'text-rose-300' : ($behavior < 80 ? 'text-amber-300' : 'text-emerald-300') }}"
                                                    >
                                                        {{ number_format($behavior, 0) }}
                                                    </span>
                                                    <button class="game-btn-ghost !px-2 !py-1 text-sm" type="submit" name="delta" value="1" title="+1">+</button>
                                                    <button class="game-btn-ghost !px-2 !py-1 text-xs" type="submit" name="delta" value="5" title="+5">+5</button>
                                                </div>
                                                <div class="flex flex-wrap items-center gap-1">
                                                    <label class="flex items-center gap-1 min-w-0">
                                                        <span class="text-xs text-amber-100/50">Pontos</span>
                                                        <input
                                                            data-behavior-amount
                                                            class="game-input !py-1 !px-2 !w-16 text-center text-sm"
                                                            type="number"
                                                            min="1"
                                                            max="100"
                                                            step="1"
                                                            value="1"
                                                            inputmode="numeric"
                                                        >
                                                    </label>
                                                    <button class="game-btn-ghost !px-2 !py-1 text-xs" type="submit" name="delta" value="-1" data-behavior-sign="-1">Tirar</button>
                                                    <button class="game-btn-ghost !px-2 !py-1 text-xs" type="submit" name="delta" value="1" data-behavior-sign="1">Adicionar</button>
                                                </div>
                                            </form>
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
                    <p data-roster-empty hidden class="game-card p-6 text-center text-amber-100/60">Nenhum aluno encontrado.</p>
                </section>
            @endif
        </div>
    </div>
