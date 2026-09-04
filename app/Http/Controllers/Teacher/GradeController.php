<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\SchoolClass;
use App\Models\Team;
use App\Models\User;
use App\Services\GameLoopService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class GradeController extends Controller
{
    public function __construct(private GameLoopService $loop) {}

    public function store(Request $request, SchoolClass $schoolClass): RedirectResponse
    {
        $this->authorize('manage', $schoolClass);

        $data = $request->validate([
            'activity_id' => ['required', 'exists:activities,id'],
            'scores' => ['required', 'array'],
            'scores.*' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $activity = Activity::query()->where('class_id', $schoolClass->id)->findOrFail($data['activity_id']);

        $scores = collect($data['scores'])
            ->filter(fn ($score) => $score !== null && $score !== '')
            ->mapWithKeys(fn ($score, $id) => [(string) $id => $score])
            ->all();

        if ($scores === []) {
            return back()
                ->withInput(['tab' => 'notas'])
                ->withErrors(['scores' => 'Informe ao menos uma nota para salvar.']);
        }

        if ($activity->isTeam()) {
            $validIds = $schoolClass->teams()->pluck('id')->map(fn ($id) => (string) $id)->all();
        } else {
            $validIds = $schoolClass->students()->pluck('users.id')->map(fn ($id) => (string) $id)->all();
        }

        $scores = array_intersect_key($scores, array_flip($validIds));

        if ($scores === []) {
            return back()
                ->withInput(['tab' => 'notas'])
                ->withErrors(['scores' => 'Informe ao menos uma nota válida para salvar.']);
        }

        $count = $this->loop->recordActivityGradesBatch($schoolClass, $activity, $scores, $request->user());

        return back()
            ->withInput(['tab' => 'notas'])
            ->with('success', $count === 1
                ? '1 nota lançada. O ranking foi atualizado.'
                : "{$count} notas lançadas. O ranking foi atualizado.");
    }

    public function adjust(Request $request, SchoolClass $schoolClass): RedirectResponse
    {
        $this->authorize('manage', $schoolClass);

        $data = $request->validate([
            'delta' => ['required', 'numeric', 'min:-100', 'max:100'],
            'reason' => ['required', 'string', 'max:180'],
            'target_type' => ['required', 'in:student,team'],
            'student_id' => ['nullable', 'exists:users,id'],
            'team_id' => ['nullable', 'exists:teams,id'],
        ]);

        if ($data['target_type'] === 'team') {
            $team = Team::query()->where('class_id', $schoolClass->id)->findOrFail($data['team_id'] ?? 0);
            $this->loop->recordAdjustment($schoolClass, (float) $data['delta'], $data['reason'], $request->user(), team: $team);
        } else {
            $student = User::query()->findOrFail($data['student_id'] ?? 0);
            abort_unless($schoolClass->students()->where('users.id', $student->id)->exists(), 404);
            $this->loop->recordAdjustment($schoolClass, (float) $data['delta'], $data['reason'], $request->user(), student: $student);
        }

        return back()->with('success', 'Ajuste lançado. O ranking foi atualizado.');
    }

    public function behavior(Request $request, SchoolClass $schoolClass, User $student): RedirectResponse
    {
        $this->authorize('manage', $schoolClass);
        abort_unless($schoolClass->students()->where('users.id', $student->id)->exists(), 404);

        $data = $request->validate([
            'delta' => ['required', 'numeric', 'in:-5,-1,1,5'],
        ]);

        $this->loop->adjustBehavior($schoolClass, $student, (float) $data['delta'], $request->user());

        return back()
            ->withInput(['tab' => 'alunos'])
            ->with('success', 'Comportamento atualizado.');
    }
}
