    <div class="space-y-6">
        <div class="game-card p-5 space-y-4">
            <div>
                <h2 class="font-display text-xl text-amber-200">Chamada</h2>
                <p class="text-sm text-amber-100/60 mt-1">
                    Escolha a data, gere a lista e marque Presente, Ausente ou Falta justificada.
                    Só o Presente gera {{ \App\Models\GameCurrency::label('seals') }} para a loja. Justificada conta na nota de frequência.
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
                        <p class="text-xs text-amber-100/50">Todos começam como presentes. Desligue quem faltou e salve para atualizar a média e {{ \App\Models\GameCurrency::label('seals') }}.</p>
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

            <form method="POST" action="{{ route('teacher.attendance.destroy', [$class, $activeAttendanceSession]) }}" onsubmit="return confirm({{ \Illuminate\Support\Js::from('Remover a chamada deste dia? '.\App\Models\GameCurrency::label('seals').' de presença serão revertidos.') }})">
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
