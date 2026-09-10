<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeamBattle extends Model
{
    protected $fillable = [
        'class_id',
        'challenger_team_id',
        'opponent_team_id',
        'challenger_id',
        'accepted_by_id',
        'status',
        'seed',
        'log',
        'winner_team_id',
        'glory_winner',
        'glory_loser',
        'resolved_at',
    ];

    public const STATUS_PENDING = 'pending';

    public const STATUS_DECLINED = 'declined';

    public const STATUS_RESOLVED = 'resolved';

    public const GLORY_WIN = 10;

    public const GLORY_LOSS = 2;

    public const DAILY_RESOLVED_LIMIT = 1;

    /**
     * Sorte da arena nas guerras de guilda (menor que o duelo 1v1).
     */
    public const LUCK_RANGE = 0.06;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => self::STATUS_PENDING,
        'glory_winner' => 0,
        'glory_loser' => 0,
    ];

    protected function casts(): array
    {
        return [
            'seed' => 'integer',
            'log' => 'array',
            'glory_winner' => 'integer',
            'glory_loser' => 'integer',
            'resolved_at' => 'datetime',
        ];
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function challengerTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'challenger_team_id');
    }

    public function opponentTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'opponent_team_id');
    }

    public function winnerTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'winner_team_id');
    }

    public function challenger(): BelongsTo
    {
        return $this->belongsTo(User::class, 'challenger_id');
    }

    public function acceptedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accepted_by_id');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isResolved(): bool
    {
        return $this->status === self::STATUS_RESOLVED;
    }

    public function involvesTeam(Team $team): bool
    {
        return $this->challenger_team_id === $team->id || $this->opponent_team_id === $team->id;
    }

    public function involvesStudent(User $student): bool
    {
        $team = $student->teamInClass($this->schoolClass);

        return $team !== null && $this->involvesTeam($team);
    }

    public function otherTeam(Team $team): ?Team
    {
        if ($this->challenger_team_id === $team->id) {
            return $this->opponentTeam;
        }

        if ($this->opponent_team_id === $team->id) {
            return $this->challengerTeam;
        }

        return null;
    }
}
