<?php

namespace App\Models;

use App\Support\ForgeCatalog;
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
        'forge_level',
        'combat_bonus',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'forge_level' => 1,
    ];

    protected function casts(): array
    {
        return [
            'enrollment_id' => 'integer',
            'pet_id' => 'integer',
            'forge_level' => 'integer',
            'combat_bonus' => 'float',
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

    public function effectiveCombatBonus(): float
    {
        if ($this->combat_bonus !== null) {
            return round((float) $this->combat_bonus, 4);
        }

        return round((float) ($this->pet?->combat_bonus ?? 0), 4);
    }

    public function forgeLevelLabel(): string
    {
        return ForgeCatalog::levelLabel((int) $this->forge_level);
    }

    /**
     * @return array{id: int, pet_id: int, custom_name: string, aura_color: string, aura_label: string, species_name: string, rarity: string, rarity_label: string, sprite_key: string, gif_url: ?string, combat_bonus: float, combat_bonus_percent: float, forge_level: int, forge_level_label: string, equipped: bool, listed_price: ?int}
     */
    public function toInventoryArray(bool $equipped = false, ?int $listedPrice = null): array
    {
        $pet = $this->pet;
        $bonus = $this->effectiveCombatBonus();

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
            'combat_bonus' => $bonus,
            'combat_bonus_percent' => round($bonus * 100, 2),
            'forge_level' => (int) $this->forge_level,
            'forge_level_label' => $this->forgeLevelLabel(),
            'equipped' => $equipped,
            'listed_price' => $listedPrice,
        ];
    }
}
