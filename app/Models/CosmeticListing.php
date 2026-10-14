<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CosmeticListing extends Model
{
    protected $fillable = [
        'class_id',
        'enrollment_id',
        'enrollment_cosmetic_id',
        'item_key',
        'price',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'enrollment_cosmetic_id' => 'integer',
        ];
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function cosmetic(): BelongsTo
    {
        return $this->belongsTo(EnrollmentCosmetic::class, 'enrollment_cosmetic_id');
    }
}
