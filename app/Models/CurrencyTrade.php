<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CurrencyTrade extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'area_id',
        'vault_id',
        'listing_id',
        'seller_id',
        'seller_class_id',
        'buyer_id',
        'buyer_class_id',
        'offer_currency',
        'offer_amount',
        'ask_currency',
        'ask_amount',
        'fee_currency',
        'fee_amount',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'offer_amount' => 'integer',
            'ask_amount' => 'integer',
            'fee_amount' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function vault(): BelongsTo
    {
        return $this->belongsTo(ExchangeVault::class, 'vault_id');
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(CurrencyTradeListing::class, 'listing_id');
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function sellerClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'seller_class_id');
    }

    public function buyerClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'buyer_class_id');
    }
}
