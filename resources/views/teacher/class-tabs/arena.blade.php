    @php
        $arenaTab = request('arena_tab', old('arena_tab', 'turma'));
        $arenaTab = in_array($arenaTab, ['turma', 'turmas'], true) ? $arenaTab : 'turma';
    @endphp

    <div class="space-y-4">
        <nav class="flex flex-wrap gap-2" aria-label="Abas da arena">
            <a class="tab-btn game-btn-ghost" href="{{ route('teacher.classes.show', ['schoolClass' => $class, 'tab' => 'arena', 'arena_tab' => 'turma']) }}"
                data-active="{{ $arenaTab === 'turma' ? 'true' : 'false' }}">
                Arena da turma
                @if($pendingDuels->isNotEmpty())
                    <span class="ml-1 text-cyan-300">{{ $pendingDuels->count() }}</span>
                @endif
            </a>
            <a class="tab-btn game-btn-ghost" href="{{ route('teacher.classes.show', ['schoolClass' => $class, 'tab' => 'arena', 'arena_tab' => 'turmas']) }}"
                data-active="{{ $arenaTab === 'turmas' ? 'true' : 'false' }}">
                Arena das turmas
                @if(($pendingRealmDuels ?? collect())->isNotEmpty())
                    <span class="ml-1 text-violet-300">{{ $pendingRealmDuels->count() }}</span>
                @endif
            </a>
        </nav>

        @if($arenaTab === 'turma')
            <div class="game-card p-5 flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h2 class="font-display text-xl text-amber-200">Arena de batalha</h2>
                    <p class="text-sm text-amber-100/60 mt-1">
                        Status hoje ({{ \App\Support\ArenaSchedule::todayLabel() }}):
                        <span class="{{ $class->isArenaOpen() ? 'text-emerald-300' : 'text-rose-300' }}">
                            {{ $class->isArenaOpen() ? 'aberta' : 'fechada' }}
                        </span>
                        · Vitória +{{ \App\Models\Duel::GLORY_WIN }} {{ \App\Models\GameCurrency::label('glory') }} · Derrota +{{ \App\Models\Duel::GLORY_LOSS }}
                        · Espera {{ $class->arenaCooldownLabel() }}
                        · Limite {{ $class->arenaDailyLimit() }}/dia
                        · Guildas {{ $class->guildArenaDailyLimit() }}/dia
                    </p>
                    <p class="text-sm text-cyan-200/70 mt-2">
                        Batalhas de guildas: com a arena aberta, qualquer membro pode desafiar outra guilda.
                        Cada guilda resolve no máximo <strong>{{ $class->guildArenaDailyLimit() }} {{ $class->guildArenaDailyLimit() === 1 ? 'batalha' : 'batalhas' }} por dia</strong>. Quem lutou ganha {{ \App\Models\GameCurrency::label('glory') }}/{{ \App\Models\GameCurrency::label('relics') }}; a média e o XP não mudam.
                    </p>
                </div>
                @if($class->isArenaOpen())
                    <div class="flex flex-wrap gap-2">
                        <a class="game-btn-ghost" href="{{ route('arena.rules') }}">Regras para a turma</a>
                        <form method="POST" action="{{ route('teacher.arena.close', $class) }}">
                            @csrf
                            <button class="game-btn-ghost" type="submit">
                                {{ $class->hasArenaSchedule() ? 'Fechar hoje ('.\App\Support\ArenaSchedule::todayLabel().')' : 'Fechar arena' }}
                            </button>
                        </form>
                    </div>
                @else
                    <div class="flex flex-wrap gap-2">
                        <a class="game-btn-ghost" href="{{ route('arena.rules') }}">Regras para a turma</a>
                        <form method="POST" action="{{ route('teacher.arena.open', $class) }}">
                            @csrf
                            <button class="game-btn" type="submit">
                                {{ $class->hasArenaSchedule() ? 'Abrir hoje ('.\App\Support\ArenaSchedule::todayLabel().')' : 'Abrir arena' }}
                            </button>
                        </form>
                    </div>
                @endif
            </div>

            <form method="POST" action="{{ route('teacher.arena.update', $class) }}" class="game-card p-5 space-y-4">
                @csrf
                @method('PUT')
                <input type="hidden" name="tab" value="arena">
                <input type="hidden" name="arena_tab" value="turma">
                <div>
                    <h3 class="font-display text-lg text-amber-200">Configurações da arena</h3>
                    <p class="text-sm text-amber-100/60 mt-1">Defina, para cada dia da semana, se a arena está aberta, o intervalo entre desafios e o limite de duelos e de batalhas de guildas.</p>
                </div>
                @include('partials.arena-weekday-settings', [
                    'prefix' => 'days',
                    'week' => $class->arenaWeek(),
                    'limitLabel' => 'Duelos no dia',
                    'cooldownHint' => '0 = sem espera. Ex.: 30 para meia hora, 120 para 2 horas.',
                    'guildPrefix' => 'guild_days',
                    'guildWeek' => $class->guildArenaWeek(),
                    'guildLimitLabel' => 'Guildas no dia',
                ])
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
                            · +{{ $duel->glory_winner }}/+{{ $duel->glory_loser }} {{ \App\Models\GameCurrency::label('glory') }}
                        </span>
                    </div>
                @empty
                    <p class="text-sm text-purple-200/60">Sem duelos resolvidos.</p>
                @endforelse
            </div>
        @else
            @if($class->area)
                @php $realmArea = $class->area; @endphp
                <form method="POST" action="{{ route('teacher.arena.realm.update', $class) }}" class="game-card p-5 space-y-4 border-violet-400/15">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="tab" value="arena">
                    <input type="hidden" name="arena_tab" value="turmas">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h3 class="font-display text-lg text-violet-200">Configurações da arena entre turmas</h3>
                            <p class="text-sm text-amber-100/60 mt-1">
                                Reino {{ $realmArea->name }} · vale para todas as turmas deste reino, com uma configuração por dia da semana.
                                @if(auth()->user()?->isAdmin())
                                    <a href="{{ route('admin.areas.arena', ['area' => $realmArea, 'tab' => 'turmas']) }}" class="text-violet-300 underline ml-1">Abrir painel do reino</a>
                                @endif
                            </p>
                        </div>
                        <p class="text-xs {{ $realmArea->isRealmArenaOpen() ? 'text-emerald-300' : 'text-rose-300' }}">
                            Hoje ({{ \App\Support\ArenaSchedule::todayLabel() }}):
                            {{ $realmArea->isRealmArenaOpen() ? 'aberta' : 'fechada' }}
                            · espera {{ $realmArea->realmArenaCooldownLabel() }}
                            · {{ $realmArea->realmArenaDailyLimit() }}/dia
                        </p>
                    </div>
                    @include('partials.arena-weekday-settings', [
                        'prefix' => 'realm_days',
                        'week' => $realmArea->realmArenaWeek(),
                        'limitLabel' => 'Duelos do reino no dia',
                        'cooldownHint' => '0 = sem espera. Ex.: 30 para meia hora.',
                    ])
                    <button class="game-btn" type="submit">Salvar arena entre turmas</button>
                </form>

                <div class="grid lg:grid-cols-2 gap-4">
                    <div class="game-card p-5 border-violet-400/15">
                        <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
                            <h3 class="font-display text-lg text-violet-200">Desafios entre turmas pendentes</h3>
                            @if(auth()->user()?->isAdmin())
                                <a href="{{ route('admin.areas.arena', ['area' => $realmArea, 'tab' => 'turmas']) }}" class="text-xs text-violet-300 underline">Ver reino inteiro</a>
                            @endif
                        </div>
                        @forelse($pendingRealmDuels as $duel)
                            <div class="flex flex-wrap items-start justify-between gap-3 py-2 border-b border-purple-900/40 text-sm">
                                <p>
                                    {{ $duel->challenger->arenaName() ?: $duel->challenger->name }}
                                    <span class="text-amber-100/45">({{ $duel->challengerClass->name }})</span>
                                    →
                                    {{ $duel->opponent->arenaName() ?: $duel->opponent->name }}
                                    <span class="text-amber-100/45">({{ $duel->opponentClass->name }})</span>
                                </p>
                                <form method="POST" action="{{ route('teacher.arena.realm.cancel', [$class, $duel]) }}"
                                    onsubmit="return confirm('Cancelar este desafio pendente?')">
                                    @csrf
                                    <button type="submit" class="game-btn-ghost !py-1 !px-3 text-xs text-rose-300">Cancelar</button>
                                </form>
                            </div>
                        @empty
                            <p class="text-sm text-purple-200/60">Nenhum desafio entre turmas pendente.</p>
                        @endforelse
                    </div>

                    <div class="game-card p-5 border-violet-400/15">
                        <h3 class="font-display text-lg text-violet-200 mb-3">Histórico entre turmas</h3>
                        @forelse($recentRealmDuels as $duel)
                            <div class="flex flex-wrap justify-between gap-2 py-2 border-b border-purple-900/40 text-sm">
                                <span>
                                    {{ $duel->challenger->arenaName() ?: $duel->challenger->name }}
                                    <span class="text-amber-100/40">({{ $duel->challengerClass->name }})</span>
                                    vs
                                    {{ $duel->opponent->arenaName() ?: $duel->opponent->name }}
                                    <span class="text-amber-100/40">({{ $duel->opponentClass->name }})</span>
                                </span>
                                @if($duel->isResolved())
                                    <span class="text-emerald-300">
                                        Venceu: {{ $duel->winner?->arenaName() ?: $duel->winner?->name }}
                                        · +{{ $duel->aura_winner }}/+{{ $duel->aura_loser }} {{ \App\Models\GameCurrency::label('auras') }}
                                    </span>
                                @else
                                    <span class="text-rose-300/80">Cancelado / recusado</span>
                                @endif
                            </div>
                        @empty
                            <p class="text-sm text-purple-200/60">Sem duelos entre turmas ainda.</p>
                        @endforelse
                    </div>
                </div>
            @else
                <div class="game-card p-5 border-violet-400/15">
                    <h3 class="font-display text-lg text-violet-200">Arena das turmas</h3>
                    <p class="text-sm text-amber-100/60 mt-1">Esta turma ainda não pertence a um reino. Vincule a turma a um reino para configurar os duelos entre turmas.</p>
                </div>
            @endif
        @endif

        @include('partials.combat-rules', ['detailed' => true])
    </div>
