<div class="grid lg:grid-cols-3 gap-6 mb-8">
    <div class="game-card game-card-glow {{ $student->characterAuraClass() }} p-6 lg:col-span-2">
        @include('partials.class-fx', ['characterClass' => $student->character_class])
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <p class="text-sm text-purple-200/70">Nota atual</p>
                <p class="font-display text-6xl text-cyan-300" data-count-to="{{ $average }}" data-count-from="0">{{ number_format($average, 1) }}</p>
            </div>
            <div class="text-right">
                <p class="font-display text-2xl text-amber-300">{{ $level['name'] }}</p>
                <p class="text-sm text-purple-200/70">{{ $enrollment?->xp ?? 0 }} XP</p>
                <p class="text-sm">Jogador: {{ $position ? $position.'º' : '—' }} · Guilda: {{ $guildPosition ? $guildPosition.'º' : '—' }}</p>
            </div>
        </div>
        <div class="mt-4">
            <div class="flex justify-between text-xs text-purple-200/60 mb-1">
                <span>Próximo nível{{ $level['next'] ? ' ('.$level['next'].' XP)' : '' }}</span>
                <span>{{ $level['progress'] }}%</span>
            </div>
            <div class="xp-track"><div class="xp-fill" data-xp-fill="{{ $level['progress'] }}"></div></div>
        </div>

        <div class="mt-5 grid grid-cols-2 sm:grid-cols-4 gap-3">
            <div class="rounded-xl border border-purple-900/50 bg-purple-950/40 px-3 py-2">
                <p class="text-[10px] uppercase tracking-wide text-purple-200/60">{{ \App\Models\GameCurrency::label('relics') }}</p>
                <p class="text-sm text-cyan-300 mt-1">{{ \App\Models\GameCurrency::format('relics', $enrollment?->relics ?? 0) }}</p>
            </div>
            <div class="rounded-xl border border-purple-900/50 bg-purple-950/40 px-3 py-2">
                <p class="text-[10px] uppercase tracking-wide text-purple-200/60">{{ \App\Models\GameCurrency::label('seals') }}</p>
                <p class="text-sm text-emerald-300 mt-1">{{ \App\Models\GameCurrency::format('seals', $enrollment?->seals ?? 0) }}</p>
            </div>
            <div class="rounded-xl border border-purple-900/50 bg-purple-950/40 px-3 py-2">
                <p class="text-[10px] uppercase tracking-wide text-purple-200/60">{{ \App\Models\GameCurrency::label('auras') }}</p>
                <p class="text-sm text-violet-300 mt-1">{{ \App\Models\GameCurrency::format('auras', $auras ?? 0) }}</p>
            </div>
            <div class="rounded-xl border border-purple-900/50 bg-purple-950/40 px-3 py-2">
                <p class="text-[10px] uppercase tracking-wide text-purple-200/60">{{ \App\Models\GameCurrency::label('glory') }}</p>
                <p class="text-sm text-amber-200 mt-1">{{ \App\Models\GameCurrency::format('glory', $enrollment?->glory ?? 0) }}</p>
            </div>
        </div>

        @php
            $wins = (int) ($enrollment?->arena_wins ?? 0);
            $losses = (int) ($enrollment?->arena_losses ?? 0);
        @endphp
        <div class="mt-3 grid grid-cols-3 gap-3">
            <div class="rounded-xl border border-emerald-900/40 bg-emerald-950/30 px-3 py-2 text-center">
                <p class="text-[10px] uppercase tracking-wide text-emerald-200/60">Vitórias</p>
                <p class="font-display text-xl text-emerald-300 mt-1">{{ $wins }}</p>
            </div>
            <div class="rounded-xl border border-rose-900/40 bg-rose-950/30 px-3 py-2 text-center">
                <p class="text-[10px] uppercase tracking-wide text-rose-200/60">Derrotas</p>
                <p class="font-display text-xl text-rose-300 mt-1">{{ $losses }}</p>
            </div>
            <div class="rounded-xl border border-purple-900/50 bg-purple-950/40 px-3 py-2 text-center">
                <p class="text-[10px] uppercase tracking-wide text-purple-200/60">Total</p>
                <p class="font-display text-xl text-cyan-300 mt-1">{{ $wins + $losses }}</p>
            </div>
        </div>

        @unless($viewerIsTeacher)
            <label class="block mt-4">
                <span class="text-xs uppercase tracking-wide text-purple-200/70">Chamada</span>
                <select class="game-select mt-1 w-full" data-attendance-dropdown aria-label="Chamada">
                    @forelse($attendanceRecords as $record)
                        <option value="{{ $record->id }}">
                            {{ $record->session->held_on->format('d/m/Y') }} · {{ $record->statusLabel() }}
                        </option>
                    @empty
                        <option value="">Nenhuma chamada ainda</option>
                    @endforelse
                </select>
            </label>
        @endunless
    </div>

    <div class="game-card p-6">
        @if($team)
            <p class="text-4xl">{{ $team->emblemIcon() }}</p>
            <h2 class="font-display text-2xl text-amber-200 mt-2">{{ $team->name }}</h2>
            <p class="text-purple-200/70">{{ $viewerIsTeacher ? 'A guilda está em' : 'Vocês estão em' }} {{ $guildPosition }}º no Hall</p>
            @unless($viewerIsTeacher)
                <a href="{{ route('ranking.guild', [$class, $team]) }}" class="text-xs text-amber-200/70 hover:text-amber-200 underline mt-2 inline-block">Ver a guilda</a>
            @endunless
            @include('partials.guild-mission-alerts')
        @else
            <h2 class="font-display text-2xl text-amber-200">Sem guilda</h2>
            <p class="text-purple-200/70">{{ $viewerIsTeacher ? 'Este aluno ainda não está em uma equipe.' : 'O professor ainda não te colocou em uma equipe.' }}</p>
        @endif
        @if($viewerIsTeacher)
            <p class="text-sm text-purple-200/70 mt-4">
                Ranking público:
                <span class="{{ $enrollment?->ranking_visible ? 'text-emerald-300' : 'text-amber-200' }}">
                    {{ $enrollment?->ranking_visible ? 'nome visível' : 'nome oculto' }}
                </span>
            </p>
        @else
            <form method="POST" action="{{ route('student.privacy') }}" class="mt-4">
                @csrf
                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" name="ranking_visible" value="1" @checked($enrollment?->ranking_visible) onchange="this.form.submit()">
                    Mostrar meu nome e nota no ranking
                </label>
            </form>
            <label class="flex items-center gap-2 text-sm mt-3 text-purple-200/70">
                <input type="checkbox" data-sound-toggle> Som da arena
            </label>
            <a href="{{ route('student.arena.index') }}" class="game-btn !py-1 !px-3 text-sm mt-4 inline-block">Arena de batalha</a>
            <a href="{{ route('student.arena.realm.index') }}" class="game-btn-ghost !py-1 !px-3 text-sm mt-2 inline-block">Arena entre turmas</a>
        @endif
        <a href="{{ route('ranking.show', $class) }}" class="text-xs text-amber-200/70 hover:text-amber-200 underline mt-4 inline-block">Ver ranking da turma</a>
    </div>
</div>

<div class="grid lg:grid-cols-2 gap-6 mb-8">
    <div class="game-card p-6">
        <h2 class="font-display text-xl text-amber-200 mb-3">Composição da nota</h2>
        @if($viewerIsTeacher)
            <p class="text-sm text-purple-200/70 mb-4">A média ponderada é a soma de (nota × peso) dividida pela soma dos pesos. Ajustes entram depois.</p>
        @endif
        @foreach($breakdown as $line)
            <div class="flex justify-between gap-3 py-2 border-b border-purple-900/40">
                <span>
                    {{ $line['label'] }}
                    @if($line['weight'])
                        <span class="text-purple-300/50 text-xs">peso {{ $line['weight'] }}</span>
                    @endif
                    @if($viewerIsTeacher && $line['kind'] === 'individual' && ! $line['graded'])
                        <span class="block text-xs text-amber-200/70">sem lançamento · usa nota padrão</span>
                    @endif
                </span>
                <span class="text-right {{ $line['kind'] === 'adjust' && $line['score'] < 0 ? 'text-rose-300' : 'text-cyan-300' }}">
                    @if($viewerIsTeacher && $line['kind'] !== 'adjust')
                        <span class="block text-sm">{{ number_format($line['score'], 1) }} × {{ $line['weight'] }} = {{ number_format($line['contribution'], 1) }}</span>
                    @else
                        {{ number_format($line['score'], 1) }}
                    @endif
                </span>
            </div>
        @endforeach
        @if($viewerIsTeacher)
            <div class="mt-4 space-y-1 text-sm">
                <div class="flex justify-between gap-3 text-purple-200/70">
                    <span>Soma (nota × peso)</span>
                    <span>{{ number_format($averageBreakdown['weighted_sum'], 1) }}</span>
                </div>
                <div class="flex justify-between gap-3 text-purple-200/70">
                    <span>Soma dos pesos</span>
                    <span>{{ $averageBreakdown['weight_sum'] }}</span>
                </div>
                <div class="flex justify-between gap-3 text-purple-200/70">
                    <span>Média ponderada</span>
                    <span>{{ number_format($averageBreakdown['weighted_average'], 1) }}</span>
                </div>
                @if($averageBreakdown['adjustments'] != 0.0)
                    <div class="flex justify-between gap-3 {{ $averageBreakdown['adjustments'] < 0 ? 'text-rose-300' : 'text-emerald-300' }}">
                        <span>Ajustes</span>
                        <span>{{ sprintf('%+0.1f', $averageBreakdown['adjustments']) }}</span>
                    </div>
                @endif
                <div class="flex justify-between gap-3 pt-2 border-t border-purple-900/40 font-semibold text-cyan-300">
                    <span>Média final</span>
                    <span>{{ number_format($averageBreakdown['average'], 1) }}</span>
                </div>
            </div>
        @endif
    </div>
    <div class="game-card p-6">
        <h2 class="font-display text-xl text-amber-200 mb-3">Extrato</h2>
        @forelse($entries as $entry)
            <div class="flex justify-between py-2 text-sm border-b border-purple-900/40">
                <span>{{ $entry->activity?->name ?? $entry->reason }}</span>
                <span class="{{ ($entry->type === 'penalty' || ($entry->delta ?? 0) < 0) ? 'text-rose-300' : 'text-emerald-300' }}">
                    {{ $entry->type === 'activity' ? number_format($entry->raw_score, 1) : sprintf('%+0.1f', $entry->delta) }}
                </span>
            </div>
        @empty
            <p class="text-purple-200/60">Nenhum lançamento ainda.</p>
        @endforelse
    </div>
</div>

<div class="game-card p-6 mb-8">
    <div class="flex flex-wrap items-center justify-between gap-3 mb-3">
        <h2 class="font-display text-xl text-amber-200">Itens comprados</h2>
        @unless($viewerIsTeacher)
            <a href="{{ route('student.shop.index') }}" class="game-btn-ghost !py-1 !px-3 text-sm">Abrir loja</a>
        @endunless
    </div>
    @php $ownedItems = $ownedItems ?? []; @endphp
    @if(count($ownedItems) === 0)
        <p class="text-purple-200/60">Nenhum item comprado nesta turma.</p>
    @else
        <div class="space-y-5">
            @foreach($ownedItems as $slot => $items)
                <div>
                    <h3 class="text-xs uppercase tracking-wide text-purple-200/70 mb-2">{{ \App\Support\CosmeticCatalog::SLOTS[$slot] ?? $slot }}</h3>
                    <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-3">
                        @foreach($items as $item)
                            <article class="game-card p-3 flex items-center gap-3 {{ $item['equipped'] ? 'border-emerald-400/40' : '' }}">
                                @include('partials.shop-item-art', [
                                    'icon' => $item['icon'],
                                    'rarity' => $item['rarity'],
                                    'css' => $item['css'] ?? null,
                                    'slot' => $item['slot'],
                                    'size' => 'sm',
                                ])
                                <div class="min-w-0">
                                    <p class="font-display text-amber-100 truncate">{{ $item['name'] }}</p>
                                    <p class="text-xs text-purple-200/60">{{ $item['rarity_label'] }}</p>
                                    @if($item['equipped'])
                                        <p class="text-xs text-emerald-300 mt-1">Equipado</p>
                                    @endif
                                </div>
                            </article>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>

<div class="game-card p-6 mb-8">
    <h2 class="font-display text-xl text-amber-200 mb-3">Medalhas</h2>
    <div class="flex flex-wrap gap-3">
        @forelse($badges as $badge)
            <div class="px-3 py-2 rounded-xl border border-amber-300/30 bg-amber-300/10">{{ $badge->icon }} {{ $badge->name }}</div>
        @empty
            <p class="text-purple-200/60">{{ $viewerIsTeacher ? 'Nenhuma medalha nesta turma ainda.' : 'Nenhuma medalha ainda. Jogue a temporada.' }}</p>
        @endforelse
    </div>
</div>
