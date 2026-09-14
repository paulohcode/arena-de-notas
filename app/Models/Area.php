<?php

namespace App\Models;

use App\Support\ArenaSchedule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Area extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'color',
        'emblem',
        'map_x',
        'map_y',
        'is_active',
        'realm_arena_open',
        'realm_arena_cooldown_minutes',
        'realm_arena_daily_limit',
        'realm_arena_schedule',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'realm_arena_open' => true,
        'realm_arena_cooldown_minutes' => 0,
        'realm_arena_daily_limit' => RealmDuel::DAILY_RESOLVED_LIMIT,
    ];

    /**
     * @var array<string, string>
     */
    public const EMBLEMS = [
        'castle' => '🏰',
        'forge' => '⚒',
        'tower' => '🗼',
        'forest' => '🌲',
        'harbor' => '⚓',
        'mountain' => '⛰',
        'scroll' => '📜',
        'gear' => '⚙',
        'flame' => '🔥',
        'crystal' => '💎',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'map_x' => 'integer',
            'map_y' => 'integer',
            'realm_arena_open' => 'boolean',
            'realm_arena_cooldown_minutes' => 'integer',
            'realm_arena_daily_limit' => 'integer',
            'realm_arena_schedule' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Area $area): void {
            if (blank($area->slug) && filled($area->name)) {
                $area->slug = Str::slug($area->name);
            }
        });
    }

    public function teachers(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    public function classes(): HasMany
    {
        return $this->hasMany(SchoolClass::class);
    }

    public function seasons(): HasMany
    {
        return $this->hasMany(Season::class);
    }

    public function balances(): HasMany
    {
        return $this->hasMany(AreaBalance::class);
    }

    public function realmDuels(): HasMany
    {
        return $this->hasMany(RealmDuel::class);
    }

    public function gameEvents(): HasMany
    {
        return $this->hasMany(GameEvent::class);
    }

    public function cosmeticStocks(): HasMany
    {
        return $this->hasMany(AreaCosmeticStock::class);
    }

    public function shopItems(): HasMany
    {
        return $this->hasMany(ShopItem::class);
    }

    public function emblemIcon(): string
    {
        return self::EMBLEMS[$this->emblem] ?? '🏰';
    }

    /**
     * @param  Builder<Area>  $query
     * @return Builder<Area>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function isRealmArenaOpen(): bool
    {
        return $this->realmArenaToday()['open'];
    }

    public function realmArenaCooldownMinutes(): int
    {
        return $this->realmArenaToday()['cooldown_minutes'];
    }

    public function realmArenaDailyLimit(): int
    {
        return $this->realmArenaToday()['daily_limit'];
    }

    public function realmArenaCooldownLabel(): string
    {
        return ArenaSchedule::cooldownLabel($this->realmArenaCooldownMinutes());
    }

    public function hasRealmArenaSchedule(): bool
    {
        return ArenaSchedule::hasSchedule($this->realm_arena_schedule);
    }

    /**
     * @return array<int, array{open: bool, cooldown_minutes: int, daily_limit: int}>
     */
    public function realmArenaWeek(): array
    {
        return ArenaSchedule::week(
            $this->realm_arena_schedule,
            (bool) $this->realm_arena_open,
            $this->fallbackRealmArenaCooldownMinutes(),
            $this->fallbackRealmArenaDailyLimit(),
        );
    }

    /**
     * @return array{open: bool, cooldown_minutes: int, daily_limit: int}
     */
    public function realmArenaToday(): array
    {
        return ArenaSchedule::forToday(
            $this->realm_arena_schedule,
            (bool) $this->realm_arena_open,
            $this->fallbackRealmArenaCooldownMinutes(),
            $this->fallbackRealmArenaDailyLimit(),
        );
    }

    private function fallbackRealmArenaCooldownMinutes(): int
    {
        return max(0, (int) ($this->realm_arena_cooldown_minutes ?? 0));
    }

    private function fallbackRealmArenaDailyLimit(): int
    {
        return max(1, (int) ($this->realm_arena_daily_limit ?? RealmDuel::DAILY_RESOLVED_LIMIT));
    }
}
