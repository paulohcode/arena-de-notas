    <div class="space-y-6">
        @if($eventsTickStale)
            <div class="game-card p-4 border-amber-400/50 text-amber-100" role="alert">
                <p class="font-semibold text-amber-200">Agendador parado</p>
                <p class="text-sm mt-1">Quizzes ao vivo e desafios pendentes dependem de <code class="text-amber-200">php artisan schedule:run</code> a cada minuto no servidor. Ele não rodou recentemente — o evento ao vivo pode travar na sala.</p>
            </div>
        @endif
        <form method="POST" action="{{ route('teacher.events.store', $class) }}" class="game-card p-5 space-y-4">
            @csrf
            <h2 class="font-display text-xl text-amber-200">Evento da turma (item exclusivo)</h2>
            <p class="text-sm text-purple-200/70">Competição por ranking. O 1º lugar (mais acertos, depois mais rápido) ganha o item.</p>
            <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-3">
                <label class="space-y-1 sm:col-span-2">
                    <span class="text-xs uppercase tracking-wide text-purple-200/70">Título</span>
                    <input class="game-input" name="title" value="{{ old('title') }}" required maxlength="120">
                </label>
                <label class="space-y-1">
                    <span class="text-xs uppercase tracking-wide text-purple-200/70">Modo</span>
                    <select class="game-select" name="mode">
                        <option value="window">Janela de tempo</option>
                        <option value="live">Ao vivo</option>
                    </select>
                </label>
                <label class="space-y-1">
                    <span class="text-xs uppercase tracking-wide text-purple-200/70">Segundos / pergunta</span>
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
            <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-3 border-t border-purple-800/40 pt-4">
                <label class="space-y-1">
                    <span class="text-xs uppercase tracking-wide text-purple-200/70">Item — nome</span>
                    <input class="game-input" name="prize_name" value="{{ old('prize_name') }}" required maxlength="60">
                </label>
                <label class="space-y-1">
                    <span class="text-xs uppercase tracking-wide text-purple-200/70">Slot</span>
                    <select class="game-select" name="prize_slot">
                        @foreach(\App\Support\CosmeticCatalog::SLOTS as $slot => $label)
                            <option value="{{ $slot }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="space-y-1">
                    <span class="text-xs uppercase tracking-wide text-purple-200/70">Ícone</span>
                    <input class="game-input" name="prize_icon" value="{{ old('prize_icon', '🏆') }}" required maxlength="32">
                </label>
                <label class="space-y-1">
                    <span class="text-xs uppercase tracking-wide text-purple-200/70">Raridade</span>
                    <select class="game-select" name="prize_rarity">
                        @foreach(\App\Support\CosmeticCatalog::RARITIES as $rarity => $label)
                            <option value="{{ $rarity }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
            </div>
            @include('partials.quiz-questions-form')
            <button class="game-btn" type="submit">Criar evento da turma</button>
        </form>

        @if($class->area_id)
            <form method="POST" action="{{ route('teacher.events.realm.store', $class) }}" class="game-card p-5 space-y-4">
                @csrf
                <h2 class="font-display text-xl text-amber-200">Evento do reino (item exclusivo)</h2>
                <p class="text-sm text-purple-200/70">Todas as turmas de {{ $class->area?->name }}. Vence o melhor aluno individual.</p>
                <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-3">
                    <label class="space-y-1 sm:col-span-2">
                        <span class="text-xs uppercase tracking-wide text-purple-200/70">Título</span>
                        <input class="game-input" name="title" required maxlength="120">
                    </label>
                    <label class="space-y-1">
                        <span class="text-xs uppercase tracking-wide text-purple-200/70">Modo</span>
                        <select class="game-select" name="mode">
                            <option value="window">Janela de tempo</option>
                            <option value="live">Ao vivo</option>
                        </select>
                    </label>
                    <label class="space-y-1">
                        <span class="text-xs uppercase tracking-wide text-purple-200/70">Segundos / pergunta</span>
                        <input class="game-input" type="number" name="question_seconds" value="30" min="5" max="300" required>
                    </label>
                </div>
                <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-3">
                    <label class="space-y-1">
                        <span class="text-xs uppercase tracking-wide text-purple-200/70">Item — nome</span>
                        <input class="game-input" name="prize_name" required maxlength="60">
                    </label>
                    <label class="space-y-1">
                        <span class="text-xs uppercase tracking-wide text-purple-200/70">Slot</span>
                        <select class="game-select" name="prize_slot">
                            @foreach(\App\Support\CosmeticCatalog::SLOTS as $slot => $label)
                                <option value="{{ $slot }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="space-y-1">
                        <span class="text-xs uppercase tracking-wide text-purple-200/70">Ícone</span>
                        <input class="game-input" name="prize_icon" value="👑" required>
                    </label>
                    <label class="space-y-1">
                        <span class="text-xs uppercase tracking-wide text-purple-200/70">Raridade</span>
                        <select class="game-select" name="prize_rarity">
                            @foreach(\App\Support\CosmeticCatalog::RARITIES as $rarity => $label)
                                <option value="{{ $rarity }}" @selected($rarity === 'epic')>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                </div>
                @include('partials.quiz-questions-form')
                <button class="game-btn" type="submit">Criar evento do reino</button>
            </form>
        @endif

        <div class="game-card p-5">
            <h2 class="font-display text-xl text-amber-200 mb-4">Eventos da turma</h2>
            @forelse(($class->gameEvents ?? collect())->where('kind', 'class') as $event)
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-purple-900/40 py-3">
                    <div>
                        <p class="font-semibold text-amber-100">{{ $event->title }}</p>
                        <p class="text-xs text-purple-200/60">{{ $event->modeLabel() }} · {{ $event->statusLabel() }} · {{ $event->questions->count() }} perguntas</p>
                    </div>
                    <div class="flex gap-2">
                        <a class="game-btn-ghost text-xs" href="{{ route('teacher.events.show', [$class, $event]) }}">Ver</a>
                        @if($event->isLiveMode() && in_array($event->status, ['draft', 'scheduled'], true))
                            <form method="POST" action="{{ route('teacher.events.start', [$class, $event]) }}">@csrf<button class="game-btn text-xs" type="submit">Iniciar</button></form>
                        @endif
                        @if(! $event->isClosed())
                            <form method="POST" action="{{ route('teacher.events.close', [$class, $event]) }}">@csrf<button class="game-btn-ghost text-xs" type="submit">Encerrar</button></form>
                        @endif
                    </div>
                </div>
            @empty
                <p class="text-sm text-purple-200/60">Nenhum evento da turma ainda.</p>
            @endforelse
        </div>

        @if($realmEvents->isNotEmpty())
            <div class="game-card p-5">
                <h2 class="font-display text-xl text-amber-200 mb-4">Eventos do reino</h2>
                @foreach($realmEvents as $event)
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-purple-900/40 py-3">
                        <div>
                            <p class="font-semibold text-amber-100">{{ $event->title }}</p>
                            <p class="text-xs text-purple-200/60">{{ $event->statusLabel() }} · prêmio {{ $event->prizeItem?->name }}</p>
                        </div>
                        <div class="flex gap-2">
                            <a class="game-btn-ghost text-xs" href="{{ route('teacher.events.show', [$class, $event]) }}">Ver</a>
                            @if($event->isLiveMode() && in_array($event->status, ['draft', 'scheduled'], true))
                                <form method="POST" action="{{ route('teacher.events.start', [$class, $event]) }}">@csrf<button class="game-btn text-xs" type="submit">Iniciar</button></form>
                            @endif
                            @if(! $event->isClosed())
                                <form method="POST" action="{{ route('teacher.events.close', [$class, $event]) }}">@csrf<button class="game-btn-ghost text-xs" type="submit">Encerrar</button></form>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
