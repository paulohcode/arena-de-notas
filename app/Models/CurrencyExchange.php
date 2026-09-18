<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CurrencyExchange extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'student_id',
        'class_id',
        'area_id',
        'exchange_rate_id',
        'pay_currency',
        'receive_currency',
        'pay_amount',
        'receive_amount',
        'lots',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'area_id' => 'integer',
            'exchange_rate_id' => 'integer',
            'pay_amount' => 'integer',
            'receive_amount' => 'integer',
            'lots' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function exchangeRate(): BelongsTo
    {
        return $this->belongsTo(ExchangeRate::class);
    }
}
