<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

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
        'attendance_grade_weight',
        'arena_open',
        'arena_cooldown_minutes',
        'arena_daily_limit',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'arena_open' => false,
        'arena_cooldown_minutes' => Duel::CHALLENGE_COOLDOWN_MINUTES,
        'arena_daily_limit' => Duel::DAILY_RESOLVED_LIMIT,
    ];

    protected function casts(): array
    {
        return [
            'arena_open' => 'boolean',
            'arena_cooldown_minutes' => 'integer',
            'arena_daily_limit' => 'integer',
            'team_grade_weight' => 'integer',
            'behavior_grade_weight' => 'integer',
            'attendance_grade_weight' => 'integer',
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
            ->withPivot([
                'ranking_visible',
                'xp',
                'glory',
                'relics',
                'seals',
                'arena_wins',
                'arena_losses',
                'behavior_score',
                'equipped_frame',
                'equipped_accessory',
                'equipped_title',
                'equipped_aura',
            ])
            ->withTimestamps();
    }

    public function attendanceSessions(): HasMany
    {
        return $this->hasMany(AttendanceSession::class, 'class_id');
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

    public function teamBattles(): HasMany
    {
        return $this->hasMany(TeamBattle::class, 'class_id');
    }

    public function cosmeticStocks(): HasMany
    {
        return $this->hasMany(ClassCosmeticStock::class, 'class_id');
    }

    public function cosmeticListings(): HasMany
    {
        return $this->hasMany(CosmeticListing::class, 'class_id');
    }

    public function shopItems(): HasMany
    {
        return $this->hasMany(ShopItem::class, 'class_id');
    }

    public function cosmetics(): HasManyThrough
    {
        return $this->hasManyThrough(
            EnrollmentCosmetic::class,
            Enrollment::class,
            'class_id',
            'enrollment_id',
        );
    }

    public function isArenaOpen(): bool
    {
        return (bool) $this->arena_open;
    }

    public function arenaCooldownMinutes(): int
    {
        return max(0, (int) ($this->arena_cooldown_minutes ?? Duel::CHALLENGE_COOLDOWN_MINUTES));
    }

    public function arenaDailyLimit(): int
    {
        return max(1, (int) ($this->arena_daily_limit ?? Duel::DAILY_RESOLVED_LIMIT));
    }

    public function arenaCooldownLabel(): string
    {
        $minutes = $this->arenaCooldownMinutes();

        if ($minutes === 0) {
            return 'sem espera';
        }

        if ($minutes % 60 === 0) {
            $hours = intdiv($minutes, 60);

            return $hours === 1 ? '1 hora' : $hours.' horas';
        }

        return $minutes === 1 ? '1 minuto' : $minutes.' minutos';
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
