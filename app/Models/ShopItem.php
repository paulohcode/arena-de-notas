<?php

namespace App\Models;

use App\Support\CosmeticCatalog;
use Database\Factories\ShopItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShopItem extends Model
{
    /** @use HasFactory<ShopItemFactory> */
    use HasFactory;

    protected $fillable = [
        'class_id',
        'area_id',
        'item_key',
        'slot',
        'name',
        'price',
        'currency',
        'rarity',
        'icon',
        'css',
        'label',
        'combat_bonus',
        'prize_only',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'prize_only' => false,
    ];

    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'class_id' => 'integer',
            'area_id' => 'integer',
            'combat_bonus' => 'float',
            'prize_only' => 'boolean',
        ];
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    protected static function booted(): void
    {
        static::saved(fn () => CosmeticCatalog::flush());
        static::deleted(fn () => CosmeticCatalog::flush());
    }

    public function isGlobal(): bool
    {
        return $this->class_id === null && $this->area_id === null;
    }

    public function usesAuras(): bool
    {
        return $this->currency === CosmeticCatalog::CURRENCY_AURAS;
    }

    public function belongsToClassShop(SchoolClass $class): bool
    {
        if ((int) $this->class_id === (int) $class->id) {
            return true;
        }

        return $this->usesAuras()
            && $this->area_id !== null
            && (int) $this->area_id === (int) $class->area_id;
    }

    public function scopeLabel(): string
    {
        if ($this->usesAuras()) {
            return $this->area?->name
                ? 'único no reino '.$this->area->name
                : 'único em cada reino';
        }

        return $this->isGlobal()
            ? 'todas as turmas'
            : ($this->schoolClass?->name ?? 'turma');
    }

    public function isPrizeOnly(): bool
    {
        return (bool) $this->prize_only;
    }

    /**
     * @return array{id: int, slot: string, name: string, price: int, rarity: string, icon: string, currency: string, css: ?string, label: ?string, class_id: ?int, area_id: ?int, combat_bonus: ?float, prize_only: bool}
     */
    public function toCatalogArray(): array
    {
        return [
            'id' => $this->id,
            'slot' => $this->slot,
            'name' => $this->name,
            'price' => (int) $this->price,
            'rarity' => $this->rarity,
            'icon' => $this->icon,
            'currency' => $this->currency ?: CosmeticCatalog::CURRENCY_RELICS,
            'css' => $this->css,
            'label' => $this->label,
            'class_id' => $this->class_id,
            'area_id' => $this->area_id,
            'combat_bonus' => $this->combat_bonus,
            'prize_only' => $this->isPrizeOnly(),
        ];
    }

    public function combatBonusPercent(): float
    {
        return round(CosmeticCatalog::combatBonusForItem($this->toCatalogArray()) * 100, 1);
    }
}
