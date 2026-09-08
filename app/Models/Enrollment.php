<?php

namespace App\Models;

use App\Support\CosmeticCatalog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Enrollment extends Model
{
    protected $fillable = [
        'class_id',
        'student_id',
        'ranking_visible',
        'xp',
        'glory',
        'relics',
        'arena_wins',
        'arena_losses',
        'behavior_score',
        'equipped_frame',
        'equipped_accessory',
        'equipped_title',
        'equipped_aura',
    ];

    protected function casts(): array
    {
        return [
            'ranking_visible' => 'boolean',
            'xp' => 'integer',
            'glory' => 'integer',
            'relics' => 'integer',
            'arena_wins' => 'integer',
            'arena_losses' => 'integer',
            'behavior_score' => 'float',
        ];
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function cosmetics(): HasMany
    {
        return $this->hasMany(EnrollmentCosmetic::class);
    }

    /**
     * @return array{frame: ?string, accessory: ?string, title: ?string, aura: ?string}
     */
    public function cosmeticLoadout(): array
    {
        return CosmeticCatalog::loadoutFromEnrollment($this);
    }

    public function ownsCosmetic(string $itemKey): bool
    {
        return $this->cosmetics()->where('item_key', $itemKey)->exists();
    }

    public function equippedTitleLabel(): ?string
    {
        return CosmeticCatalog::titleLabel($this->equipped_title);
    }
}
