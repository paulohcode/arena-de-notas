<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeasonClassRite extends Model
{
    protected $fillable = [
        'season_id',
        'class_id',
        'status',
        'boss_max_hp',
        'boss_hp',
        'marks_applied',
        'seed',
        'log',
        'outcome',
        'opened_at',
        'resolved_at',
    ];

    public const STATUS_PENDING = 'pending';

    public const STATUS_OPEN = 'open';

    public const STATUS_RESOLVED = 'resolved';

    public const OUTCOME_BROKEN = 'broken';

    public const OUTCOME_RESISTED = 'resisted';

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => self::STATUS_PENDING,
        'boss_max_hp' => 0,
        'boss_hp' => 0,
        'marks_applied' => 0,
    ];

    protected function casts(): array
    {
        return [
            'boss_max_hp' => 'integer',
            'boss_hp' => 'integer',
            'marks_applied' => 'integer',
            'seed' => 'integer',
            'log' => 'array',
            'opened_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }

    public function isResolved(): bool
    {
        return $this->status === self::STATUS_RESOLVED;
    }

    public function wasBroken(): bool
    {
        return $this->outcome === self::OUTCOME_BROKEN;
    }
}
