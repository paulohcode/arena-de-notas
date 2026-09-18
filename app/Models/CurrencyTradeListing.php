<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CurrencyTradeListing extends Model
{
    public const STATUS_OPEN = 'open';

    public const STATUS_SOLD = 'sold';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'seller_id',
        'seller_class_id',
        'area_id',
        'offer_currency',
        'offer_amount',
        'ask_currency',
        'ask_amount',
        'status',
        'buyer_id',
        'buyer_class_id',
        'sold_at',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => self::STATUS_OPEN,
    ];

    protected function casts(): array
    {
        return [
            'offer_amount' => 'integer',
            'ask_amount' => 'integer',
            'sold_at' => 'datetime',
        ];
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function sellerClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'seller_class_id');
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function buyerClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'buyer_class_id');
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_OPEN);
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }

    public function feeAmount(): int
    {
        return self::calculateFee((int) $this->ask_amount);
    }

    public static function calculateFee(int $askAmount): int
    {
        if ($askAmount < 1) {
            return 0;
        }

        return max(1, (int) ceil($askAmount * 0.1));
    }

    public function offerLabel(): string
    {
        return GameCurrency::format($this->offer_currency, $this->offer_amount);
    }

    public function askLabel(): string
    {
        return GameCurrency::format($this->ask_currency, $this->ask_amount);
    }
}
