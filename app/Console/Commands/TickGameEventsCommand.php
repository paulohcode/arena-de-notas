<?php

namespace App\Console\Commands;

use App\Services\ChallengeExpiryService;
use App\Services\GameEventService;
use Illuminate\Console\Command;

class TickGameEventsCommand extends Command
{
    protected $signature = 'game-events:tick';

    protected $description = 'Avança perguntas ao vivo, encerra janelas expiradas e limpa desafios pendentes';

    public function handle(GameEventService $events, ChallengeExpiryService $expiry): int
    {
        $count = $events->tick();
        $expired = $expiry->expirePending();
        $events->markTicked();

        $this->info("Eventos atualizados: {$count}");
        $this->info("Desafios expirados: {$expired}");

        return self::SUCCESS;
    }
}
