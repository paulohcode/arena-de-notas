<?php

namespace App\Models;

use Database\Factories\GameEventFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GameEvent extends Model
{
    /** @use HasFactory<GameEventFactory> */
    use HasFactory;

    public const KIND_ACTIVITY = 'activity';

    public const KIND_CLASS = 'class';

    public const KIND_REALM = 'realm';

    public const MODE_LIVE = 'live';

    public const MODE_WINDOW = 'window';

    public const STATUS_DRAFT = 'draft';

    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_LIVE = 'live';

    public const STATUS_CLOSED = 'closed';

    protected $fillable = [
        'title',
        'kind',
        'mode',
        'status',
        'class_id',
        'area_id',
        'activity_id',
        'created_by',
        'starts_at',
        'ends_at',
        'question_seconds',
        'current_question_index',
        'current_question_opened_at',
        'relics_per_correct',
        'seals_per_correct',
        'auras_per_correct',
        'prize_item_id',
        'awarded_at',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => self::STATUS_DRAFT,
        'question_seconds' => 30,
        'relics_per_correct' => 0,
        'seals_per_correct' => 0,
        'auras_per_correct' => 0,
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'current_question_opened_at' => 'datetime',
            'awarded_at' => 'datetime',
            'question_seconds' => 'integer',
            'current_question_index' => 'integer',
            'relics_per_correct' => 'integer',
            'seals_per_correct' => 'integer',
            'auras_per_correct' => 'integer',
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

    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function prizeItem(): BelongsTo
    {
        return $this->belongsTo(ShopItem::class, 'prize_item_id');
    }

    public function questions(): HasMany
    {
        return $this->hasMany(GameEventQuestion::class)->orderBy('position');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(GameEventAttempt::class);
    }

    public function isActivity(): bool
    {
        return $this->kind === self::KIND_ACTIVITY;
    }

    public function isClassEvent(): bool
    {
        return $this->kind === self::KIND_CLASS;
    }

    public function isRealmEvent(): bool
    {
        return $this->kind === self::KIND_REALM;
    }

    public function isLiveMode(): bool
    {
        return $this->mode === self::MODE_LIVE;
    }

    public function isWindowMode(): bool
    {
        return $this->mode === self::MODE_WINDOW;
    }

    public function isOpen(): bool
    {
        return in_array($this->status, [self::STATUS_SCHEDULED, self::STATUS_LIVE], true);
    }

    public function isClosed(): bool
    {
        return $this->status === self::STATUS_CLOSED;
    }

    public function awardsCoins(): bool
    {
        return $this->isActivity();
    }

    public function awardsPrize(): bool
    {
        return in_array($this->kind, [self::KIND_CLASS, self::KIND_REALM], true);
    }

    public function kindLabel(): string
    {
        return match ($this->kind) {
            self::KIND_ACTIVITY => 'Atividade-evento',
            self::KIND_CLASS => 'Evento da turma',
            self::KIND_REALM => 'Evento do reino',
            default => $this->kind,
        };
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_DRAFT => 'Rascunho',
            self::STATUS_SCHEDULED => 'Agendado',
            self::STATUS_LIVE => 'Ao vivo',
            self::STATUS_CLOSED => 'Encerrado',
            default => $this->status,
        };
    }

    public function modeLabel(): string
    {
        return $this->isLiveMode() ? 'Ao vivo' : 'Janela de tempo';
    }
}
