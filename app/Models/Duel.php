<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Duel extends Model
{
    protected $fillable = [
        'class_id',
        'challenger_id',
        'opponent_id',
        'status',
        'seed',
        'log',
        'winner_id',
        'glory_winner',
        'glory_loser',
        'resolved_at',
    ];

    public const STATUS_PENDING = 'pending';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_DECLINED = 'declined';

    public const STATUS_RESOLVED = 'resolved';

    public const STATUS_EXPIRED = 'expired';

    public const GLORY_WIN = 10;

    public const GLORY_LOSS = 2;

    public const DAILY_RESOLVED_LIMIT = 3;

    public const CHALLENGE_COOLDOWN_HOURS = 2;

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

    public function challenger(): BelongsTo
    {
        return $this->belongsTo(User::class, 'challenger_id');
    }

    public function opponent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opponent_id');
    }

    public function winner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'winner_id');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isResolved(): bool
    {
        return $this->status === self::STATUS_RESOLVED;
    }

    public function involves(User $student): bool
    {
        return $this->challenger_id === $student->id || $this->opponent_id === $student->id;
    }

    public function otherParticipant(User $student): ?User
    {
        if ($this->challenger_id === $student->id) {
            return $this->opponent;
        }

        if ($this->opponent_id === $student->id) {
            return $this->challenger;
        }

        return null;
    }
}
