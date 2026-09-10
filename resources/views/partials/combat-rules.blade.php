@php
    $rules = \App\Services\ArenaCombatService::rulebook();
    $gradePct = (int) round($rules['grade_weight'] * 100);
    $attendancePct = (int) round($rules['attendance_weight'] * 100);
    $teamPct = (int) round($rules['team_weight'] * 100);
    $gearCapPct = (int) round($rules['gear_cap'] * 100);
@endphp
<div class="rounded-lg border border-amber-400/20 bg-amber-950/20 p-4 text-sm text-amber-100/75 space-y-3">
    <h2 class="font-display text-lg text-amber-200">Como o vencedor é definido</h2>
    <p>
        Todos começam com os mesmos atributos
        (HP {{ $rules['base_hp'] }}, ATK {{ $rules['base_atk'] }}, DEF {{ $rules['base_def'] }}, SPD {{ $rules['base_spd'] }}).
        A <strong class="text-amber-200">classe de personagem</strong> é só visual — não muda o combate.
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
            <strong class="text-amber-200">Itens equipados</strong> da loja: cada peça dá um pouco de poder conforme a raridade
            (comum {{ number_format($rules['gear_by_rarity']['common'] * 100, 1) }}%,
            incomum {{ number_format($rules['gear_by_rarity']['uncommon'] * 100, 1) }}%,
            raro {{ number_format($rules['gear_by_rarity']['rare'] * 100, 1) }}%,
            épico {{ number_format($rules['gear_by_rarity']['epic'] * 100, 1) }}%),
            no máximo +{{ $gearCapPct }}% no total. Só vale o que está equipado.
        </li>
        <li>
            <strong class="text-amber-200">Nível de XP</strong> (Iniciante a Mestre) multiplica o resultado final.
        </li>
    </ul>
    <p>
        Quem tem mais SPD age primeiro. Cada turno: golpe (ATK menos metade da DEF do rival, com variação)
        ou cura (a mesma chance para todos). O combate dura no máximo {{ $rules['max_turns'] }} turnos.
    </p>
    <p>
        Vence quem zerar o HP do outro. Se o tempo acabar, vence quem tiver mais HP.
        Empate de HP: ganha quem tiver mais SPD. Empate total: o desafiante leva.
    </p>
    <p class="text-xs text-amber-100/50">
        Glória e Relíquias da vitória não mudam a média nem o XP.
    </p>
</div>
