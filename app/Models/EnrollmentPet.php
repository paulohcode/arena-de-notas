<?php

namespace App\Models;

use App\Support\PetCatalog;
use Database\Factories\EnrollmentPetFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class EnrollmentPet extends Model
{
    /** @use HasFactory<EnrollmentPetFactory> */
    use HasFactory;

    protected $fillable = [
        'enrollment_id',
        'pet_id',
        'custom_name',
        'aura_color',
    ];

    protected function casts(): array
    {
        return [
            'enrollment_id' => 'integer',
            'pet_id' => 'integer',
        ];
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function pet(): BelongsTo
    {
        return $this->belongsTo(Pet::class);
    }

    public function listing(): HasOne
    {
        return $this->hasOne(PetListing::class);
    }

    public function auraLabel(): string
    {
        return PetCatalog::AURA_COLORS[$this->aura_color] ?? $this->aura_color;
    }

    /**
     * @return array{id: int, pet_id: int, custom_name: string, aura_color: string, aura_label: string, species_name: string, rarity: string, rarity_label: string, sprite_key: string, gif_url: ?string, combat_bonus: float, combat_bonus_percent: float, equipped: bool, listed_price: ?int}
     */
    public function toInventoryArray(bool $equipped = false, ?int $listedPrice = null): array
    {
        $pet = $this->pet;

        return [
            'id' => $this->id,
            'pet_id' => (int) $this->pet_id,
            'custom_name' => $this->custom_name,
            'aura_color' => $this->aura_color,
            'aura_label' => $this->auraLabel(),
            'species_name' => $pet?->name ?? 'Mascote',
            'rarity' => $pet?->rarity ?? 'common',
            'rarity_label' => $pet?->rarityLabel() ?? 'Comum',
            'sprite_key' => $pet?->spriteKey() ?? 'owl',
            'gif_url' => $pet?->gifUrl(),
            'combat_bonus' => (float) ($pet?->combat_bonus ?? 0),
            'combat_bonus_percent' => $pet?->combatBonusPercent() ?? 0.0,
            'equipped' => $equipped,
            'listed_price' => $listedPrice,
        ];
    }
}
