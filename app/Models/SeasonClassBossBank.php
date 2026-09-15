<?php

namespace App\Models;

use App\Support\BossArchetypeCatalog;
use Database\Factories\SeasonClassBossBankFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeasonClassBossBank extends Model
{
    /** @use HasFactory<SeasonClassBossBankFactory> */
    use HasFactory;

    protected $fillable = [
        'season_id',
        'class_id',
        'relics',
        'battles',
        'next_battle',
        'payout_percent',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'relics' => 0,
        'battles' => 0,
        'next_battle' => BossArchetypeCatalog::JACKPOT_BATTLE_MIN,
        'payout_percent' => BossArchetypeCatalog::JACKPOT_PERCENT_MIN,
    ];

    protected function casts(): array
    {
        return [
            'relics' => 'integer',
            'battles' => 'integer',
            'next_battle' => 'integer',
            'payout_percent' => 'integer',
        ];
    }

    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    /**
     * Altura visual do ouro (0–100). 200 Relíquias = pote cheio.
     */
    public function fillPercent(): float
    {
        return BossArchetypeCatalog::bossBankFillPercent((int) $this->relics);
    }
}
