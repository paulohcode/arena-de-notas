<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\SchoolClass;
use App\Services\AttendanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AttendanceController extends Controller
{
    public function __construct(private AttendanceService $attendance) {}

    public function store(Request $request, SchoolClass $schoolClass): RedirectResponse
    {
        $this->authorize('manage', $schoolClass);

        $data = $request->validate([
            'held_on' => ['required', 'date'],
        ]);

        $session = $this->attendance->generate($schoolClass, $data['held_on'], $request->user());

        return redirect()
            ->route('teacher.classes.show', [
                'schoolClass' => $schoolClass,
                'tab' => 'chamada',
                'session' => $session->id,
            ])
            ->with('success', 'Chamada gerada para '.$session->held_on->format('d/m/Y').'.');
    }

    public function update(Request $request, SchoolClass $schoolClass, AttendanceSession $attendanceSession): RedirectResponse
    {
        $this->authorize('manage', $schoolClass);
        abort_unless((int) $attendanceSession->class_id === (int) $schoolClass->id, 404);

        $data = $request->validate([
            'statuses' => ['required', 'array'],
            'statuses.*' => ['required', Rule::in(AttendanceRecord::STATUSES)],
        ]);

        $this->attendance->saveStatuses($schoolClass, $attendanceSession, $data['statuses']);

        return redirect()
            ->route('teacher.classes.show', [
                'schoolClass' => $schoolClass,
                'tab' => 'chamada',
                'session' => $attendanceSession->id,
            ])
            ->with('success', 'Chamada salva. A frequência e os Selos foram atualizados.');
    }

    public function destroy(SchoolClass $schoolClass, AttendanceSession $attendanceSession): RedirectResponse
    {
        $this->authorize('manage', $schoolClass);
        abort_unless((int) $attendanceSession->class_id === (int) $schoolClass->id, 404);

        $date = $attendanceSession->held_on->format('d/m/Y');
        $this->attendance->destroy($schoolClass, $attendanceSession);

        return redirect()
            ->route('teacher.classes.show', [
                'schoolClass' => $schoolClass,
                'tab' => 'chamada',
            ])
            ->with('success', 'Chamada de '.$date.' removida.');
    }
}
