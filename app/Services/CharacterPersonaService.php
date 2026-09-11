<?php

namespace App\Services;

use App\Models\SchoolClass;
use App\Models\User;
use App\Notifications\GameAlert;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CharacterPersonaService
{
    public function nameIsTaken(User $student, string $name, bool $includePending = true): bool
    {
        $needle = Str::lower($name);

        return User::query()
            ->where('id', '!=', $student->id)
            ->where(function ($query) use ($needle, $includePending) {
                $query->whereRaw('LOWER(character_name) = ?', [$needle]);

                if ($includePending) {
                    $query->orWhereRaw('LOWER(pending_character_name) = ?', [$needle]);
                }
            })
            ->exists();
    }

    public function submit(User $student, string $characterClass, string $characterName, string $characterAvatar): void
    {
        if ($this->nameIsTaken($student, $characterName)) {
            throw ValidationException::withMessages([
                'character_name' => 'Este nome de personagem já está em uso.',
            ]);
        }

        if ($student->hasCharacterClass()) {
            $characterClass = $student->character_class;
        }

        $student->update([
            'character_class' => $characterClass,
            'pending_character_name' => $characterName,
            'pending_character_avatar' => $characterAvatar,
            'character_approval_status' => 'pending',
            'character_rejection_reason' => null,
        ]);

        $this->notifyTeachers($student, $characterName);
    }

    public function assign(User $student, string $characterClass, string $characterName, string $characterAvatar): void
    {
        if ($this->nameIsTaken($student, $characterName)) {
            throw ValidationException::withMessages([
                'character_name' => 'Este nome de personagem já está em uso.',
            ]);
        }

        $student->update([
            'character_class' => $characterClass,
            'character_name' => $characterName,
            'character_avatar' => $characterAvatar,
            'pending_character_name' => null,
            'pending_character_avatar' => null,
            'character_approval_status' => 'approved',
            'character_rejection_reason' => null,
        ]);

        $student->notify(new GameAlert(
            'persona',
            'Personagem atualizado',
            "O professor definiu seu personagem: {$characterName}.",
            ['character_name' => $characterName],
        ));
    }

    public function approve(SchoolClass $schoolClass, User $student): void
    {
        abort_unless($schoolClass->students()->where('users.id', $student->id)->exists(), 404);

        if ($student->hasApprovedPersona() && ! $student->isPersonaPending()) {
            return;
        }

        if (! $student->isPersonaPending()) {
            throw ValidationException::withMessages([
                'character' => 'Este aluno não tem personagem aguardando aprovação.',
            ]);
        }

        $name = (string) $student->pending_character_name;
        $avatar = (string) $student->pending_character_avatar;

        if ($name === '' || $avatar === '') {
            throw ValidationException::withMessages([
                'character' => 'O pedido de personagem está incompleto. Peça ao aluno para enviar de novo.',
            ]);
        }

        if ($this->nameIsTaken($student, $name, includePending: false)) {
            throw ValidationException::withMessages([
                'character_name' => 'Este nome de personagem já está em uso.',
            ]);
        }

        $student->update([
            'character_name' => $name,
            'character_avatar' => $avatar,
            'pending_character_name' => null,
            'pending_character_avatar' => null,
            'character_approval_status' => 'approved',
            'character_rejection_reason' => null,
        ]);

        $student->notify(new GameAlert(
            'persona',
            'Personagem aprovado!',
            "Agora você aparece como {$name} na arena.",
            ['character_name' => $name],
        ));
    }

    /**
     * @return array{approved: int, skipped: int}
     */
    public function approvePendingInClass(SchoolClass $schoolClass): array
    {
        $approved = 0;
        $skipped = 0;

        $pending = $schoolClass->students()
            ->orderBy('users.name')
            ->orderBy('users.id')
            ->get()
            ->filter(fn (User $student) => $student->isPersonaPending());

        foreach ($pending as $student) {
            try {
                $this->approve($schoolClass, $student);
                $approved++;
            } catch (ValidationException) {
                $skipped++;
            }
        }

        return [
            'approved' => $approved,
            'skipped' => $skipped,
        ];
    }

    public function reject(SchoolClass $schoolClass, User $student, ?string $reason = null): void
    {
        abort_unless($schoolClass->students()->where('users.id', $student->id)->exists(), 404);

        if (! $student->isPersonaPending()) {
            throw ValidationException::withMessages([
                'character' => 'Este aluno não tem personagem aguardando aprovação.',
            ]);
        }

        $student->update([
            'pending_character_name' => null,
            'pending_character_avatar' => null,
            'character_approval_status' => 'rejected',
            'character_rejection_reason' => $reason,
        ]);

        $message = 'O professor recusou seu avatar e nome de jogo. Escolha outros e envie de novo.';
        if (filled($reason)) {
            $message .= ' Motivo: '.$reason;
        }

        $student->notify(new GameAlert(
            'persona',
            'Personagem recusado',
            $message,
        ));
    }

    private function notifyTeachers(User $student, string $characterName): void
    {
        $teachers = $student->classes()
            ->with('teacher')
            ->orderBy('name')
            ->get()
            ->pluck('teacher')
            ->filter()
            ->unique('id');

        foreach ($teachers as $teacher) {
            $teacher->notify(new GameAlert(
                'persona',
                'Personagem aguardando aprovação',
                "{$student->name} enviou o nome {$characterName} para a arena.",
                ['student_id' => $student->id],
            ));
        }
    }
}
