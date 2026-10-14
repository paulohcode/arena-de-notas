<?php

namespace App\Models;

use App\Support\CosmeticCatalog;
use App\Support\ForgeCatalog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class EnrollmentCosmetic extends Model
{
    protected $fillable = [
        'enrollment_id',
        'item_key',
        'forge_level',
        'combat_bonus',
        'forge_tone',
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
            'forge_level' => 'integer',
            'combat_bonus' => 'float',
        ];
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function listing(): HasOne
    {
        return $this->hasOne(CosmeticListing::class);
    }

    public function effectiveCombatBonus(): float
    {
        if ($this->combat_bonus !== null) {
            return round((float) $this->combat_bonus, 4);
        }

        return CosmeticCatalog::combatBonusForKey($this->item_key);
    }

    public function visualTone(): ?string
    {
        if (filled($this->forge_tone)) {
            return $this->forge_tone;
        }

        $item = CosmeticCatalog::item($this->item_key);

        return $item['css'] ?? null;
    }

    public function forgeLevelLabel(): string
    {
        return ForgeCatalog::levelLabel((int) $this->forge_level);
    }

    /**
     * @return array{
     *     id: int,
     *     item_key: string,
     *     slot: string,
     *     name: string,
     *     rarity: string,
     *     rarity_label: string,
     *     icon: ?string,
     *     css: ?string,
     *     label: ?string,
     *     forge_level: int,
     *     forge_level_label: string,
     *     combat_bonus: float,
     *     combat_bonus_percent: float,
     *     equipped: bool,
     *     listed_price: ?int,
     *     tradable: bool
     * }
     */
    public function toInventoryArray(bool $equipped = false, ?int $listedPrice = null): array
    {
        $item = CosmeticCatalog::item($this->item_key) ?? [
            'slot' => 'accessory',
            'name' => $this->item_key,
            'rarity' => 'common',
            'icon' => null,
            'css' => null,
            'label' => null,
        ];

        $bonus = $this->effectiveCombatBonus();

        return [
            'id' => (int) $this->id,
            'item_key' => $this->item_key,
            'slot' => $item['slot'],
            'name' => $item['name'],
            'rarity' => $item['rarity'],
            'rarity_label' => CosmeticCatalog::rarityLabel($item['rarity']),
            'icon' => CosmeticCatalog::icon($this->item_key),
            'css' => $this->visualTone(),
            'label' => $item['label'] ?? null,
            'forge_level' => (int) $this->forge_level,
            'forge_level_label' => $this->forgeLevelLabel(),
            'combat_bonus' => $bonus,
            'combat_bonus_percent' => round($bonus * 100, 2),
            'equipped' => $equipped,
            'listed_price' => $listedPrice,
            'tradable' => ! CosmeticCatalog::isNonTradable($this->item_key),
        ];
    }
}
