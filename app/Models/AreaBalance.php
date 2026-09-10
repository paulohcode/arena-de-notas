<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AreaBalance extends Model
{
    protected $fillable = [
        'area_id',
        'student_id',
        'auras',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'auras' => 0,
    ];

    protected function casts(): array
    {
        return [
            'auras' => 'integer',
        ];
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public static function forStudent(Area $area, User $student): self
    {
        return static::query()->firstOrCreate(
            [
                'area_id' => $area->id,
                'student_id' => $student->id,
            ],
            ['auras' => 0],
        );
    }
}
