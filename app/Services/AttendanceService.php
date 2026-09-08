<?php

namespace App\Services;

use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AttendanceService
{
    public function __construct(private GameLoopService $loop) {}

    public function generate(SchoolClass $class, Carbon|string $heldOn, User $author): AttendanceSession
    {
        $date = Carbon::parse($heldOn)->toDateString();

        return DB::transaction(function () use ($class, $date, $author) {
            $session = AttendanceSession::query()->firstOrCreate(
                [
                    'class_id' => $class->id,
                    'held_on' => $date,
                ],
                [
                    'created_by' => $author->id,
                ],
            );

            $studentIds = $class->students()->pluck('users.id');

            foreach ($studentIds as $studentId) {
                AttendanceRecord::query()->firstOrCreate(
                    [
                        'attendance_session_id' => $session->id,
                        'student_id' => $studentId,
                    ],
                    ['status' => null],
                );
            }

            return $session->load(['records.student']);
        });
    }

    /**
     * @param  array<int|string, string>  $statusesByStudentId
     */
    public function saveStatuses(SchoolClass $class, AttendanceSession $session, array $statusesByStudentId): AttendanceSession
    {
        abort_unless((int) $session->class_id === (int) $class->id, 404);

        $studentIds = $class->students()->pluck('users.id')->map(fn ($id) => (int) $id)->all();

        if ($studentIds === []) {
            throw ValidationException::withMessages([
                'statuses' => 'Não há alunos matriculados nesta turma.',
            ]);
        }

        foreach ($studentIds as $studentId) {
            $status = $statusesByStudentId[$studentId] ?? $statusesByStudentId[(string) $studentId] ?? null;
            if (! in_array($status, AttendanceRecord::STATUSES, true)) {
                throw ValidationException::withMessages([
                    'statuses' => 'Informe o status de todos os alunos (Presente, Ausente ou Justificada).',
                ]);
            }
        }

        $this->loop->refreshProgress($class, function () use ($class, $session, $statusesByStudentId, $studentIds) {
            $records = AttendanceRecord::query()
                ->where('attendance_session_id', $session->id)
                ->lockForUpdate()
                ->get()
                ->keyBy('student_id');

            $enrollments = Enrollment::query()
                ->where('class_id', $class->id)
                ->whereIn('student_id', $studentIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('student_id');

            foreach ($studentIds as $studentId) {
                $newStatus = $statusesByStudentId[$studentId] ?? $statusesByStudentId[(string) $studentId];
                $record = $records->get($studentId);

                if (! $record) {
                    $record = AttendanceRecord::query()->create([
                        'attendance_session_id' => $session->id,
                        'student_id' => $studentId,
                        'status' => null,
                    ]);
                }

                $oldStatus = $record->status;
                $record->status = $newStatus;
                $record->save();

                $enrollment = $enrollments->get($studentId);
                if (! $enrollment) {
                    continue;
                }

                $this->adjustSeals($enrollment, $oldStatus, $newStatus);
            }
        });

        return $session->fresh(['records.student']);
    }

    public function destroy(SchoolClass $class, AttendanceSession $session): void
    {
        abort_unless((int) $session->class_id === (int) $class->id, 404);

        $this->loop->refreshProgress($class, function () use ($class, $session) {
            $records = AttendanceRecord::query()
                ->where('attendance_session_id', $session->id)
                ->lockForUpdate()
                ->get();

            $studentIds = $records->pluck('student_id')->all();

            $enrollments = Enrollment::query()
                ->where('class_id', $class->id)
                ->whereIn('student_id', $studentIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('student_id');

            foreach ($records as $record) {
                $enrollment = $enrollments->get($record->student_id);
                if ($enrollment) {
                    $this->adjustSeals($enrollment, $record->status, null);
                }
            }

            $session->delete();
        });
    }

    private function adjustSeals(Enrollment $enrollment, ?string $oldStatus, ?string $newStatus): void
    {
        $wasPresent = $oldStatus === AttendanceRecord::STATUS_PRESENT;
        $isPresent = $newStatus === AttendanceRecord::STATUS_PRESENT;

        if ($wasPresent === $isPresent) {
            return;
        }

        $seals = (int) $enrollment->seals;

        if ($isPresent) {
            $seals++;
        } else {
            $seals = max(0, $seals - 1);
        }

        $enrollment->seals = $seals;
        $enrollment->save();
    }
}
