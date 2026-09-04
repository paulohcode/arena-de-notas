<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable(['name', 'slug', 'description', 'color', 'emblem', 'map_x', 'map_y', 'is_active'])]
class Area extends Model
{
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
}
