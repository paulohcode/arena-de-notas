<?php

namespace App\Services;

use App\Models\Duel;
use App\Models\RealmDuel;
use App\Models\TeamBattle;
use App\Models\User;
use App\Notifications\GameAlert;
use App\Support\ArenaUrl;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;

class ChallengeExpiryService
{
    public const PENDING_EXPIRES_HOURS = 24;

    public function expirePending(): int
    {
        $cutoff = now()->subHours(self::PENDING_EXPIRES_HOURS);

        return $this->expireDuels($cutoff)
            + $this->expireTeamBattles($cutoff)
            + $this->expireRealmDuels($cutoff);
    }

    private function expireDuels(DateTimeInterface $cutoff): int
    {
        return (int) DB::transaction(function () use ($cutoff) {
            $duels = Duel::query()
                ->with(['challenger', 'opponent'])
                ->where('status', Duel::STATUS_PENDING)
                ->where('created_at', '<=', $cutoff)
                ->lockForUpdate()
                ->orderBy('id')
                ->get();

            foreach ($duels as $duel) {
                $duel->update([
                    'status' => Duel::STATUS_EXPIRED,
                    'resolved_at' => now(),
                ]);

                $this->notifyExpired(
                    [$duel->challenger, $duel->opponent],
                    'duel_expired',
                    'Desafio expirado',
                    'O duelo ficou pendente demais e expirou. Sem punição.',
                    [
                        'duel_id' => $duel->id,
                        'class_id' => $duel->class_id,
                        'url' => ArenaUrl::route('student.arena.index'),
                    ],
                );
            }

            return $duels->count();
        });
    }

    private function expireTeamBattles(DateTimeInterface $cutoff): int
    {
        return (int) DB::transaction(function () use ($cutoff) {
            $battles = TeamBattle::query()
                ->with(['challengerTeam.members', 'opponentTeam.members'])
                ->where('status', TeamBattle::STATUS_PENDING)
                ->where('created_at', '<=', $cutoff)
                ->lockForUpdate()
                ->orderBy('id')
                ->get();

            foreach ($battles as $battle) {
                $battle->update([
                    'status' => TeamBattle::STATUS_EXPIRED,
                    'resolved_at' => now(),
                ]);

                $recipients = $battle->challengerTeam->members
                    ->concat($battle->opponentTeam->members);

                $this->notifyExpired(
                    $recipients->all(),
                    'guild_battle_expired',
                    'Desafio de guilda expirado',
                    'A batalha de guilda ficou pendente demais e expirou. Sem punição.',
                    [
                        'team_battle_id' => $battle->id,
                        'class_id' => $battle->class_id,
                        'url' => ArenaUrl::route('student.arena.index'),
                    ],
                );
            }

            return $battles->count();
        });
    }

    private function expireRealmDuels(DateTimeInterface $cutoff): int
    {
        return (int) DB::transaction(function () use ($cutoff) {
            $duels = RealmDuel::query()
                ->with(['challenger', 'opponent'])
                ->where('status', RealmDuel::STATUS_PENDING)
                ->where('created_at', '<=', $cutoff)
                ->lockForUpdate()
                ->orderBy('id')
                ->get();

            foreach ($duels as $duel) {
                $duel->update([
                    'status' => RealmDuel::STATUS_EXPIRED,
                    'resolved_at' => now(),
                ]);

                $this->notifyExpired(
                    [$duel->challenger, $duel->opponent],
                    'realm_duel_expired',
                    'Desafio do reino expirado',
                    'O duelo por Aura ficou pendente demais e expirou. Sem punição.',
                    [
                        'realm_duel_id' => $duel->id,
                        'area_id' => $duel->area_id,
                        'url' => ArenaUrl::route('student.arena.realm.index'),
                    ],
                );
            }

            return $duels->count();
        });
    }

    /**
     * @param  iterable<int, User|null>  $recipients
     * @param  array<string, mixed>  $payload
     */
    private function notifyExpired(iterable $recipients, string $type, string $title, string $message, array $payload): void
    {
        $seen = [];

        foreach ($recipients as $recipient) {
            if (! $recipient instanceof User || isset($seen[$recipient->id])) {
                continue;
            }

            $seen[$recipient->id] = true;
            $recipient->notify(new GameAlert($type, $title, $message, $payload));
        }
    }
}
