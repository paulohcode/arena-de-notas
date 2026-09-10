<?php

namespace App\Models;

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
        return (bool) $this->realm_arena_open;
    }

    public function realmArenaCooldownMinutes(): int
    {
        return max(0, (int) ($this->realm_arena_cooldown_minutes ?? 0));
    }

    public function realmArenaDailyLimit(): int
    {
        return max(1, (int) ($this->realm_arena_daily_limit ?? RealmDuel::DAILY_RESOLVED_LIMIT));
    }

    public function realmArenaCooldownLabel(): string
    {
        $minutes = $this->realmArenaCooldownMinutes();

        if ($minutes === 0) {
            return 'sem espera';
        }

        if ($minutes % 60 === 0) {
            $hours = intdiv($minutes, 60);

            return $hours === 1 ? '1 hora' : $hours.' horas';
        }

        return $minutes === 1 ? '1 minuto' : $minutes.' minutos';
    }
}
