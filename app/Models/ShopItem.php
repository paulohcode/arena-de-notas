<?php

namespace App\Models;

use App\Support\CosmeticCatalog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShopItem extends Model
{
    /** @use HasFactory<\Database\Factories\ShopItemFactory> */
    use HasFactory;

    protected $fillable = [
        'class_id',
        'item_key',
        'slot',
        'name',
        'price',
        'currency',
        'rarity',
        'icon',
        'css',
        'label',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'class_id' => 'integer',
        ];
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function isGlobal(): bool
    {
        return $this->class_id === null;
    }

    /**
     * @return array{slot: string, name: string, price: int, rarity: string, icon: string, currency: string, css: ?string, label: ?string, class_id: ?int}
     */
    public function toCatalogArray(): array
    {
        return [
            'slot' => $this->slot,
            'name' => $this->name,
            'price' => (int) $this->price,
            'rarity' => $this->rarity,
            'icon' => $this->icon,
            'currency' => $this->currency ?: CosmeticCatalog::CURRENCY_RELICS,
            'css' => $this->css,
            'label' => $this->label,
            'class_id' => $this->class_id,
        ];
    }
}
