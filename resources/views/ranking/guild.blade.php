@extends('layouts.game')

@section('title', $team->name.' — Hall das Guildas')

@section('content')
<a href="{{ route('ranking.show', $class) }}" class="game-btn-ghost mb-6 inline-flex">← Voltar ao Hall</a>

<div class="game-card p-8 mb-6">
    <div class="flex items-center gap-4">
        <div class="text-6xl">{{ $team->emblemIcon() }}</div>
        <div>
            <p class="text-amber-200/70 text-sm">{{ $position }}º no Hall</p>
            <h1 class="font-display text-4xl text-amber-300">{{ $team->name }}</h1>
            <p class="text-cyan-300 text-2xl mt-1">{{ number_format($score, 1) }} / 100</p>
        </div>
    </div>
    <div class="xp-track mt-4"><div class="xp-fill" data-xp-fill="{{ $score }}"></div></div>
</div>

<div class="grid md:grid-cols-2 gap-6">
    <div class="game-card p-6">
        <h2 class="font-display text-xl text-amber-200 mb-4">Membros</h2>
        <ul class="space-y-2">
            @foreach($members as $member)
                <li>
                    @if($member['profile_url'])
                        <a
                            href="{{ $member['profile_url'] }}"
                            class="hover:text-amber-300 transition-colors underline decoration-amber-300/40 underline-offset-2"
                            title="Ver ficha de {{ $member['name'] }}"
                        >{{ $member['hidden'] ? '👤 '.$member['name'] : '⚔️ '.$member['name'] }}</a>
                    @else
                        {{ $member['hidden'] ? '👤 '.$member['name'] : '⚔️ '.$member['name'] }}
                    @endif
                    @if(! $member['hidden'] && $member['character_name'])
                        <span class="text-amber-300"> · {{ $member['character_name'] }}</span>
                    @endif
                </li>
            @endforeach
        </ul>
    </div>
    <div class="game-card p-6">
        <h2 class="font-display text-xl text-amber-200 mb-4">Últimos lançamentos</h2>
        <ul class="space-y-2 text-sm">
            @forelse($entries as $entry)
                <li class="flex justify-between gap-3">
                    <span>{{ $entry->activity?->name ?? $entry->reason }}</span>
                    <span class="{{ $entry->delta >= 0 ? 'text-emerald-300' : 'text-rose-300' }}">
                        {{ $entry->type === 'activity' ? number_format($entry->raw_score, 1) : sprintf('%+0.1f', $entry->delta) }}
                    </span>
                </li>
            @empty
                <li class="text-purple-200/60">Nenhum lançamento ainda.</li>
            @endforelse
        </ul>
    </div>
</div>
@endsection
