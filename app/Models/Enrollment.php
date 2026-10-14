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
        'seals',
        'arena_wins',
        'arena_losses',
        'behavior_score',
        'equipped_frame_id',
        'equipped_accessory_id',
        'equipped_title_id',
        'equipped_aura_id',
        'equipped_pet_id',
    ];

    protected function casts(): array
    {
        return [
            'ranking_visible' => 'boolean',
            'xp' => 'integer',
            'glory' => 'integer',
            'relics' => 'integer',
            'seals' => 'integer',
            'arena_wins' => 'integer',
            'arena_losses' => 'integer',
            'behavior_score' => 'float',
            'equipped_frame_id' => 'integer',
            'equipped_accessory_id' => 'integer',
            'equipped_title_id' => 'integer',
            'equipped_aura_id' => 'integer',
            'equipped_pet_id' => 'integer',
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

    public function pets(): HasMany
    {
        return $this->hasMany(EnrollmentPet::class);
    }

    public function equippedPet(): BelongsTo
    {
        return $this->belongsTo(EnrollmentPet::class, 'equipped_pet_id');
    }

    public function equippedFrameCosmetic(): BelongsTo
    {
        return $this->belongsTo(EnrollmentCosmetic::class, 'equipped_frame_id');
    }

    public function equippedAccessoryCosmetic(): BelongsTo
    {
        return $this->belongsTo(EnrollmentCosmetic::class, 'equipped_accessory_id');
    }

    public function equippedTitleCosmetic(): BelongsTo
    {
        return $this->belongsTo(EnrollmentCosmetic::class, 'equipped_title_id');
    }

    public function equippedAuraCosmetic(): BelongsTo
    {
        return $this->belongsTo(EnrollmentCosmetic::class, 'equipped_aura_id');
    }

    public function listings(): HasMany
    {
        return $this->hasMany(CosmeticListing::class);
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
        $title = $this->relationLoaded('equippedTitleCosmetic')
            ? $this->equippedTitleCosmetic
            : $this->equippedTitleCosmetic()->first();

        if ($title) {
            $item = CosmeticCatalog::item($title->item_key);

            return $item['label'] ?? $item['name'] ?? null;
        }

        return null;
    }

    public function isCosmeticEquipped(int $enrollmentCosmeticId): bool
    {
        return in_array($enrollmentCosmeticId, [
            (int) ($this->equipped_frame_id ?? 0),
            (int) ($this->equipped_accessory_id ?? 0),
            (int) ($this->equipped_title_id ?? 0),
            (int) ($this->equipped_aura_id ?? 0),
        ], true);
    }

    public function clearEquippedCosmeticId(int $enrollmentCosmeticId): void
    {
        foreach (['equipped_frame_id', 'equipped_accessory_id', 'equipped_title_id', 'equipped_aura_id'] as $column) {
            if ((int) ($this->{$column} ?? 0) === $enrollmentCosmeticId) {
                $this->{$column} = null;
            }
        }
    }
}
