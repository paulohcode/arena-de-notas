<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\SchoolClass;
use App\Models\User;
use App\Services\CharacterPersonaService;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CharacterApprovalController extends Controller
{
    public function __construct(private CharacterPersonaService $personas) {}

    public function update(Request $request, SchoolClass $schoolClass, User $student): RedirectResponse
    {
        $this->authorize('manage', $schoolClass);
        abort_unless($student->isStudent(), 404);
        abort_unless($schoolClass->students()->where('users.id', $student->id)->exists(), 404);

        $data = $request->validate([
            'character_class' => ['required', Rule::in(array_keys(User::CHARACTER_CLASSES))],
            'character_name' => [
                'required',
                'string',
                'min:2',
                'max:24',
                'regex:/^[\p{L}0-9][\p{L}0-9 \-\']{0,22}$/u',
                function (string $attribute, mixed $value, Closure $fail) use ($student) {
                    if ($this->personas->nameIsTaken($student, (string) $value)) {
                        $fail('Este nome de personagem já está em uso.');
                    }
                },
            ],
            'character_avatar' => ['required', Rule::in(array_keys(User::CHARACTER_AVATARS))],
        ]);

        $this->personas->assign(
            $student,
            $data['character_class'],
            $data['character_name'],
            $data['character_avatar'],
        );

        return redirect()
            ->route('teacher.students.show', [$schoolClass, $student])
            ->with('success', "Identidade de {$student->name} atualizada.");
    }

    public function approve(SchoolClass $schoolClass, User $student): RedirectResponse
    {
        $this->authorize('manage', $schoolClass);
        abort_unless($student->isStudent(), 404);

        $this->personas->approve($schoolClass, $student);

        return redirect()
            ->route('teacher.classes.show', ['schoolClass' => $schoolClass, 'tab' => 'personagens'])
            ->with('success', "Personagem de {$student->name} aprovado.");
    }

    public function reject(Request $request, SchoolClass $schoolClass, User $student): RedirectResponse
    {
        $this->authorize('manage', $schoolClass);
        abort_unless($student->isStudent(), 404);

        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:200'],
        ]);

        $this->personas->reject($schoolClass, $student, $data['reason'] ?? null);

        return redirect()
            ->route('teacher.classes.show', ['schoolClass' => $schoolClass, 'tab' => 'personagens'])
            ->with('success', "Personagem de {$student->name} recusado.");
    }
}
