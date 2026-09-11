@php
    $detailed = $detailed ?? false;
    $rules = \App\Services\ArenaCombatService::rulebook();
    $gradePct = (int) round($rules['grade_weight'] * 100);
    $attendancePct = (int) round($rules['attendance_weight'] * 100);
    $teamPct = (int) round($rules['team_weight'] * 100);
    $luckPct = (int) round($rules['luck_range'] * 100);
    $gearCapPct = (int) round($rules['gear_cap'] * 100);
@endphp
<div class="rounded-lg border border-amber-400/20 bg-amber-950/20 p-4 text-sm text-amber-100/75 space-y-3">
    <h2 class="font-display text-lg text-amber-200">Como o vencedor é definido</h2>
    @if($detailed)
        <p>
            Todos começam com a mesma base
            (HP {{ $rules['base_hp'] }}, ATK {{ $rules['base_atk'] }}, DEF {{ $rules['base_def'] }}, SPD {{ $rules['base_spd'] }}).
            A <strong class="text-amber-200">classe de personagem</strong> mistura
            {{ (int) round($rules['class_influence'] * 100) }}% do próprio estilo nisso
            (Guerreiro aguenta mais, Mago acerta mais forte, Clérigo se cura mais).
            Esses números são multiplicados pelo <strong class="text-amber-200">poder</strong>, que soma bônus independentes:
        </p>
        <ul class="list-disc pl-5 space-y-1">
            <li>
                <strong class="text-amber-200">Notas</strong> (provas e comportamento): até +{{ $gradePct }}%.
                Chamada e guilda <em>não</em> entram nesta média — cada uma tem bônus próprio.
            </li>
            <li>
                <strong class="text-amber-200">Frequência</strong> (chamada): até +{{ $attendancePct }}% se houver chamada lançada.
                Faltar enfraquece; estar presente ajuda, mas não substitui estudar.
            </li>
            <li>
                <strong class="text-amber-200">Nota da equipe</strong> (guilda): até +{{ $teamPct }}% se houver atividade de equipe.
                A nota da guilda vale para todos os integrantes. Sem guilda, esse bônus fica zerado.
            </li>
            <li>
                <strong class="text-amber-200">Itens equipados</strong> da loja: cada peça dá um bônus de poder.
                No catálogo, a raridade define o valor
                (comum {{ number_format($rules['gear_by_rarity']['common'] * 100, 1) }}%,
                incomum {{ number_format($rules['gear_by_rarity']['uncommon'] * 100, 1) }}%,
                raro {{ number_format($rules['gear_by_rarity']['rare'] * 100, 1) }}%,
                épico {{ number_format($rules['gear_by_rarity']['epic'] * 100, 1) }}%).
                Itens cadastrados na loja podem ter um poder próprio.
                No máximo +{{ $gearCapPct }}% no total. Só vale o que está equipado.
            </li>
            <li>
                <strong class="text-amber-200">Nível de XP</strong> (Iniciante a Mestre) multiplica o resultado final.
            </li>
            <li>
                <strong class="text-amber-200">Sorte da arena</strong>: cada lutador recebe uma variação oculta de até ±{{ $luckPct }}% no poder.
                Alunos não veem esses números — assim o duelo não nasce decidido.
            </li>
        </ul>
        <p>
            Quem tem mais SPD age primeiro. Cada turno: golpe (ATK menos metade da DEF do rival, com variação)
            ou cura (suporte recupera mais vezes). O combate dura no máximo {{ $rules['max_turns'] }} turnos.
        </p>
        <p>
            Vence quem zerar o HP do outro. Se o tempo acabar, vence quem tiver mais HP.
            Empate de HP: ganha quem tiver mais SPD. Empate total: o desafiante leva.
        </p>
        <p>
            Nas <strong class="text-cyan-200">batalhas de guildas</strong>, os lutadores elegíveis são emparelhados por poder
            (mais forte vs mais forte). Quem sobrar luta de novo contra o mais fraco do outro lado.
            Vence a guilda com mais vitórias; em empate, soma-se o HP dos vencedores; empate total favorece a desafiante.
            Cada guilda só pode resolver <strong class="text-cyan-200">uma batalha por dia</strong>.
            A sorte da arena nestas guerras é menor (±6%) do que no duelo 1v1 (±12%).
        </p>
        <p class="text-xs text-amber-100/50">
            {{ \App\Models\GameCurrency::label('glory') }} e {{ \App\Models\GameCurrency::label('relics') }} da vitória não mudam a média nem o XP. Esta receita completa fica só com o professor.
            Os alunos leem a versão de sala em
            <a href="{{ route('arena.rules') }}" class="text-amber-200 underline">Regras da arena</a>.
        </p>
    @else
        <p>
            Notas, presença, guilda, itens da loja e o nível de XP mandam no poder.
            A <strong class="text-amber-200">classe</strong> muda o estilo da luta
            (mais vida, mais dano, mais velocidade ou mais cura) — não substitui a prova.
        </p>
        <p>
            Cada duelo também tem um <strong class="text-amber-200">fator de sorte</strong> da arena:
            ninguém chega sabendo quem vai ganhar.
        </p>
        <p>
            Vence quem derrubar o rival, ou quem tiver mais vida se o tempo acabar.
        </p>
        <p>
            Nas <strong class="text-cyan-200">batalhas de guildas</strong>, todos os lutadores elegíveis entram:
            mais forte vs mais forte; quem sobrar enfrenta o mais fraco do outro lado.
            Cada guilda só pode resolver <strong class="text-cyan-200">uma batalha por dia</strong>.
            A sorte nestas guerras é menor (±6%) do que no duelo individual (±12%).
        </p>
        <p>
            <a href="{{ route('arena.rules') }}" class="text-amber-200 underline font-semibold">Ler as regras completas da arena</a>
        </p>
    @endif
</div>
