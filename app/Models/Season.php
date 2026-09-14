<?php

namespace App\Models;

use App\Support\BossArchetypeCatalog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Season extends Model
{
    protected $fillable = [
        'area_id',
        'name',
        'description',
        'boss_archetype',
        'boss_difficulty',
        'vigil_open',
        'created_by',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'boss_difficulty' => BossArchetypeCatalog::DIFFICULTY_NORMAL,
        'vigil_open' => false,
    ];

    protected function casts(): array
    {
        return [
            'vigil_open' => 'boolean',
        ];
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function classes(): BelongsToMany
    {
        return $this->belongsToMany(SchoolClass::class, 'season_classes', 'season_id', 'class_id')
            ->withTimestamps();
    }

    public function vigils(): HasMany
    {
        return $this->hasMany(BossVigil::class);
    }

    public function classRites(): HasMany
    {
        return $this->hasMany(SeasonClassRite::class);
    }

    public function hasBoss(): bool
    {
        return filled($this->boss_archetype) && BossArchetypeCatalog::get($this->boss_archetype) !== null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function bossMeta(): ?array
    {
        return BossArchetypeCatalog::get($this->boss_archetype);
    }

    public function bossDisplayName(): ?string
    {
        $meta = $this->bossMeta();
        if ($meta === null) {
            return null;
        }

        return $meta['name'].' — '.$this->name;
    }

    public function isVigilOpen(): bool
    {
        return $this->hasBoss() && (bool) $this->vigil_open;
    }
}
