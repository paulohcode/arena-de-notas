@extends('layouts.game')

@section('title', 'Arena do reino — '.$area->name)

@section('content')
<div class="flex flex-wrap items-start justify-between gap-4 mb-8">
    <div>
        <a href="{{ route('admin.areas.index') }}" class="game-btn-ghost text-sm mb-2 inline-block">← Reinos</a>
        <p class="hero-kicker !mb-1">Mediação</p>
        <h1 class="font-display text-4xl text-amber-300">{{ $area->emblemIcon() }} Arena do reino</h1>
        <p class="text-amber-100/65 mt-1">
            {{ $area->name }} · duelos entre turmas por {{ \App\Models\GameCurrency::label('auras') }} (+{{ \App\Models\RealmDuel::AURA_WIN }}/+{{ \App\Models\RealmDuel::AURA_LOSS }})
            · Status:
            <span class="{{ $area->isRealmArenaOpen() ? 'text-emerald-300' : 'text-rose-300' }}">
                {{ $area->isRealmArenaOpen() ? 'aberta' : 'fechada' }}
            </span>
            · Espera {{ $area->realmArenaCooldownLabel() }}
            · Limite {{ $area->realmArenaDailyLimit() }}/dia
        </p>
    </div>
    <div>
        @if($area->isRealmArenaOpen())
            <form method="POST" action="{{ route('admin.areas.arena.close', $area) }}">
                @csrf
                <button class="game-btn-ghost" type="submit">Fechar arena entre turmas</button>
            </form>
        @else
            <form method="POST" action="{{ route('admin.areas.arena.open', $area) }}">
                @csrf
                <button class="game-btn" type="submit">Abrir arena entre turmas</button>
            </form>
        @endif
    </div>
</div>

<form method="POST" action="{{ route('admin.areas.arena.update', $area) }}" class="game-card p-5 mb-6 space-y-4">
    @csrf
    @method('PUT')
    <div>
        <h2 class="font-display text-xl text-amber-200">Configurações da arena entre turmas</h2>
        <p class="text-sm text-amber-100/60 mt-1">Define se o reino aceita duelos entre turmas, a espera entre desafios e o limite diário de {{ \App\Models\GameCurrency::label('auras') }}.</p>
    </div>
    <div class="grid md:grid-cols-3 gap-4">
        <label class="block">
            <span class="text-sm">Estado</span>
            <select class="game-select mt-1 w-full" name="realm_arena_open" required>
                <option value="1" @selected((string) old('realm_arena_open', $area->isRealmArenaOpen() ? '1' : '0') === '1')>Aberta</option>
                <option value="0" @selected((string) old('realm_arena_open', $area->isRealmArenaOpen() ? '1' : '0') === '0')>Fechada</option>
            </select>
        </label>
        <label class="block">
            <span class="text-sm">Espera entre desafios (minutos)</span>
            <input class="game-input mt-1 w-full" type="number" name="realm_arena_cooldown_minutes" min="0" max="10080" required
                value="{{ old('realm_arena_cooldown_minutes', $area->realmArenaCooldownMinutes()) }}">
            <span class="block text-xs text-amber-100/50 mt-1">0 = sem espera. Ex.: 30 para meia hora.</span>
        </label>
        <label class="block">
            <span class="text-sm">Duelos do reino no dia</span>
            <input class="game-input mt-1 w-full" type="number" name="realm_arena_daily_limit" min="1" max="50" required
                value="{{ old('realm_arena_daily_limit', $area->realmArenaDailyLimit()) }}">
            <span class="block text-xs text-amber-100/50 mt-1">Cada aluno pode concluir no máximo esse número por dia.</span>
        </label>
    </div>
    <button class="game-btn" type="submit">Salvar configurações</button>
</form>

<div class="game-card p-5 mb-6">
    <h2 class="font-display text-xl text-amber-200 mb-3">Turmas do reino</h2>
    <div class="space-y-2">
        @forelse($area->classes as $class)
            <div class="flex flex-wrap items-center justify-between gap-2 py-2 border-b border-purple-900/40 text-sm">
                <span>
                    <a class="hover:text-amber-300" href="{{ route('teacher.classes.show', ['schoolClass' => $class, 'tab' => 'arena']) }}">{{ $class->name }}</a>
                    <span class="text-amber-100/45">· {{ $class->teacher?->name ?? 'sem professor' }}</span>
                </span>
                <span class="{{ $class->isArenaOpen() ? 'text-emerald-300' : 'text-rose-300' }}">
                    Arena {{ $class->isArenaOpen() ? 'aberta' : 'fechada' }}
                </span>
            </div>
        @empty
            <p class="text-sm text-purple-200/60">Nenhuma turma neste reino.</p>
        @endforelse
    </div>
</div>

<div class="grid lg:grid-cols-2 gap-6 mb-6">
    <div class="game-card p-5">
        <h2 class="font-display text-xl text-violet-200 mb-3">Desafios pendentes</h2>
        @forelse($pendingDuels as $duel)
            <div class="flex flex-wrap items-start justify-between gap-3 py-3 border-b border-purple-900/40">
                <div class="min-w-0 text-sm">
                    <p>
                        {{ $duel->challenger->arenaName() ?: $duel->challenger->name }}
                        <span class="text-amber-100/45">({{ $duel->challengerClass->name }})</span>
                        →
                        {{ $duel->opponent->arenaName() ?: $duel->opponent->name }}
                        <span class="text-amber-100/45">({{ $duel->opponentClass->name }})</span>
                    </p>
                    <p class="text-xs text-amber-100/45 mt-1">Enviado {{ $duel->created_at?->diffForHumans() }}</p>
                </div>
                <form method="POST" action="{{ route('admin.areas.arena.cancel', [$area, $duel]) }}"
                    onsubmit="return confirm('Cancelar este desafio pendente?')">
                    @csrf
                    <button type="submit" class="game-btn-ghost !py-1 !px-3 text-xs text-rose-300">Cancelar</button>
                </form>
            </div>
        @empty
            <p class="text-sm text-purple-200/60">Nenhum desafio aguardando aceite.</p>
        @endforelse
    </div>

    <div class="game-card p-5">
        <h2 class="font-display text-xl text-violet-200 mb-3">Saldo de {{ \App\Models\GameCurrency::label('auras') }}</h2>
        @forelse($auraLeaders as $row)
            <div class="flex justify-between py-2 border-b border-purple-900/40 text-sm">
                <span>{{ $row->student?->arenaName() ?: $row->student?->name }}</span>
                <span class="text-violet-300">{{ \App\Models\GameCurrency::format('auras', $row->auras) }}</span>
            </div>
        @empty
            <p class="text-sm text-purple-200/60">Ninguém acumulou {{ \App\Models\GameCurrency::label('auras') }} ainda.</p>
        @endforelse
    </div>
</div>

<div class="game-card p-5">
    <h2 class="font-display text-xl text-amber-200 mb-3">Histórico recente</h2>
    @forelse($recentDuels as $duel)
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
        <p class="text-sm text-purple-200/60">Sem duelos registrados neste reino.</p>
    @endforelse
</div>
@endsection
