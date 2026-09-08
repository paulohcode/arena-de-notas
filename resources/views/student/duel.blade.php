@extends('layouts.game')

@section('title', 'Duelo — '.$class->name)

@php
    $log = $duel->log ?? [];
    $fighters = $log['fighters'] ?? [];
    $challengerSnap = $fighters['challenger'] ?? null;
    $opponentSnap = $fighters['opponent'] ?? null;
    $turns = $log['turns'] ?? [];
    $isPending = $duel->isPending();
    $isResolved = $duel->isResolved() && $challengerSnap && $opponentSnap;

    $battlePayload = $isResolved ? [
        'left' => [
            'id' => $duel->challenger->id,
            'name' => $duel->challenger->name,
            'arena' => $duel->challenger->arenaName(),
            'class' => $challengerSnap['class'],
            'classKey' => (string) ($duel->challenger->character_class ?? ''),
            'icon' => $duel->challenger->avatarIcon(),
            'tone' => $duel->challenger->avatarTone(),
            'maxHp' => (int) $challengerSnap['max_hp'],
            'atk' => (int) $challengerSnap['atk'],
            'def' => (int) $challengerSnap['def'],
            'spd' => (int) $challengerSnap['spd'],
            'power' => $challengerSnap['power'],
        ],
        'right' => [
            'id' => $duel->opponent->id,
            'name' => $duel->opponent->name,
            'arena' => $duel->opponent->arenaName(),
            'class' => $opponentSnap['class'],
            'classKey' => (string) ($duel->opponent->character_class ?? ''),
            'icon' => $duel->opponent->avatarIcon(),
            'tone' => $duel->opponent->avatarTone(),
            'maxHp' => (int) $opponentSnap['max_hp'],
            'atk' => (int) $opponentSnap['atk'],
            'def' => (int) $opponentSnap['def'],
            'spd' => (int) $opponentSnap['spd'],
            'power' => $opponentSnap['power'],
        ],
        'turns' => $turns,
        'winnerId' => (int) $duel->winner_id,
        'viewerId' => (int) $student->id,
        'gloryWin' => (int) $duel->glory_winner,
        'gloryLoss' => (int) $duel->glory_loser,
    ] : null;
@endphp

@section('content')
@if($isPending)
    <div
        class="game-card p-8 text-center max-w-xl mx-auto reveal"
        data-duel-waiting
        x-data="{
            statusUrl: {{ \Illuminate\Support\Js::from($statusUrl) }},
            pulling: false,
            async poll() {
                if (this.pulling) {
                    return;
                }
                this.pulling = true;
                try {
                    const response = await fetch(this.statusUrl, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
                    if (! response.ok) return;
                    const data = await response.json();
                    if (data.redirect) {
                        window.location.href = data.redirect;
                    }
                } catch (e) {}
                finally {
                    this.pulling = false;
                }
            },
            init() {
                this.poll();
                setInterval(() => this.poll(), 5000);
            }
        }"
    >
        <p class="hero-kicker !mb-2">Sala de espera</p>
        <h1 class="font-display text-3xl text-amber-300 mb-3">Aguardando o oponente</h1>
        <div class="flex items-center justify-center gap-6 my-8">
            <div class="text-center">
                @include('partials.player-avatar', ['student' => $duel->challenger, 'enrollment' => $challengerEnrollment ?? null, 'size' => 'lg'])
                <p class="mt-2 font-semibold">{{ $duel->challenger->arenaName() ?: $duel->challenger->name }}</p>
                @include('partials.cosmetic-title', ['enrollment' => $challengerEnrollment ?? null, 'inline' => false])
            </div>
            <p class="font-display text-2xl text-amber-200/50">VS</p>
            <div class="text-center opacity-70">
                @include('partials.player-avatar', ['student' => $duel->opponent, 'enrollment' => $opponentEnrollment ?? null, 'size' => 'lg'])
                <p class="mt-2 font-semibold">{{ $duel->opponent->arenaName() ?: $duel->opponent->name }}</p>
                @include('partials.cosmetic-title', ['enrollment' => $opponentEnrollment ?? null, 'inline' => false])
            </div>
        </div>
        <p class="text-amber-100/65 mb-2">Quando {{ $duel->opponent->arenaName() ?: $duel->opponent->name }} aceitar, o combate abre sozinho nesta tela.</p>
        <p class="text-xs text-cyan-300/70 animate-pulse">Escutando a arena…</p>
        <a class="game-btn-ghost inline-block mt-6" href="{{ route('student.arena.index') }}">Voltar à arena</a>
    </div>
@elseif($isResolved)
    <div
        class="space-y-6"
        x-data="duelBattle({{ \Illuminate\Support\Js::from($battlePayload) }})"
        x-init="start()"
    >
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="hero-kicker !mb-1">Campo de combate</p>
                <h1 class="font-display text-3xl md:text-4xl text-amber-300" x-text="headline"></h1>
                <p class="text-amber-100/60 mt-1">
                    {{ $duel->challenger->arenaName() ?: $duel->challenger->name }}
                    vs
                    {{ $duel->opponent->arenaName() ?: $duel->opponent->name }}
                </p>
            </div>
            <a class="game-btn-ghost" href="{{ route('student.arena.index') }}">Voltar à arena</a>
        </div>

        <div class="duel-stage game-card overflow-hidden">
            <div class="duel-stage__floor" aria-hidden="true"></div>

            <div class="relative z-10 grid grid-cols-[1fr_auto_1fr] items-end gap-2 md:gap-6 px-3 md:px-8 pt-8 pb-6 min-h-[280px] md:min-h-[340px]">
                <div class="text-center" :class="{ 'duel-shake': left.hit, 'duel-heal-flash': left.healed }">
                    <div
                        class="duel-portrait mx-auto mb-3"
                        :style="'--portrait-tone:' + left.tone"
                        :class="{ 'duel-portrait--winner': finished && winnerId === left.id, 'duel-portrait--down': left.hp <= 0 }"
                    >
                        <span class="text-4xl md:text-5xl" x-text="left.icon"></span>
                    </div>
                    <p class="font-semibold text-sm md:text-base truncate" x-text="left.arena || left.name"></p>
                    <p class="text-xs text-amber-100/50" x-text="left.class"></p>
                    <div class="mt-3 max-w-[11rem] mx-auto">
                        <div class="flex justify-between text-[10px] uppercase tracking-wide text-rose-200/70 mb-1">
                            <span>HP</span>
                            <span x-text="left.hp + '/' + left.maxHp"></span>
                        </div>
                        <div class="duel-hp-track">
                            <div class="duel-hp-fill" :style="'width:' + leftPct + '%'"></div>
                        </div>
                    </div>
                    <p class="text-[10px] text-amber-100/40 mt-2">
                        ATK <span x-text="left.atk"></span> · DEF <span x-text="left.def"></span> · SPD <span x-text="left.spd"></span>
                    </p>
                </div>

                <div class="relative self-center w-16 md:w-28 h-24 md:h-32 flex items-center justify-center">
                    <p class="font-display text-xl md:text-2xl text-amber-200/40" x-show="!finished">VS</p>
                    <template x-for="bolt in bolts" :key="bolt.id">
                        <span
                            class="duel-bolt"
                            :class="{
                                'duel-bolt--right': bolt.dir === 'right',
                                'duel-bolt--left': bolt.dir === 'left',
                                'duel-bolt--heal': bolt.kind === 'heal'
                            }"
                            x-text="bolt.kind === 'heal' ? '✚' : '✦'"
                        ></span>
                    </template>
                    <template x-for="float in floats" :key="float.id">
                        <span
                            class="duel-float"
                            :class="{
                                'duel-float--left': float.side === 'left',
                                'duel-float--right': float.side === 'right',
                                'duel-float--heal': float.kind === 'heal',
                                'duel-float--dmg': float.kind === 'dmg'
                            }"
                            x-text="float.text"
                        ></span>
                    </template>
                </div>

                <div class="text-center" :class="{ 'duel-shake': right.hit, 'duel-heal-flash': right.healed }">
                    <div
                        class="duel-portrait mx-auto mb-3"
                        :style="'--portrait-tone:' + right.tone"
                        :class="{ 'duel-portrait--winner': finished && winnerId === right.id, 'duel-portrait--down': right.hp <= 0 }"
                    >
                        <span class="text-4xl md:text-5xl" x-text="right.icon"></span>
                    </div>
                    <p class="font-semibold text-sm md:text-base truncate" x-text="right.arena || right.name"></p>
                    <p class="text-xs text-amber-100/50" x-text="right.class"></p>
                    <div class="mt-3 max-w-[11rem] mx-auto">
                        <div class="flex justify-between text-[10px] uppercase tracking-wide text-rose-200/70 mb-1">
                            <span>HP</span>
                            <span x-text="right.hp + '/' + right.maxHp"></span>
                        </div>
                        <div class="duel-hp-track">
                            <div class="duel-hp-fill" :style="'width:' + rightPct + '%'"></div>
                        </div>
                    </div>
                    <p class="text-[10px] text-amber-100/40 mt-2">
                        ATK <span x-text="right.atk"></span> · DEF <span x-text="right.def"></span> · SPD <span x-text="right.spd"></span>
                    </p>
                </div>
            </div>

            <div class="relative z-10 border-t border-amber-300/15 px-4 py-3 flex flex-wrap items-center justify-between gap-2 bg-black/20">
                <p class="text-sm text-amber-100/70" x-text="statusLine"></p>
                <button type="button" class="game-btn-ghost !py-1 !px-3 text-sm" x-show="!finished" @click="skip()">Pular animação</button>
                <p class="text-sm font-semibold" x-show="finished" x-cloak :class="iWon ? 'text-emerald-300' : 'text-rose-300'" x-text="resultLine"></p>
            </div>
        </div>

        <div class="game-card p-5">
            <h2 class="font-display text-xl text-amber-200 mb-3">Histórico de danos</h2>
            <div class="space-y-1 max-h-72 overflow-y-auto" x-ref="logBox">
                <template x-for="(entry, index) in log" :key="index">
                    <div class="flex items-start gap-3 py-2 border-b border-purple-900/40 text-sm">
                        <span class="text-amber-100/40 w-8 shrink-0" x-text="'#' + (index + 1)"></span>
                        <span class="flex-1" x-text="entry.text"></span>
                        <span
                            class="shrink-0 font-semibold"
                            :class="entry.action === 'heal' ? 'text-emerald-300' : 'text-rose-300'"
                            x-text="entry.action === 'heal' ? ('+' + entry.amount) : ('-' + entry.amount)"
                        ></span>
                    </div>
                </template>
                <p class="text-sm text-purple-200/50 py-2" x-show="log.length === 0">O combate vai começar…</p>
            </div>
        </div>

        <div
            x-show="victoryOpen"
            x-cloak
            x-transition.opacity.duration.400ms
            class="duel-victory class-aura"
            :class="winner.classKey ? ('class-aura--' + winner.classKey) : ''"
            role="dialog"
            aria-modal="true"
            aria-labelledby="duel-winner-name"
            @keydown.escape.window="victoryOpen = false"
        >
            <span class="class-fx" :class="winner.classKey ? ('class-fx--' + winner.classKey) : ''" aria-hidden="true">
                <span></span><span></span><span></span><span></span><span></span><span></span>
                <span></span><span></span><span></span><span></span><span></span><span></span>
            </span>
            <div class="duel-victory__stage">
                <div class="duel-victory__burst" aria-hidden="true"></div>
                <div class="duel-victory__content">
                    <p class="hero-kicker !mb-3">Vencedor da arena</p>
                    <p class="duel-victory__icon" x-text="winner.icon"></p>
                    <h2 id="duel-winner-name" class="duel-victory__name font-display" x-text="winner.arena || winner.name"></h2>
                    <p class="duel-victory__class" x-text="winner.class"></p>
                    <p class="duel-victory__glory" x-text="victoryGloryLine"></p>
                    <button type="button" class="game-btn mt-8" @click="victoryOpen = false">Continuar</button>
                </div>
            </div>
        </div>
    </div>
@else
    <div class="game-card p-5">
        <p class="text-amber-100/70">Este duelo ainda não está disponível.</p>
        <a class="game-btn-ghost inline-block mt-4" href="{{ route('student.arena.index') }}">Voltar à arena</a>
    </div>
@endif
@endsection
