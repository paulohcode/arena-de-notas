    <div class="space-y-4">
        @if($pendingPersonas->isNotEmpty())
            <a class="game-card p-4 w-full text-left border-amber-400/40 block" href="{{ route('teacher.classes.show', ['schoolClass' => $class, 'tab' => 'personagens']) }}">
                <p class="text-amber-200 font-semibold">{{ $pendingPersonas->count() }} personagem(ns) aguardando aprovação</p>
                <p class="text-sm text-amber-100/60">Abra a aba Personagens para aprovar avatar e nome de jogo.</p>
            </a>
        @endif
        <div class="game-card p-4 flex flex-wrap items-center justify-between gap-3 {{ $class->isArenaOpen() ? 'border-cyan-400/30' : 'border-purple-900/40' }}">
            <div>
                <p class="font-semibold {{ $class->isArenaOpen() ? 'text-cyan-300' : 'text-amber-100/70' }}">
                    Arena {{ $class->isArenaOpen() ? 'aberta' : 'fechada' }}
                </p>
                <p class="text-sm text-amber-100/55">Duelos RPG e batalhas de guildas geram {{ \App\Models\GameCurrency::label('glory') }} — não mexem na média nem no XP. Com a arena aberta, cada guilda pode batalhar 1 vez por dia. Alunos também podem desafiar outras turmas do mesmo reino por {{ \App\Models\GameCurrency::label('auras') }}.</p>
            </div>
            <a class="game-btn-ghost !py-1 !px-3 text-sm" href="{{ route('teacher.classes.show', ['schoolClass' => $class, 'tab' => 'arena']) }}">Gerenciar arena</a>
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
