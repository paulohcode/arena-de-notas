@extends('layouts.game')

@section('title', $firstTime ? 'Crie seu personagem' : 'Avatar e nome de jogo')

@section('content')
<div class="hero-banner reveal">
    <p class="hero-kicker">{{ $firstTime ? 'Criação de personagem' : 'Identidade de combate' }}</p>
    <h1 class="hero-title">{{ $firstTime ? 'Crie seu personagem' : 'Avatar e nome de jogo' }}</h1>
    <p class="text-amber-100/70 mt-3 max-w-xl mx-auto">
        Seu nome verdadeiro continua visível. O nome de jogo e o avatar só entram na arena depois que o professor aprovar.
    </p>
</div>

@if($student->isPersonaPending())
    <div class="game-card p-4 mb-6 border-amber-400/40 text-amber-100 reveal">
        Pedido em análise: <strong class="text-amber-300">{{ $student->pending_character_name }}</strong>.
        Você pode enviar outro enquanto espera.
    </div>
@elseif($student->isPersonaRejected())
    <div class="game-card p-4 mb-6 border-red-400/40 text-red-200 reveal">
        O último pedido foi recusado.
        @if($student->character_rejection_reason)
            Motivo: {{ $student->character_rejection_reason }}
        @endif
    </div>
@endif

<form method="POST" action="{{ route('student.character.update') }}" class="reveal reveal-delay-1 space-y-8">
    @csrf

    <section class="space-y-3">
        <h2 class="font-display text-xl text-amber-200">Nome de jogo</h2>
        <p class="text-sm text-amber-100/60">Como você quer ser chamado na arena. O nome da matrícula não muda.</p>
        <label class="block max-w-md">
            <span class="sr-only">Nome de jogo</span>
            <input class="game-input" name="character_name" value="{{ old('character_name', $characterName) }}"
                   placeholder="Ex: Lobo Noturno" required minlength="2" maxlength="24">
        </label>
        <p class="text-xs text-amber-100/45">2 a 24 caracteres. Letras, números, espaço e hífen.</p>
    </section>

    <section class="space-y-3">
        <h2 class="font-display text-xl text-amber-200">Avatar</h2>
        <div class="grid grid-cols-3 sm:grid-cols-4 lg:grid-cols-6 gap-3">
            @foreach($avatars as $key => $meta)
                <label class="game-card game-card-glow choice-card p-3 cursor-pointer text-center">
                    <input type="radio" name="character_avatar" value="{{ $key }}" class="sr-only"
                           @checked(old('character_avatar', $selectedAvatar) === $key) required>
                    <span class="hero-portrait mx-auto" style="--portrait-tone: {{ $meta['tone'] }}">{{ $meta['icon'] }}</span>
                    <span class="block text-xs text-amber-100/70 mt-2">{{ $meta['name'] }}</span>
                </label>
            @endforeach
        </div>
    </section>

    <section class="space-y-3">
        <h2 class="font-display text-xl text-amber-200">Classe</h2>
        <p class="text-base md:text-lg text-amber-100/80 max-w-2xl leading-relaxed">
            A classe muda o <strong class="text-amber-200">estilo</strong> da luta: mais vida, mais dano, mais velocidade ou mais cura.
            O <strong class="text-amber-200">poder</strong> continua vindo das notas, da presença e da guilda.
            Quem estuda bem vence na maioria das vezes — a classe não substitui a prova.
        </p>
        <p class="mt-4">
            <a href="{{ route('arena.rules') }}" class="game-btn">Ler as regras da arena</a>
        </p>
        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach($classes as $key => $meta)
                <label class="game-card game-card-glow choice-card class-aura class-aura--{{ $key }} p-5 cursor-pointer block">
                    @include('partials.class-fx', ['characterClass' => $key])
                    <input type="radio" name="character_class" value="{{ $key }}" class="sr-only"
                           @checked(old('character_class', $selected) === $key) required>
                    <div class="flex items-start gap-3">
                        <span class="rank-badge !min-w-12 !h-12 text-xl">{{ $meta['icon'] }}</span>
                        <div>
                            <p class="text-[10px] uppercase tracking-wide text-cyan-200/80">{{ \App\Models\User::CHARACTER_ROLES[$meta['role']] }}</p>
                            <p class="font-display text-xl text-amber-200">{{ $meta['name'] }}</p>
                            <p class="text-sm text-amber-100/60 mt-1">{{ $meta['blurb'] }}</p>
                            <p class="text-xs text-cyan-200/75 mt-2">{{ $meta['combat_blurb'] }}</p>
                        </div>
                    </div>
                </label>
            @endforeach
        </div>
    </section>

    <div class="flex flex-wrap gap-3">
        <button class="game-btn" type="submit">
            {{ $firstTime ? 'Enviar para aprovação' : 'Enviar novo pedido' }}
        </button>
        @unless($firstTime)
            <a href="{{ route('student.dashboard') }}" class="game-btn-ghost">Cancelar</a>
        @endunless
    </div>
</form>
@endsection
