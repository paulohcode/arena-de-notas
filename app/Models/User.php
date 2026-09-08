<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'must_change_password',
        'character_class',
        'character_name',
        'character_avatar',
        'pending_character_name',
        'pending_character_avatar',
        'character_approval_status',
        'character_rejection_reason',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Classes de personagem disponíveis para os alunos.
     * Stats de combate (hp/atk/def/spd/heal_chance) alimentam a arena; não alteram notas.
     *
     * @var array<string, array{name: string, icon: string, blurb: string, tone: string, hp: int, atk: int, def: int, spd: int, heal_chance: float}>
     */
    public const CHARACTER_CLASSES = [
        'guerreiro' => [
            'name' => 'Guerreiro',
            'icon' => '⚔',
            'blurb' => 'Força bruta e linha de frente.',
            'tone' => '#ea580c',
            'hp' => 120,
            'atk' => 18,
            'def' => 14,
            'spd' => 8,
            'heal_chance' => 0.0,
        ],
        'mago' => [
            'name' => 'Mago',
            'icon' => '✦',
            'blurb' => 'Magia arcana e conhecimento.',
            'tone' => '#7c3aed',
            'hp' => 85,
            'atk' => 24,
            'def' => 8,
            'spd' => 10,
            'heal_chance' => 0.0,
        ],
        'feiticeira' => [
            'name' => 'Feiticeira',
            'icon' => '☽',
            'blurb' => 'Poder místico e presença.',
            'tone' => '#c084fc',
            'hp' => 88,
            'atk' => 23,
            'def' => 9,
            'spd' => 11,
            'heal_chance' => 0.05,
        ],
        'arqueiro' => [
            'name' => 'Arqueiro',
            'icon' => '➶',
            'blurb' => 'Precisão e alcance.',
            'tone' => '#4ade80',
            'hp' => 95,
            'atk' => 20,
            'def' => 10,
            'spd' => 16,
            'heal_chance' => 0.0,
        ],
        'ladino' => [
            'name' => 'Ladino',
            'icon' => '🗡',
            'blurb' => 'Astúcia e velocidade.',
            'tone' => '#64748b',
            'hp' => 90,
            'atk' => 21,
            'def' => 9,
            'spd' => 18,
            'heal_chance' => 0.0,
        ],
        'paladino' => [
            'name' => 'Paladino',
            'icon' => '⛨',
            'blurb' => 'Honra, defesa e disciplina.',
            'tone' => '#f5c56b',
            'hp' => 115,
            'atk' => 16,
            'def' => 16,
            'spd' => 7,
            'heal_chance' => 0.12,
        ],
        'druida' => [
            'name' => 'Druida',
            'icon' => '❧',
            'blurb' => 'Natureza e equilíbrio.',
            'tone' => '#22c55e',
            'hp' => 100,
            'atk' => 15,
            'def' => 12,
            'spd' => 10,
            'heal_chance' => 0.22,
        ],
        'bardo' => [
            'name' => 'Bardo',
            'icon' => '♫',
            'blurb' => 'Carisma e inspiração.',
            'tone' => '#f472b6',
            'hp' => 98,
            'atk' => 16,
            'def' => 11,
            'spd' => 12,
            'heal_chance' => 0.18,
        ],
        'clerigo' => [
            'name' => 'Clérigo',
            'icon' => '✚',
            'blurb' => 'Cura e proteção sagrada.',
            'tone' => '#fde68a',
            'hp' => 105,
            'atk' => 14,
            'def' => 13,
            'spd' => 9,
            'heal_chance' => 0.28,
        ],
        'necromante' => [
            'name' => 'Necromante',
            'icon' => '☠',
            'blurb' => 'Sombras e poder proibido.',
            'tone' => '#4ade80',
            'hp' => 82,
            'atk' => 25,
            'def' => 7,
            'spd' => 11,
            'heal_chance' => 0.08,
        ],
        'anao' => [
            'name' => 'Anão',
            'icon' => '⚒',
            'blurb' => 'Forja, pedra e resistência.',
            'tone' => '#b45309',
            'hp' => 125,
            'atk' => 15,
            'def' => 18,
            'spd' => 6,
            'heal_chance' => 0.0,
        ],
        'frankenstein' => [
            'name' => 'Frankenstein',
            'icon' => '⚡',
            'blurb' => 'Relâmpago, laboratório e força reconstruída.',
            'tone' => '#22d3ee',
            'hp' => 118,
            'atk' => 19,
            'def' => 13,
            'spd' => 7,
            'heal_chance' => 0.06,
        ],
    ];

    /**
     * Avatares que o aluno pode escolher. Só aparecem em público após aprovação.
     *
     * @var array<string, array{name: string, icon: string, tone: string}>
     */
    public const CHARACTER_AVATARS = [
        'lobo' => ['name' => 'Lobo', 'icon' => '🐺', 'tone' => '#78716c'],
        'fenix' => ['name' => 'Fênix', 'icon' => '🔥', 'tone' => '#ea580c'],
        'dragao' => ['name' => 'Dragão', 'icon' => '🐉', 'tone' => '#16a34a'],
        'coruja' => ['name' => 'Coruja', 'icon' => '🦉', 'tone' => '#7c3aed'],
        'leao' => ['name' => 'Leão', 'icon' => '🦁', 'tone' => '#d97706'],
        'corvo' => ['name' => 'Corvo', 'icon' => '🐦‍⬛', 'tone' => '#334155'],
        'serpente' => ['name' => 'Serpente', 'icon' => '🐍', 'tone' => '#059669'],
        'aguia' => ['name' => 'Águia', 'icon' => '🦅', 'tone' => '#b45309'],
        'lua' => ['name' => 'Lua', 'icon' => '🌙', 'tone' => '#6366f1'],
        'runa' => ['name' => 'Runa', 'icon' => '✦', 'tone' => '#eab308'],
        'elmo' => ['name' => 'Elmo', 'icon' => '🪖', 'tone' => '#64748b'],
        'cristal' => ['name' => 'Cristal', 'icon' => '💎', 'tone' => '#06b6d4'],
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_accessed_at' => 'datetime',
            'password' => 'hashed',
            'must_change_password' => 'boolean',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isTeacher(): bool
    {
        return $this->role === 'teacher';
    }

    public function isStudent(): bool
    {
        return $this->role === 'student';
    }

    public function recordAccess(bool $force = false): void
    {
        if (! $this->isStudent()) {
            return;
        }

        if (! $force && $this->last_accessed_at !== null && $this->last_accessed_at->gte(now()->subMinute())) {
            return;
        }

        $this->last_accessed_at = now();
        $this->save();
    }

    /**
     * Último acesso no horário de Brasília, ou null se o aluno nunca entrou.
     */
    public function lastAccessedLabel(): ?string
    {
        if ($this->last_accessed_at === null) {
            return null;
        }

        return $this->last_accessed_at
            ->copy()
            ->timezone(config('app.display_timezone'))
            ->format('d/m/Y H:i');
    }

    public function homeRoute(): string
    {
        return match ($this->role) {
            'admin' => route('admin.dashboard'),
            'teacher' => route('teacher.dashboard'),
            default => route('student.dashboard'),
        };
    }

    public function belongsToArea(Area|int $area): bool
    {
        $areaId = $area instanceof Area ? $area->id : $area;

        return $this->areas()->where('areas.id', $areaId)->exists();
    }

    public function hasCharacterClass(): bool
    {
        return filled($this->character_class) && isset(self::CHARACTER_CLASSES[$this->character_class]);
    }

    /**
     * @return array{name: string, icon: string, blurb: string, tone: string, hp: int, atk: int, def: int, spd: int, heal_chance: float}|null
     */
    public function characterClassMeta(): ?array
    {
        if (! $this->hasCharacterClass()) {
            return null;
        }

        return self::CHARACTER_CLASSES[$this->character_class];
    }

    public function hasApprovedPersona(): bool
    {
        return $this->character_approval_status === 'approved'
            && filled($this->character_name)
            && filled($this->character_avatar);
    }

    public function characterClassLabel(): string
    {
        return $this->characterClassMeta()['name'] ?? 'Sem classe';
    }

    public function characterClassIcon(): string
    {
        return $this->characterClassMeta()['icon'] ?? '?';
    }

    public function characterClassTone(): string
    {
        return $this->characterClassMeta()['tone'] ?? '#c4922c';
    }

    public function characterAuraClass(bool $compact = false): string
    {
        if (! $this->hasCharacterClass()) {
            return '';
        }

        $classes = 'class-aura class-aura--'.$this->character_class;

        if ($compact) {
            $classes .= ' class-aura--compact';
        }

        return $classes;
    }

    public function arenaName(): ?string
    {
        return filled($this->character_name) ? $this->character_name : null;
    }

    public function isPersonaPending(): bool
    {
        return $this->character_approval_status === 'pending';
    }

    public function isPersonaRejected(): bool
    {
        return $this->character_approval_status === 'rejected';
    }

    /**
     * @return array{name: string, icon: string, tone: string}|null
     */
    public function avatarMeta(?string $key = null): ?array
    {
        $avatarKey = $key ?? $this->character_avatar;

        if (! filled($avatarKey) || ! isset(self::CHARACTER_AVATARS[$avatarKey])) {
            return null;
        }

        return self::CHARACTER_AVATARS[$avatarKey];
    }

    public function avatarIcon(?string $key = null): string
    {
        return $this->avatarMeta($key)['icon'] ?? $this->characterClassIcon();
    }

    public function avatarTone(?string $key = null): string
    {
        return $this->avatarMeta($key)['tone'] ?? $this->characterClassTone();
    }

    public function areas(): BelongsToMany
    {
        return $this->belongsToMany(Area::class)->withTimestamps();
    }

    public function taughtClasses(): HasMany
    {
        return $this->hasMany(SchoolClass::class, 'teacher_id');
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class, 'student_id');
    }

    public function classes(): BelongsToMany
    {
        return $this->belongsToMany(SchoolClass::class, 'enrollments', 'student_id', 'class_id')
            ->withPivot([
                'ranking_visible',
                'xp',
                'glory',
                'relics',
                'arena_wins',
                'arena_losses',
                'behavior_score',
                'equipped_frame',
                'equipped_accessory',
                'equipped_title',
                'equipped_aura',
            ])
            ->withTimestamps();
    }

    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class, 'team_members', 'student_id', 'team_id')->withTimestamps();
    }

    public function badges(): BelongsToMany
    {
        return $this->belongsToMany(Badge::class, 'user_badges')->withPivot('class_id')->withTimestamps();
    }

    public function teamInClass(SchoolClass $class): ?Team
    {
        return $this->teams()->where('class_id', $class->id)->first();
    }

    public function enrollmentIn(SchoolClass $class): ?Enrollment
    {
        return $this->enrollments()->where('class_id', $class->id)->first();
    }
}
