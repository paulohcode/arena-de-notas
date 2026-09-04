<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Duel;
use App\Models\LedgerEntry;
use App\Models\SchoolClass;
use App\Models\User;
use App\Services\StudentSheetService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class StudentController extends Controller
{
    public function __construct(private StudentSheetService $sheet) {}

    public function show(SchoolClass $schoolClass, User $student): View
    {
        $this->authorize('manage', $schoolClass);
        abort_unless($student->isStudent(), 404);
        abort_unless($schoolClass->students()->where('users.id', $student->id)->exists(), 404);

        return view('teacher.student-show', array_merge(
            $this->sheet->data($student, $schoolClass),
            [
                'viewerIsTeacher' => true,
                'rankingUrl' => route('ranking.live', $schoolClass),
            ],
        ));
    }

    public function store(Request $request, SchoolClass $schoolClass): RedirectResponse
    {
        $this->authorize('manage', $schoolClass);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:180', Rule::unique('users', 'email')],
        ]);

        $student = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make('aluno123'),
            'role' => 'student',
            'must_change_password' => true,
        ]);

        $schoolClass->students()->attach($student->id, ['ranking_visible' => false, 'xp' => 0]);

        return back()
            ->withInput(['tab' => 'alunos'])
            ->with('success', 'Aluno cadastrado. Senha inicial: aluno123');
    }

    public function attach(Request $request, SchoolClass $schoolClass): RedirectResponse
    {
        $this->authorize('manage', $schoolClass);

        $data = $request->validate([
            'email' => ['required', 'email', 'exists:users,email'],
        ]);

        $student = User::query()->where('email', $data['email'])->where('role', 'student')->firstOrFail();
        $schoolClass->students()->syncWithoutDetaching([$student->id => ['ranking_visible' => false, 'xp' => 0]]);

        return back()
            ->withInput(['tab' => 'alunos'])
            ->with('success', 'Aluno adicionado à turma.');
    }

    public function transfer(Request $request, SchoolClass $schoolClass, User $student): RedirectResponse
    {
        $this->authorize('manage', $schoolClass);
        abort_unless($schoolClass->students()->where('users.id', $student->id)->exists(), 404);

        $allowedClassIds = $this->manageableClassIds($request->user())
            ->reject(fn (int $id) => $id === $schoolClass->id)
            ->values()
            ->all();

        $data = $request->validate([
            'target_class_id' => ['required', 'integer', Rule::in($allowedClassIds)],
        ]);

        $target = SchoolClass::query()->findOrFail($data['target_class_id']);
        $this->authorize('manage', $target);

        $rankingVisible = (bool) ($student->enrollmentIn($schoolClass)?->ranking_visible ?? false);

        DB::transaction(function () use ($schoolClass, $target, $student, $rankingVisible) {
            $this->purgeClassProgress($schoolClass, $student);

            $schoolClass->students()->detach($student->id);

            $target->students()->syncWithoutDetaching([
                $student->id => [
                    'ranking_visible' => $rankingVisible,
                    'xp' => 0,
                    'glory' => 0,
                    'arena_wins' => 0,
                    'arena_losses' => 0,
                    'behavior_score' => 100,
                ],
            ]);
        });

        return redirect()
            ->route('teacher.classes.show', ['schoolClass' => $schoolClass, 'tab' => 'alunos'])
            ->with(
                'success',
                "{$student->name} foi transferido para {$target->name}. Notas, XP, Glória, medalhas, guilda e histórico da turma anterior foram apagados."
            );
    }

    /**
     * Remove todo vínculo de progresso do aluno na turma de origem.
     */
    private function purgeClassProgress(SchoolClass $schoolClass, User $student): void
    {
        LedgerEntry::query()
            ->where('class_id', $schoolClass->id)
            ->where('student_id', $student->id)
            ->delete();

        DB::table('user_badges')
            ->where('user_id', $student->id)
            ->where('class_id', $schoolClass->id)
            ->delete();

        $teamIds = $schoolClass->teams()->pluck('id');
        if ($teamIds->isNotEmpty()) {
            DB::table('team_members')
                ->whereIn('team_id', $teamIds)
                ->where('student_id', $student->id)
                ->delete();
        }

        Duel::query()
            ->where('class_id', $schoolClass->id)
            ->where(function ($query) use ($student) {
                $query->where('challenger_id', $student->id)
                    ->orWhere('opponent_id', $student->id);
            })
            ->delete();
    }

    public function destroy(SchoolClass $schoolClass, User $student): RedirectResponse
    {
        $this->authorize('manage', $schoolClass);
        $schoolClass->students()->detach($student->id);

        return back()
            ->withInput(['tab' => 'alunos'])
            ->with('success', 'Aluno removido da turma.');
    }

    /**
     * @return Collection<int, int>
     */
    private function manageableClassIds(User $user): Collection
    {
        if ($user->isAdmin()) {
            return SchoolClass::query()->pluck('id');
        }

        return $user->taughtClasses()->pluck('id');
    }
}
