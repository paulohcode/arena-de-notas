<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SchoolClass extends Model
{
    protected $table = 'classes';

    protected $fillable = [
        'teacher_id',
        'area_id',
        'name',
        'year',
        'score_mode',
        'team_grade_weight',
        'behavior_grade_weight',
        'arena_open',
    ];

    protected function casts(): array
    {
        return [
            'arena_open' => 'boolean',
            'team_grade_weight' => 'integer',
            'behavior_grade_weight' => 'integer',
        ];
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class, 'class_id');
    }

    public function students(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'enrollments', 'class_id', 'student_id')
            ->withPivot(['ranking_visible', 'xp', 'glory', 'arena_wins', 'arena_losses', 'behavior_score'])
            ->withTimestamps();
    }

    public function teams(): HasMany
    {
        return $this->hasMany(Team::class, 'class_id');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class, 'class_id');
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class, 'class_id');
    }

    public function duels(): HasMany
    {
        return $this->hasMany(Duel::class, 'class_id');
    }

    public function isArenaOpen(): bool
    {
        return (bool) $this->arena_open;
    }

    public function isRising(): bool
    {
        return $this->score_mode === 'up_from_zero';
    }

    public function defaultScore(): float
    {
        return $this->isRising() ? 0.0 : 100.0;
    }
}
