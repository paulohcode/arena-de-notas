<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ForgeLedger extends Model
{
    public $timestamps = false;

    public const KIND_COSMETIC = 'cosmetic';

    public const KIND_PET = 'pet';

    protected $fillable = [
        'area_id',
        'vault_id',
        'enrollment_id',
        'student_id',
        'kind',
        'source_key',
        'input_count',
        'result_level',
        'result_combat_bonus',
        'result_cosmetic_id',
        'result_pet_id',
        'fee_currency',
        'fee_amount',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'input_count' => 'integer',
            'result_level' => 'integer',
            'result_combat_bonus' => 'float',
            'result_cosmetic_id' => 'integer',
            'result_pet_id' => 'integer',
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
        return $this->belongsTo(ForgeVault::class, 'vault_id');
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }
}
