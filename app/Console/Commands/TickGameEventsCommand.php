<?php

namespace App\Console\Commands;

use App\Services\ChallengeExpiryService;
use App\Services\DuelService;
use App\Services\GameEventService;
use Illuminate\Console\Command;

class TickGameEventsCommand extends Command
{
    protected $signature = 'game-events:tick';

    protected $description = 'Avança perguntas ao vivo, encerra janelas expiradas e limpa desafios pendentes';

    public function handle(
        GameEventService $events,
        ChallengeExpiryService $expiry,
        DuelService $duels,
    ): int {
        $count = $events->tick();
        $expired = $expiry->expirePending();
        $quotaPenalties = $duels->settleWeeklyQuotas();
        $events->markTicked();

        $this->info("Eventos atualizados: {$count}");
        $this->info("Desafios expirados: {$expired}");
        $this->info("Multas de cota semanal: {$quotaPenalties}");

        return self::SUCCESS;
    }
}
