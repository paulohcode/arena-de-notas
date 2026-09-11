    <div class="space-y-4">
        <div class="game-card p-5">
            <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="font-display text-xl text-amber-200 mb-2">Pedidos de personagem</h2>
                    <p class="text-sm text-amber-100/60">O nome verdadeiro continua visível. O nome de jogo e o avatar só entram na arena depois da sua aprovação.</p>
                </div>
                @if($pendingPersonas->isNotEmpty())
                    <form method="POST" action="{{ route('teacher.characters.approve-all', $class) }}">
                        @csrf
                        <button class="game-btn" type="submit">Aprovar todos</button>
                    </form>
                @endif
            </div>

            @forelse($pendingPersonas as $student)
                <div class="flex flex-wrap items-center justify-between gap-4 py-4 border-b border-purple-900/40">
                    <div class="flex items-center gap-3 min-w-0">
                        @include('partials.player-avatar', ['student' => $student, 'avatarKey' => $student->pending_character_avatar, 'size' => 'md'])
                        <div class="min-w-0">
                            <p class="font-semibold truncate">{{ $student->name }}</p>
                            <p class="text-amber-300">{{ $student->pending_character_name }}</p>
                            <p class="text-xs text-amber-100/50">{{ $student->characterClassLabel() }} · {{ $student->avatarMeta($student->pending_character_avatar)['name'] ?? 'Avatar' }}</p>
                        </div>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <form method="POST" action="{{ route('teacher.characters.approve', [$class, $student]) }}">
                            @csrf
                            <button class="game-btn !py-1 !px-3 text-sm" type="submit">Aprovar</button>
                        </form>
                        <form method="POST" action="{{ route('teacher.characters.reject', [$class, $student]) }}" class="flex gap-2">
                            @csrf
                            <input class="game-input !py-1 !w-40" name="reason" placeholder="Motivo (opcional)" maxlength="200">
                            <button class="game-btn-ghost !py-1 !px-3 text-sm" type="submit">Recusar</button>
                        </form>
                    </div>
                </div>
            @empty
                <p class="text-purple-200/60 text-sm">Nenhum pedido pendente.</p>
            @endforelse
        </div>
    </div>
