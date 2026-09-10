<?php

namespace App\Console\Commands;

use App\Services\GameEventService;
use Illuminate\Console\Command;

class TickGameEventsCommand extends Command
{
    protected $signature = 'game-events:tick';

    protected $description = 'Avança perguntas ao vivo e encerra janelas de eventos expiradas';

    public function handle(GameEventService $events): int
    {
        $count = $events->tick();
        $this->info("Eventos atualizados: {$count}");

        return self::SUCCESS;
    }
}
