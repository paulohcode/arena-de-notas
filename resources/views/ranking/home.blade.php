@extends('layouts.game')

@section('title', 'Mapa dos Reinos — Arena das Notas')

@section('content')
<div class="hero-banner reveal">
    <p class="hero-kicker">Escolha seu reino</p>
    <h1 class="hero-title">Arena das Notas</h1>
    <p class="text-amber-100/70 mt-3 max-w-xl mx-auto">
        @if($canEditMap)
            Arraste os cards para posicionar cada reino no mapa. Um clique abre o território.
        @else
            Cada área de atuação é um reino. Toque no card sobre o mapa e entre no território.
        @endif
    </p>
    <div class="mt-6 flex flex-wrap justify-center gap-3">
        <a href="{{ route('arena.rules') }}" class="game-btn-ghost">Regras da arena</a>
        @guest
            <a href="{{ route('login') }}" class="game-btn">Entrar na arena</a>
        @endguest
    </div>
</div>

<section class="kingdom-map reveal" aria-label="Mapa dos reinos">
    <div class="kingdom-map-frame">
        <div
            class="kingdom-map-surface"
            x-data="kingdomMapEditor"
            data-can-edit="{{ $canEditMap ? '1' : '0' }}"
            x-ref="surface"
        >
            <img
                src="{{ asset('images/kingdom-map.png') }}"
                alt=""
                class="kingdom-map-art"
                aria-hidden="true"
            >
            <div class="kingdom-map-vignette" aria-hidden="true"></div>

            @forelse($areas as $area)
                <a
                    href="{{ $canEditMap ? '#' : route('areas.show', $area) }}"
                    class="kingdom-pin{{ $canEditMap ? ' is-draggable' : '' }}"
                    style="left: {{ $area->map_x }}%; top: {{ $area->map_y }}%; --kingdom-color: {{ $area->color }}"
                    @if($canEditMap)
                        data-href="{{ route('areas.show', $area) }}"
                        data-save-url="{{ route('admin.areas.position', $area) }}"
                        @pointerdown="startDrag($event)"
                        @pointermove="moveDrag($event)"
                        @pointerup="endDrag($event)"
                        @pointercancel="endDrag($event)"
                        @click.prevent
                    @endif
                >
                    <span class="kingdom-pin-card">
                        <span class="kingdom-pin-icon">{{ $area->emblemIcon() }}</span>
                        <span class="kingdom-pin-body">
                            <span class="kingdom-pin-kicker">{{ $canEditMap ? 'Arraste' : 'Reino' }}</span>
                            <span class="kingdom-pin-name">{{ $area->name }}</span>
                            <span class="kingdom-pin-meta">{{ $area->classes_count }} turma(s)</span>
                        </span>
                    </span>
                </a>
            @empty
                <div class="kingdom-map-empty">Nenhum reino ativo no mapa ainda.</div>
            @endforelse
        </div>
    </div>
</section>
@endsection
