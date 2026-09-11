@extends('layouts.game')

@section('title', 'Regras da arena — Arena das Notas')

@section('content')
<div class="hero-banner reveal">
    <p class="hero-kicker">Para projetar em sala</p>
    <h1 class="hero-title">Regras da arena</h1>
    <p class="mt-4 max-w-3xl mx-auto font-display text-2xl md:text-3xl text-amber-200 leading-snug">
        A classe é o <span class="text-cyan-200">estilo</span> da luta.
        A nota é o <span class="text-amber-300">poder</span>.
    </p>
    <p class="text-lg md:text-xl text-amber-100/80 mt-4 max-w-2xl mx-auto leading-relaxed">
        Quem estuda bem vence na maioria das vezes. Escolher Mago ou Guerreiro não substitui a prova.
    </p>
</div>

<section class="grid md:grid-cols-2 gap-4 mb-10 reveal" aria-label="A regra de ouro">
    <div class="game-card p-6 md:p-8 space-y-3">
        <p class="hero-kicker !mb-0">O que te deixa forte</p>
        <h2 class="font-display text-3xl text-amber-300">Poder</h2>
        <p class="text-lg text-amber-100/85 leading-relaxed">
            O combate fica mais forte quando você:
        </p>
        <ol class="space-y-3 text-lg md:text-xl text-amber-50">
            <li><span class="text-amber-300 font-display">1.</span> Tira <strong class="text-amber-200">notas boas</strong> (é o que mais pesa)</li>
            <li><span class="text-amber-300 font-display">2.</span> Sobe de <strong class="text-amber-200">nível de XP</strong></li>
            <li><span class="text-amber-300 font-display">3.</span> Ajuda a <strong class="text-amber-200">guilda</strong> nas missões de equipe</li>
            <li><span class="text-amber-300 font-display">4.</span> Vem às aulas (<strong class="text-amber-200">presença</strong>)</li>
            <li><span class="text-amber-300 font-display">5.</span> Equipa itens da <strong class="text-amber-200">loja</strong></li>
        </ol>
    </div>
    <div class="game-card p-6 md:p-8 space-y-3 border-cyan-400/25">
        <p class="hero-kicker !mb-0 text-cyan-200/80">O que a classe faz</p>
        <h2 class="font-display text-3xl text-cyan-200">Estilo</h2>
        <p class="text-lg md:text-xl text-amber-100/90 leading-relaxed">
            Três partes do lutador são iguais para todo mundo.
            <strong class="text-cyan-200">Só uma parte</strong> vem da sua classe.
        </p>
        <ul class="space-y-3 text-lg md:text-xl text-amber-50">
            <li>Guerreiro <strong class="text-amber-200">aguenta mais</strong></li>
            <li>Mago <strong class="text-amber-200">acerta mais forte</strong></li>
            <li>Ladino <strong class="text-amber-200">age primeiro</strong></li>
            <li>Clérigo <strong class="text-amber-200">se cura mais vezes</strong></li>
        </ul>
        <p class="text-lg text-cyan-100/80 leading-relaxed">
            A classe troca um pouco de vida por dano, ou o contrário.
            Ela <strong class="text-cyan-200">não</strong> ganha da média 90 contra a média 50.
        </p>
    </div>
</section>

<section class="mb-10 reveal" aria-labelledby="classes-title">
    <h2 id="classes-title" class="font-display text-3xl md:text-4xl text-amber-300 mb-2">As classes</h2>
    <p class="text-lg md:text-xl text-amber-100/80 mb-6 max-w-3xl leading-relaxed">
        Escolha o jeito que você quer lutar. O nome verdadeiro continua na ficha.
        O nome de jogo e o avatar só entram na arena depois que o professor aprovar.
    </p>

    <div class="space-y-5">
        @foreach($roles as $role => $group)
            <div class="game-card p-5 md:p-6">
                <div class="mb-4">
                    <p class="text-sm uppercase tracking-wide text-cyan-200/80">{{ $group['label'] }}</p>
                    <p class="font-display text-2xl md:text-3xl text-amber-200">{{ $group['blurb'] }}</p>
                </div>
                <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-3">
                    @foreach($group['classes'] as $key => $meta)
                        <div class="rounded-lg border border-amber-400/20 bg-black/20 p-4 class-aura class-aura--{{ $key }}">
                            @include('partials.class-fx', ['characterClass' => $key])
                            <p class="font-display text-xl text-amber-200 flex items-center gap-2">
                                <span class="rank-badge !min-w-10 !h-10 text-lg" aria-hidden="true">{{ $meta['icon'] }}</span>
                                {{ $meta['name'] }}
                            </p>
                            <p class="text-base md:text-lg text-cyan-100/90 mt-2 leading-snug">{{ $meta['combat_blurb'] }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>
</section>

<section class="mb-10 reveal" aria-labelledby="duelo-title">
    <h2 id="duelo-title" class="font-display text-3xl md:text-4xl text-amber-300 mb-6">Como um duelo acontece</h2>
    <ol class="grid md:grid-cols-2 gap-4">
        <li class="game-card p-6 space-y-2">
            <p class="font-display text-5xl text-amber-300/80">1</p>
            <h3 class="font-display text-2xl text-amber-200">Quem é mais rápido age primeiro</h3>
            <p class="text-lg text-amber-100/80 leading-relaxed">Velocidade decide o primeiro golpe. Arqueiro e Ladino costumam sair na frente.</p>
        </li>
        <li class="game-card p-6 space-y-2">
            <p class="font-display text-5xl text-amber-300/80">2</p>
            <h3 class="font-display text-2xl text-amber-200">Cada um ataca ou se cura</h3>
            <p class="text-lg text-amber-100/80 leading-relaxed">O texto do golpe muda com a classe: o Mago lança feitiço, o Anão martela, o Clérigo canaliza cura.</p>
        </li>
        <li class="game-card p-6 space-y-2">
            <p class="font-display text-5xl text-amber-300/80">3</p>
            <h3 class="font-display text-2xl text-amber-200">Vence quem derrubar o rival</h3>
            <p class="text-lg text-amber-100/80 leading-relaxed">Se o tempo acabar, ganha quem tiver mais vida. Empate de vida: ganha o mais rápido. Empate total: o desafiante leva.</p>
        </li>
        <li class="game-card p-6 space-y-2">
            <p class="font-display text-5xl text-amber-300/80">4</p>
            <h3 class="font-display text-2xl text-amber-200">Tem um fator de sorte</h3>
            <p class="text-lg text-amber-100/80 leading-relaxed">Cada luta treme um pouco. Ninguém chega sabendo o placar. Dois alunos com notas parecidas podem virar o duelo.</p>
        </li>
    </ol>
</section>

<section class="grid md:grid-cols-2 gap-4 mb-10 reveal">
    <div class="game-card p-6 md:p-8 space-y-3">
        <h2 class="font-display text-3xl text-amber-300">O que você ganha</h2>
        <p class="text-lg md:text-xl text-amber-100/90 leading-relaxed">
            Vitória: <strong class="text-amber-200">+{{ $gloryWin }} {{ \App\Models\GameCurrency::label('glory') }}</strong> e {{ \App\Models\GameCurrency::label('relics') }} para a loja.
            Derrota: <strong class="text-amber-200">+{{ $gloryLoss }}</strong> (também conta, só que menos).
        </p>
        <p class="text-lg text-amber-100/80 leading-relaxed">
            Entre turmas, a recompensa é <strong class="text-violet-200">{{ \App\Models\GameCurrency::label('auras') }}</strong>.
            Nas guerras de guilda, quem lutou também ganha {{ \App\Models\GameCurrency::label('glory') }} e {{ \App\Models\GameCurrency::label('relics') }}.
        </p>
    </div>
    <div class="game-card p-6 md:p-8 space-y-3 border-rose-400/30">
        <h2 class="font-display text-3xl text-rose-200">O que a arena não muda</h2>
        <p class="text-lg md:text-xl text-amber-50 leading-relaxed">
            Duelo <strong class="text-rose-200">não altera a média</strong>.
            Duelo <strong class="text-rose-200">não dá XP</strong>.
        </p>
        <p class="text-lg md:text-xl text-amber-100/85 leading-relaxed">
            A ficha acadêmica só muda com prova, trabalho, chamada e comportamento.
            A arena é o palco. A nota continua sendo a regra.
        </p>
    </div>
</section>

<p class="text-center text-lg md:text-xl text-amber-100/70 mb-8 reveal max-w-2xl mx-auto leading-relaxed">
    Resumo: escolha a classe pelo estilo que você curte.
    Para ganhar de verdade, estude, compareça e jogue com a guilda.
</p>
@endsection
