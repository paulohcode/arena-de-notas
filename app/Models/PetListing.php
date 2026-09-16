<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PetListing extends Model
{
    protected $fillable = [
        'class_id',
        'enrollment_id',
        'enrollment_pet_id',
        'price',
    ];

    protected function casts(): array
    {
        return [
            'class_id' => 'integer',
            'enrollment_id' => 'integer',
            'enrollment_pet_id' => 'integer',
            'price' => 'integer',
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

    public function enrollmentPet(): BelongsTo
    {
        return $this->belongsTo(EnrollmentPet::class);
    }
}
