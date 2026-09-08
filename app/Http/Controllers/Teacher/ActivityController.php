<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\SchoolClass;
use App\Services\ActivityReminderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ActivityController extends Controller
{
    public function __construct(private ActivityReminderService $reminders) {}

    public function store(Request $request, SchoolClass $schoolClass): RedirectResponse
    {
        $this->authorize('manage', $schoolClass);

        $schoolClass->activities()->create($this->validated($request));

        return $this->redirectToActivities($schoolClass, 'Atividade criada.');
    }

    public function update(Request $request, SchoolClass $schoolClass, Activity $activity): RedirectResponse
    {
        $this->authorize('manage', $schoolClass);
        abort_unless($activity->class_id === $schoolClass->id, 404);

        $activity->update($this->validated($request));

        return $this->redirectToActivities($schoolClass, 'Atividade atualizada.');
    }

    public function destroy(SchoolClass $schoolClass, Activity $activity): RedirectResponse
    {
        $this->authorize('manage', $schoolClass);
        abort_unless($activity->class_id === $schoolClass->id, 404);
        $activity->delete();

        return $this->redirectToActivities($schoolClass, 'Atividade removida.');
    }

    public function warnMissing(SchoolClass $schoolClass, Activity $activity): RedirectResponse
    {
        $this->authorize('manage', $schoolClass);
        abort_unless($activity->class_id === $schoolClass->id, 404);

        if (! $this->reminders->activityHasStarted($activity)) {
            return $this->redirectToActivities($schoolClass, 'Lance ao menos uma nota antes de avisar quem falta entregar.');
        }

        $count = $this->reminders->notifyMissingWork($schoolClass, $activity);

        if ($count === 0) {
            return $this->redirectToActivities($schoolClass, 'Nenhum aluno está pendente nesta atividade.');
        }

        return $this->redirectToActivities(
            $schoolClass,
            $count === 1
                ? 'Aviso enviado para 1 aluno pendente.'
                : "Aviso enviado para {$count} alunos pendentes.",
        );
    }

    public function weights(Request $request, SchoolClass $schoolClass): RedirectResponse
    {
        $this->authorize('manage', $schoolClass);

        $data = $request->validate([
            'weights' => ['required', 'array'],
            'weights.*' => ['integer', 'min:1', 'max:10'],
            'team_grade_weight' => ['required', 'integer', 'min:1', 'max:10'],
            'behavior_grade_weight' => ['required', 'integer', 'min:1', 'max:10'],
            'attendance_grade_weight' => ['required', 'integer', 'min:1', 'max:10'],
        ]);

        foreach ($data['weights'] as $id => $weight) {
            $schoolClass->activities()->where('id', $id)->update(['weight' => $weight]);
        }

        $schoolClass->update([
            'team_grade_weight' => $data['team_grade_weight'],
            'behavior_grade_weight' => $data['behavior_grade_weight'],
            'attendance_grade_weight' => $data['attendance_grade_weight'],
        ]);

        return back()->with('success', 'Pesos atualizados.');
    }

    /**
     * @return array{name: string, type: string, max_score: int, weight: int}
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'type' => ['required', 'in:individual,team'],
            'max_score' => ['required', 'integer', 'min:1', 'max:100'],
            'weight' => ['required', 'integer', 'min:1', 'max:10'],
        ]);
    }

    private function redirectToActivities(SchoolClass $schoolClass, string $message): RedirectResponse
    {
        return redirect()
            ->route('teacher.classes.show', ['schoolClass' => $schoolClass, 'tab' => 'atividades'])
            ->with('success', $message);
    }
}
