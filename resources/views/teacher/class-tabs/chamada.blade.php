    @php
        $attendanceOrder = request('order') === 'team' ? 'team' : 'name';
    @endphp
    <div class="space-y-6">
        <div class="game-card game-card--overflow relative z-20 p-5 space-y-4">
            <div>
                <h2 class="font-display text-xl text-amber-200">Chamada</h2>
                <p class="text-sm text-amber-100/60 mt-1">
                    Escolha a data, gere a lista e marque Presente, Ausente ou Falta justificada.
                    Só o Presente gera {{ \App\Models\GameCurrency::label('seals') }} para a loja. Justificada conta na nota de frequência.
                </p>
            </div>
            <form method="POST" action="{{ route('teacher.attendance.store', $class) }}" class="flex flex-wrap items-end gap-3">
                @csrf
                @if($attendanceOrder === 'team')
                    <input type="hidden" name="order" value="team">
                @endif
                <label class="w-52 space-y-1">
                    <span class="text-xs uppercase tracking-wide text-purple-200/70">Data</span>
                    <div
                        class="relative"
                        x-data="miniDatePicker({{ \Illuminate\Support\Js::from(old('held_on', now()->toDateString())) }})"
                        @click.outside="open = false"
                    >
                        <button
                            type="button"
                            class="game-input flex items-center justify-between gap-3 text-left"
                            @click="toggle()"
                            :aria-expanded="open"
                            aria-haspopup="dialog"
                        >
                            <span x-text="label"></span>
                            <svg class="h-4 w-4 shrink-0 text-amber-200" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                <rect x="3.5" y="5" width="17" height="15.5" rx="2"></rect>
                                <path d="M8 3.5v3M16 3.5v3M3.5 10h17"></path>
                            </svg>
                        </button>
                        <input type="hidden" name="held_on" value="{{ old('held_on', now()->toDateString()) }}" :value="value">
                        <div
                            x-show="open"
                            x-cloak
                            class="absolute left-0 top-full z-30 mt-2 w-64 rounded-xl border border-amber-200/25 bg-[#140a12] p-3 shadow-xl"
                            role="dialog"
                            aria-label="Calendário"
                        >
                            <div class="mb-2 flex items-center justify-between gap-2">
                                <button type="button" class="game-btn-ghost !px-2 !py-1 text-sm" @click="previousMonth()" aria-label="Mês anterior">‹</button>
                                <p class="text-sm text-amber-200" x-text="monthLabel"></p>
                                <button type="button" class="game-btn-ghost !px-2 !py-1 text-sm" @click="nextMonth()" aria-label="Próximo mês">›</button>
                            </div>
                            <div class="mb-1 grid grid-cols-7 gap-1 text-center text-[11px] text-purple-200/60">
                                <template x-for="(dayName, weekdayIndex) in weekdays" :key="weekdayIndex">
                                    <span x-text="dayName"></span>
                                </template>
                            </div>
                            <div class="grid grid-cols-7 gap-1">
                                <template x-for="(day, index) in days" :key="index">
                                    <button
                                        type="button"
                                        class="h-8 rounded-md text-sm"
                                        :class="!day
                                            ? 'invisible'
                                            : (isSelected(day)
                                                ? 'bg-amber-400 font-semibold text-[#2a1703]'
                                                : 'text-amber-50 hover:bg-amber-400/15' + (isToday(day) ? ' ring-1 ring-amber-300/70' : ''))"
                                        :disabled="!day"
                                        @click="day && select(day)"
                                        x-text="day || ''"
                                    ></button>
                                </template>
                            </div>
                        </div>
                    </div>
                </label>
                <button class="game-btn" type="submit">Gerar chamada</button>
            </form>
        </div>

        @if($attendanceSessions->isNotEmpty())
            <div class="game-card p-5 space-y-4">
                <h3 class="font-display text-lg text-amber-200">Histórico</h3>
                @foreach($attendanceSessions->groupBy(fn ($session) => $session->held_on->format('Y-m')) as $sessions)
                    <div class="space-y-2">
                        <h4 class="text-xs uppercase tracking-wide text-purple-200/70">
                            {{ \Illuminate\Support\Str::ucfirst($sessions->first()->held_on->copy()->locale('pt_BR')->translatedFormat('F Y')) }}
                        </h4>
                        <div class="flex flex-wrap gap-2">
                            @foreach($sessions as $session)
                                <a
                                    class="game-btn-ghost !py-1 !px-3 text-sm {{ $activeAttendanceSession && $activeAttendanceSession->id === $session->id ? 'border-amber-400/50' : '' }}"
                                    href="{{ route('teacher.classes.show', array_filter([
                                        'schoolClass' => $class,
                                        'tab' => 'chamada',
                                        'session' => $session->id,
                                        'order' => $attendanceOrder === 'team' ? 'team' : null,
                                    ])) }}"
                                >
                                    {{ $session->held_on->format('d/m/Y') }}
                                    @if($session->records->whereNotNull('status')->isEmpty())
                                        <span class="text-amber-100/40">· rascunho</span>
                                    @endif
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        @if($activeAttendanceSession)
            @php
                $recordsByStudent = $activeAttendanceSession->records->keyBy('student_id');
                $attendanceBlocks = $attendanceOrder === 'team'
                    ? $rosterGroups
                    : collect([[
                        'key' => 'all',
                        'title' => null,
                        'emblem' => null,
                        'students' => $class->students->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)->values(),
                    ]]);
                $attendanceOrderQuery = [
                    'schoolClass' => $class,
                    'tab' => 'chamada',
                    'session' => $activeAttendanceSession->id,
                ];
            @endphp
            <form
                method="POST"
                action="{{ route('teacher.attendance.update', [$class, $activeAttendanceSession]) }}"
                class="game-card p-5 space-y-4"
                data-attendance-form
            >
                @csrf
                @method('PUT')
                @if($attendanceOrder === 'team')
                    <input type="hidden" name="order" value="team">
                @endif
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

                <div class="flex flex-wrap items-center gap-2">
                    <span class="text-xs uppercase tracking-wide text-purple-200/70">Ordenar</span>
                    <a
                        class="game-btn-ghost !py-1 !px-3 text-xs {{ $attendanceOrder === 'name' ? 'border-amber-400/50' : '' }}"
                        href="{{ route('teacher.classes.show', $attendanceOrderQuery) }}"
                    >Alfabética</a>
                    <a
                        class="game-btn-ghost !py-1 !px-3 text-xs {{ $attendanceOrder === 'team' ? 'border-amber-400/50' : '' }}"
                        href="{{ route('teacher.classes.show', [...$attendanceOrderQuery, 'order' => 'team']) }}"
                    >Por equipe</a>
                </div>

                <div class="space-y-3" data-attendance-list>
                    @foreach($attendanceBlocks as $block)
                        @if($block['title'])
                            <h4 class="pt-2 text-xs uppercase tracking-wide text-amber-200/80">
                                @if($block['emblem'])
                                    <span aria-hidden="true">{{ $block['emblem'] }}</span>
                                @endif
                                {{ $block['title'] }}
                            </h4>
                        @endif
                        @foreach($block['students'] as $student)
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
                    @endforeach
                </div>

                <div class="flex flex-wrap items-center gap-3 pt-2">
                    <button class="game-btn" type="submit">Salvar chamada</button>
                </div>
            </form>

            <form method="POST" action="{{ route('teacher.attendance.destroy', [$class, $activeAttendanceSession]) }}" onsubmit="return confirm({{ \Illuminate\Support\Js::from('Remover a chamada deste dia? '.\App\Models\GameCurrency::label('seals').' de presença serão revertidos.') }})">
                @csrf
                @method('DELETE')
                @if($attendanceOrder === 'team')
                    <input type="hidden" name="order" value="team">
                @endif
                <button class="game-btn-ghost !py-1 !px-3 text-sm text-rose-300" type="submit">Excluir esta chamada</button>
            </form>
        @elseif($attendanceSessions->isEmpty())
            <div class="game-card p-5 text-sm text-amber-100/55">
                Nenhuma chamada ainda. Escolha a data e clique em Gerar chamada.
            </div>
        @endif
    </div>
