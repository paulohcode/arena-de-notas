<div class="game-card p-5 border-violet-400/25 {{ $cardClass ?? '' }}">
    <div class="flex flex-wrap items-center justify-between gap-3 mb-3">
        <div>
            @unless($hideKicker ?? false)
                <p class="text-xs uppercase tracking-wide text-violet-300/80 mb-1">Novo · moeda {{ \App\Models\GameCurrency::label('auras') }}</p>
            @endunless
            <h2 class="font-display text-xl text-violet-200">{{ $heading ?? 'Desafio entre turmas' }}</h2>
        </div>
        @if($area)
            <p class="text-xs text-amber-100/50">{{ $realmResolvedToday }}/{{ $realmDailyLimit }} duelos do reino hoje · {{ \App\Models\GameCurrency::format('auras', $realmAuras) }}</p>
        @endif
    </div>

    @if(! $area)
        <p class="text-sm text-amber-100/70">Esta turma ainda não pertence a um reino. Peça ao professor ou admin para vincular a turma — aí você poderá desafiar outras turmas por {{ \App\Models\GameCurrency::label('auras') }}.</p>
    @else
        <div class="mb-4 rounded-lg border border-violet-400/25 bg-violet-950/20 p-3 text-xs text-amber-100/75 space-y-1">
            <p>Desafie alunos de <strong class="text-violet-200">outras turmas do reino {{ $area->name }}</strong>.</p>
            <p>Vitória: +{{ \App\Models\RealmDuel::AURA_WIN }} {{ \App\Models\GameCurrency::label('auras') }} · Derrota: +{{ \App\Models\RealmDuel::AURA_LOSS }} {{ \App\Models\GameCurrency::label('auras') }}. Sem {{ \App\Models\GameCurrency::label('glory') }} nem {{ \App\Models\GameCurrency::label('relics') }}.</p>
            <p>Mesmo par 1×/dia · até {{ $realmDailyLimit }} duelos resolvidos por dia.</p>
            @if($realmCooldownMinutes > 0)
                <p>Espere <strong class="text-violet-200">{{ $realmCooldownLabel }}</strong> entre um desafio do reino e outro.</p>
            @else
                <p>Não há espera entre desafios do reino.</p>
            @endif
            <p>As duas arenas das turmas também precisam estar abertas.</p>
        </div>

        @unless($canChallengeRealm)
            <p class="mb-4 text-sm text-amber-200/90">
                @if($area && ! $area->isRealmArenaOpen())
                    A arena entre turmas deste reino está fechada.
                @elseif(! $class->isArenaOpen())
                    Sua arena está fechada — o professor precisa abrir para você desafiar.
                @elseif(! $student->hasApprovedPersona())
                    Aprove avatar e nome de jogo para desafiar outras turmas.
                @endif
            </p>
        @endunless

        @forelse($realmOpponents as $row)
            @php $peer = $row['student']; $peerClass = $row['class']; @endphp
            <div class="flex flex-wrap items-center justify-between gap-3 py-3 border-b border-purple-900/40">
                <div class="flex items-center gap-3 min-w-0">
                    @include('partials.player-avatar', ['student' => $peer, 'size' => 'sm'])
                    <div class="min-w-0">
                        <p class="font-semibold truncate">{{ $peer->name }}
                            @if($peer->arenaName())
                                <span class="text-amber-300"> · {{ $peer->arenaName() }}</span>
                            @endif
                        </p>
                        <p class="text-xs text-amber-100/50">{{ $peerClass->name }} · {{ $peer->characterClassLabel() }}</p>
                    </div>
                </div>
                @if(! empty($realmNotices[$peer->id]))
                    <p class="text-xs text-amber-200/90 max-w-64 text-right leading-snug">{{ $realmNotices[$peer->id] }}</p>
                @elseif($canChallengeRealm)
                    <form method="POST" action="{{ route('student.arena.realm.challenge') }}">
                        @csrf
                        <input type="hidden" name="opponent_id" value="{{ $peer->id }}">
                        <button class="game-btn-ghost !py-1 !px-3 text-sm" type="submit">Desafiar</button>
                    </form>
                @elseif(! $peerClass->isArenaOpen())
                    <p class="text-xs text-amber-200/90">Arena da outra turma fechada</p>
                @endif
            </div>
        @empty
            <p class="text-sm text-purple-200/60">Nenhum aluno de outra turma do reino com personagem aprovado ainda.</p>
        @endforelse

        @if($pendingRealmOutgoing->isNotEmpty())
            <div class="mt-6">
                <h3 class="text-sm uppercase tracking-wide text-purple-200/70 mb-2">Desafios do reino enviados</h3>
                @foreach($pendingRealmOutgoing as $duel)
                    <a href="{{ route('student.arena.realm.show', $duel) }}" class="block text-sm py-1 text-violet-300/90 hover:text-violet-200 underline">
                        Aguardando {{ $duel->opponent->arenaName() ?: $duel->opponent->name }} ({{ $duel->opponentClass->name }}) — sala de espera
                    </a>
                @endforeach
            </div>
        @endif

        <div class="mt-6">
            <h3 class="text-sm uppercase tracking-wide text-purple-200/70 mb-2">Duelos do reino recentes</h3>
            @forelse($realmHistory as $duel)
                <a href="{{ route('student.arena.realm.show', $duel) }}" class="flex justify-between py-2 border-b border-purple-900/40 text-sm hover:text-amber-200">
                    <span>
                        vs {{ $duel->otherParticipant($student)?->arenaName() ?: $duel->otherParticipant($student)?->name }}
                        <span class="text-amber-100/40">({{ $duel->classFor($duel->otherParticipant($student))?->name }})</span>
                    </span>
                    <span class="{{ $duel->winner_id === $student->id ? 'text-emerald-300' : 'text-rose-300' }}">
                        {{ $duel->winner_id === $student->id ? 'Vitória' : 'Derrota' }}
                    </span>
                </a>
            @empty
                <p class="text-sm text-purple-200/60">Nenhum duelo entre turmas resolvido ainda.</p>
            @endforelse
        </div>
    @endif
</div>
