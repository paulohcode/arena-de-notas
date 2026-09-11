<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RealmDuel extends Model
{
    protected $fillable = [
        'area_id',
        'challenger_class_id',
        'opponent_class_id',
        'challenger_id',
        'opponent_id',
        'status',
        'seed',
        'log',
        'winner_id',
        'aura_winner',
        'aura_loser',
        'resolved_at',
    ];

    public const STATUS_PENDING = 'pending';

    public const STATUS_DECLINED = 'declined';

    public const STATUS_RESOLVED = 'resolved';

    public const STATUS_EXPIRED = 'expired';

    public const AURA_WIN = 10;

    public const AURA_LOSS = 2;

    public const DAILY_RESOLVED_LIMIT = 3;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => self::STATUS_PENDING,
        'aura_winner' => 0,
        'aura_loser' => 0,
    ];

    protected function casts(): array
    {
        return [
            'seed' => 'integer',
            'log' => 'array',
            'aura_winner' => 'integer',
            'aura_loser' => 'integer',
            'resolved_at' => 'datetime',
        ];
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function challengerClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'challenger_class_id');
    }

    public function opponentClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'opponent_class_id');
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

    public function classFor(User $student): ?SchoolClass
    {
        if ($this->challenger_id === $student->id) {
            return $this->challengerClass;
        }

        if ($this->opponent_id === $student->id) {
            return $this->opponentClass;
        }

        return null;
    }
}
