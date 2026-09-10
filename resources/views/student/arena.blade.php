@extends('layouts.game')

@section('title', 'Arena — '.$class->name)

@section('content')
<div class="flex flex-wrap items-start justify-between gap-4 mb-6 reveal">
    <div>
        <p class="hero-kicker !mb-1">Campo de duelos</p>
        <h1 class="font-display text-4xl text-amber-300">Arena da turma</h1>
        <p class="text-amber-100/60 mt-1">{{ $class->name }} · Glória {{ $enrollment?->glory ?? 0 }} · Relíquias {{ $enrollment?->relics ?? 0 }} · Selos {{ $enrollment?->seals ?? 0 }} · V{{ $enrollment?->arena_wins ?? 0 }}–D{{ $enrollment?->arena_losses ?? 0 }}</p>
    </div>
    <div class="flex flex-wrap gap-2">
        <a class="game-btn-ghost" href="{{ route('student.shop.index') }}">Loja</a>
        <a class="game-btn-ghost" href="{{ route('student.dashboard') }}">Voltar à ficha</a>
    </div>
</div>

@if(! $class->isArenaOpen())
    <div class="game-card p-5 mb-6 border-amber-400/30">
        <p class="text-amber-200 font-semibold">A arena está fechada</p>
        <p class="text-sm text-amber-100/60">O professor precisa abrir a arena antes dos duelos.</p>
    </div>
@elseif(! $student->hasApprovedPersona())
    <div class="game-card p-5 mb-6 border-amber-400/30">
        <p class="text-amber-200 font-semibold">Personagem incompleto</p>
        <p class="text-sm text-amber-100/60 mb-3">Avatar e nome de jogo precisam estar aprovados para duelar.</p>
        <a class="game-btn !py-1 !px-3 text-sm" href="{{ route('student.character.edit') }}">Criar personagem</a>
    </div>
@endif

@if($pendingIncoming->isNotEmpty())
    <div class="game-card p-5 mb-6 space-y-4">
        <h2 class="font-display text-xl text-amber-200">Desafios recebidos</h2>
        @foreach($pendingIncoming as $duel)
            <div class="flex flex-wrap items-center justify-between gap-3 py-3 border-b border-purple-900/40">
                <div class="flex items-center gap-3 min-w-0">
                    @include('partials.player-avatar', ['student' => $duel->challenger, 'size' => 'sm'])
                    <div class="min-w-0">
                        <p class="font-semibold truncate">{{ $duel->challenger->name }}
                            @if($duel->challenger->arenaName())
                                <span class="text-amber-300"> · {{ $duel->challenger->arenaName() }}</span>
                            @endif
                        </p>
                        <p class="text-xs text-amber-100/50">{{ $duel->challenger->characterClassLabel() }}</p>
                    </div>
                </div>
                <div class="flex gap-2">
                    <form method="POST" action="{{ route('student.arena.accept', $duel) }}">
                        @csrf
                        <button class="game-btn !py-1 !px-3 text-sm" type="submit">Aceitar</button>
                    </form>
                    <form method="POST" action="{{ route('student.arena.decline', $duel) }}">
                        @csrf
                        <button class="game-btn-ghost !py-1 !px-3 text-sm" type="submit">Recusar</button>
                    </form>
                </div>
            </div>
        @endforeach
    </div>
@endif

@if($pendingGuildIncoming->isNotEmpty())
    <div class="game-card p-5 mb-6 space-y-4 border-cyan-400/20">
        <h2 class="font-display text-xl text-cyan-200">Desafios de guilda recebidos</h2>
        @foreach($pendingGuildIncoming as $battle)
            <div class="flex flex-wrap items-center justify-between gap-3 py-3 border-b border-purple-900/40">
                <div class="min-w-0">
                    <p class="font-semibold truncate">
                        {{ $battle->challengerTeam->emblemIcon() }} {{ $battle->challengerTeam->name }}
                    </p>
                    <p class="text-xs text-amber-100/50">
                        Enviado por {{ $battle->challenger->arenaName() ?: $battle->challenger->name }}
                    </p>
                </div>
                <div class="flex gap-2">
                    <form method="POST" action="{{ route('student.arena.guild.accept', $battle) }}">
                        @csrf
                        <button class="game-btn !py-1 !px-3 text-sm" type="submit">Aceitar</button>
                    </form>
                    <form method="POST" action="{{ route('student.arena.guild.decline', $battle) }}">
                        @csrf
                        <button class="game-btn-ghost !py-1 !px-3 text-sm" type="submit">Recusar</button>
                    </form>
                </div>
            </div>
        @endforeach
    </div>
@endif

<div class="grid lg:grid-cols-2 gap-6 mb-8">
    <div class="game-card p-5">
        <div class="flex items-center justify-between gap-3 mb-3">
            <h2 class="font-display text-xl text-amber-200">Desafiar colega</h2>
            <p class="text-xs text-amber-100/50">{{ $resolvedToday }}/{{ $dailyLimit }} duelos hoje</p>
        </div>

        <div class="mb-4 rounded-lg border border-amber-400/25 bg-amber-950/20 p-3 text-xs text-amber-100/75 space-y-1">
            <p>A mesma pessoa só pode ser desafiada <strong class="text-amber-200">uma vez por dia</strong>.</p>
            @if($cooldownMinutes > 0)
                <p>Espere <strong class="text-amber-200">{{ $cooldownLabel }}</strong> entre um desafio e outro.</p>
            @else
                <p>Não há espera entre um desafio e outro.</p>
            @endif
            <p>Limite de <strong class="text-amber-200">{{ $dailyLimit }} {{ $dailyLimit === 1 ? 'duelo resolvido' : 'duelos resolvidos' }}</strong> por dia.</p>
        </div>

        @forelse($opponents as $peer)
            <div class="flex flex-wrap items-center justify-between gap-3 py-3 border-b border-purple-900/40">
                <div class="flex items-center gap-3 min-w-0">
                    @include('partials.player-avatar', ['student' => $peer, 'size' => 'sm'])
                    <div class="min-w-0">
                        <p class="font-semibold truncate">{{ $peer->name }}
                            @if($peer->arenaName())
                                <span class="text-amber-300"> · {{ $peer->arenaName() }}</span>
                            @endif
                        </p>
                        <p class="text-xs text-amber-100/50">{{ $peer->characterClassLabel() }}</p>
                    </div>
                </div>
                @if(! empty($opponentNotices[$peer->id]))
                    <p class="text-xs text-amber-200/90 max-w-56 text-right leading-snug">{{ $opponentNotices[$peer->id] }}</p>
                @elseif($canChallenge)
                    <form method="POST" action="{{ route('student.arena.challenge') }}">
                        @csrf
                        <input type="hidden" name="opponent_id" value="{{ $peer->id }}">
                        <button class="game-btn-ghost !py-1 !px-3 text-sm" type="submit">Desafiar</button>
                    </form>
                @endif
            </div>
        @empty
            <p class="text-sm text-purple-200/60">Nenhum colega com personagem aprovado ainda.</p>
        @endforelse

        @if($pendingOutgoing->isNotEmpty())
            <div class="mt-6">
                <h3 class="text-sm uppercase tracking-wide text-purple-200/70 mb-2">Seus desafios enviados</h3>
                @foreach($pendingOutgoing as $duel)
                    <a href="{{ route('student.arena.show', $duel) }}" class="block text-sm py-1 text-cyan-300/90 hover:text-cyan-200 underline">
                        Aguardando {{ $duel->opponent->arenaName() ?: $duel->opponent->name }} — abrir sala de espera
                    </a>
                @endforeach
            </div>
        @endif
    </div>

    <div class="space-y-6">
        <div class="game-card p-5">
            <h2 class="font-display text-xl text-amber-200 mb-3">Hall da Arena</h2>
            @forelse($hall as $row)
                <div class="flex justify-between py-2 border-b border-purple-900/40 text-sm">
                    <span>
                        {{ $row['position'] }}º
                        {{ $row['student']->name }}
                        @if($row['student']->arenaName())
                            <span class="text-amber-300"> · {{ $row['student']->arenaName() }}</span>
                        @endif
                        @include('partials.cosmetic-title', ['student' => $row['student']])
                    </span>
                    <span class="text-cyan-300">{{ $row['glory'] }} Glória · {{ $row['arena_wins'] }}V</span>
                </div>
            @empty
                <p class="text-sm text-purple-200/60">Ninguém conquistou Glória ainda (ou ninguém está visível no ranking).</p>
            @endforelse
        </div>

        <div class="game-card p-5">
            <h2 class="font-display text-xl text-amber-200 mb-3">Seus duelos recentes</h2>
            @forelse($history as $duel)
                <a href="{{ route('student.arena.show', $duel) }}" class="flex justify-between py-2 border-b border-purple-900/40 text-sm hover:text-amber-200">
                    <span>
                        vs {{ $duel->otherParticipant($student)?->arenaName() ?: $duel->otherParticipant($student)?->name }}
                    </span>
                    <span class="{{ $duel->winner_id === $student->id ? 'text-emerald-300' : 'text-rose-300' }}">
                        {{ $duel->winner_id === $student->id ? 'Vitória' : 'Derrota' }}
                    </span>
                </a>
            @empty
                <p class="text-sm text-purple-200/60">Nenhum duelo resolvido ainda.</p>
            @endforelse
        </div>
    </div>
</div>

<div class="game-card p-5 mb-8 reveal">
    <div class="flex flex-wrap items-center justify-between gap-3 mb-3">
        <h2 class="font-display text-xl text-cyan-200">Batalha de Guildas</h2>
        @if($ownTeam)
            <p class="text-xs text-amber-100/50">{{ $guildResolvedToday }}/{{ $guildDailyLimit }} batalha hoje · {{ $ownTeam->emblemIcon() }} {{ $ownTeam->name }}</p>
        @endif
    </div>

    <div class="mb-4 rounded-lg border border-cyan-400/25 bg-cyan-950/20 p-3 text-xs text-amber-100/75 space-y-1">
        <p>Cada guilda pode resolver <strong class="text-cyan-200">uma batalha por dia</strong>.</p>
        <p>Todos os lutadores elegíveis entram: mais forte vs mais forte; sobras enfrentam o mais fraco do outro lado.</p>
        <p>Qualquer membro da guilda desafiada pode aceitar ou recusar. Vitória dá +{{ \App\Models\TeamBattle::GLORY_WIN }} Glória/Relíquias para quem lutou.</p>
    </div>

    @if(! $ownTeam)
        <p class="text-sm text-purple-200/60">Você precisa estar em uma guilda para desafiar outra.</p>
    @else
        @forelse($otherGuilds as $guild)
            <div class="flex flex-wrap items-center justify-between gap-3 py-3 border-b border-purple-900/40">
                <div class="min-w-0">
                    <p class="font-semibold truncate">{{ $guild->emblemIcon() }} {{ $guild->name }}</p>
                    <p class="text-xs text-amber-100/50">{{ $guild->members->count() }} {{ $guild->members->count() === 1 ? 'membro' : 'membros' }}</p>
                </div>
                @if(! empty($guildNotices[$guild->id]))
                    <p class="text-xs text-amber-200/90 max-w-64 text-right leading-snug">{{ $guildNotices[$guild->id] }}</p>
                @elseif($canChallengeGuild)
                    <form method="POST" action="{{ route('student.arena.guild.challenge') }}">
                        @csrf
                        <input type="hidden" name="opponent_team_id" value="{{ $guild->id }}">
                        <button class="game-btn-ghost !py-1 !px-3 text-sm" type="submit">Desafiar guilda</button>
                    </form>
                @endif
            </div>
        @empty
            <p class="text-sm text-purple-200/60">Não há outras guildas nesta turma.</p>
        @endforelse

        @if($pendingGuildOutgoing->isNotEmpty())
            <div class="mt-6">
                <h3 class="text-sm uppercase tracking-wide text-purple-200/70 mb-2">Desafios de guilda enviados</h3>
                @foreach($pendingGuildOutgoing as $battle)
                    <a href="{{ route('student.arena.guild.show', $battle) }}" class="block text-sm py-1 text-cyan-300/90 hover:text-cyan-200 underline">
                        Aguardando {{ $battle->opponentTeam->name }} — abrir sala de espera
                    </a>
                @endforeach
            </div>
        @endif

        <div class="mt-6">
            <h3 class="text-sm uppercase tracking-wide text-purple-200/70 mb-2">Batalhas recentes da guilda</h3>
            @forelse($guildHistory as $battle)
                @php
                    $rival = $battle->challenger_team_id === $ownTeam->id ? $battle->opponentTeam : $battle->challengerTeam;
                    $won = $battle->winner_team_id === $ownTeam->id;
                    $score = $battle->log['score'] ?? null;
                @endphp
                <a href="{{ route('student.arena.guild.show', $battle) }}" class="flex justify-between py-2 border-b border-purple-900/40 text-sm hover:text-amber-200">
                    <span>vs {{ $rival->emblemIcon() }} {{ $rival->name }}
                        @if($score)
                            <span class="text-amber-100/40">({{ $score['challenger'] }}–{{ $score['opponent'] }})</span>
                        @endif
                    </span>
                    <span class="{{ $won ? 'text-emerald-300' : 'text-rose-300' }}">
                        {{ $won ? 'Vitória' : 'Derrota' }}
                    </span>
                </a>
            @empty
                <p class="text-sm text-purple-200/60">Nenhuma batalha de guilda resolvida ainda.</p>
            @endforelse
        </div>
    @endif
</div>

<div class="mb-8 reveal">
    @include('partials.combat-rules')
</div>
@endsection
