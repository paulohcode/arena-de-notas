<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BossVigil extends Model
{
    protected $fillable = [
        'season_id',
        'class_id',
        'student_id',
        'status',
        'seed',
        'log',
        'won',
        'mark_earned',
        'glory',
        'resolved_at',
    ];

    public const STATUS_RESOLVED = 'resolved';

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => self::STATUS_RESOLVED,
        'won' => false,
        'mark_earned' => false,
        'glory' => 0,
    ];

    protected function casts(): array
    {
        return [
            'seed' => 'integer',
            'log' => 'array',
            'won' => 'boolean',
            'mark_earned' => 'boolean',
            'glory' => 'integer',
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

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function isResolved(): bool
    {
        return $this->status === self::STATUS_RESOLVED;
    }
}
