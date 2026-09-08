<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\SchoolClass;
use App\Models\User;
use App\Services\GradeCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_teacher_can_generate_attendance_for_a_date(): void
    {
        [$teacher, $class, $studentA, $studentB] = $this->readyClassWithStudents();

        $this->actingAs($teacher)
            ->post(route('teacher.attendance.store', $class), [
                'held_on' => '2026-09-08',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $session = AttendanceSession::query()->where('class_id', $class->id)->firstOrFail();
        $this->assertSame('2026-09-08', $session->held_on->toDateString());
        $this->assertSame(2, $session->records()->count());
        $this->assertTrue($session->records()->whereNull('status')->exists());

        $grades = app(GradeCalculator::class);
        $this->assertFalse($grades->hasGradedAttendance($class));
        $this->assertEquals(100.0, $grades->studentAverage($studentA, $class), 0.01);
    }

    public function test_generating_same_date_reopens_existing_session(): void
    {
        [$teacher, $class] = $this->readyClassWithStudents();

        $this->actingAs($teacher)
            ->post(route('teacher.attendance.store', $class), ['held_on' => '2026-09-08']);

        $this->actingAs($teacher)
            ->post(route('teacher.attendance.store', $class), ['held_on' => '2026-09-08']);

        $this->assertSame(1, AttendanceSession::query()->where('class_id', $class->id)->count());
    }

    public function test_present_awards_seal_and_justified_counts_toward_grade_without_seal(): void
    {
        [$teacher, $class, $studentA, $studentB] = $this->readyClassWithStudents();

        $this->actingAs($teacher)
            ->post(route('teacher.attendance.store', $class), ['held_on' => '2026-09-08']);

        $session = AttendanceSession::query()->firstOrFail();

        $this->actingAs($teacher)
            ->put(route('teacher.attendance.update', [$class, $session]), [
                'statuses' => [
                    $studentA->id => AttendanceRecord::STATUS_PRESENT,
                    $studentB->id => AttendanceRecord::STATUS_JUSTIFIED,
                ],
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(1, (int) $studentA->enrollmentIn($class)->fresh()->seals);
        $this->assertSame(0, (int) $studentB->enrollmentIn($class)->fresh()->seals);

        $grades = app(GradeCalculator::class);
        // Comportamento 100 + frequência 100, pesos 1+1 => média 100
        $this->assertEquals(100.0, $grades->attendanceScore($studentA, $class), 0.01);
        $this->assertEquals(100.0, $grades->attendanceScore($studentB, $class), 0.01);
        $this->assertEquals(100.0, $grades->studentAverage($studentA, $class), 0.01);
    }

    public function test_absent_lowers_attendance_score_and_does_not_award_seal(): void
    {
        [$teacher, $class, $studentA, $studentB] = $this->readyClassWithStudents();

        $this->actingAs($teacher)
            ->post(route('teacher.attendance.store', $class), ['held_on' => '2026-09-08']);

        $session = AttendanceSession::query()->firstOrFail();

        $this->actingAs($teacher)
            ->put(route('teacher.attendance.update', [$class, $session]), [
                'statuses' => [
                    $studentA->id => AttendanceRecord::STATUS_PRESENT,
                    $studentB->id => AttendanceRecord::STATUS_ABSENT,
                ],
            ]);

        $grades = app(GradeCalculator::class);
        $this->assertEquals(100.0, $grades->attendanceScore($studentA, $class), 0.01);
        $this->assertEquals(0.0, $grades->attendanceScore($studentB, $class), 0.01);
        // Comportamento 100 + frequência 0 / 2 = 50
        $this->assertEquals(50.0, $grades->studentAverage($studentB, $class), 0.01);
        $this->assertSame(0, (int) $studentB->enrollmentIn($class)->fresh()->seals);
    }

    public function test_changing_present_to_absent_removes_seal(): void
    {
        [$teacher, $class, $studentA, $studentB] = $this->readyClassWithStudents();

        $this->actingAs($teacher)
            ->post(route('teacher.attendance.store', $class), ['held_on' => '2026-09-08']);

        $session = AttendanceSession::query()->firstOrFail();

        $this->actingAs($teacher)
            ->put(route('teacher.attendance.update', [$class, $session]), [
                'statuses' => [
                    $studentA->id => AttendanceRecord::STATUS_PRESENT,
                    $studentB->id => AttendanceRecord::STATUS_ABSENT,
                ],
            ]);

        $this->assertSame(1, (int) $studentA->enrollmentIn($class)->fresh()->seals);

        $this->actingAs($teacher)
            ->put(route('teacher.attendance.update', [$class, $session]), [
                'statuses' => [
                    $studentA->id => AttendanceRecord::STATUS_ABSENT,
                    $studentB->id => AttendanceRecord::STATUS_PRESENT,
                ],
            ]);

        $this->assertSame(0, (int) $studentA->enrollmentIn($class)->fresh()->seals);
        $this->assertSame(1, (int) $studentB->enrollmentIn($class)->fresh()->seals);
    }

    public function test_deleting_session_reverts_seals(): void
    {
        [$teacher, $class, $studentA, $studentB] = $this->readyClassWithStudents();

        $this->actingAs($teacher)
            ->post(route('teacher.attendance.store', $class), ['held_on' => '2026-09-08']);

        $session = AttendanceSession::query()->firstOrFail();

        $this->actingAs($teacher)
            ->put(route('teacher.attendance.update', [$class, $session]), [
                'statuses' => [
                    $studentA->id => AttendanceRecord::STATUS_PRESENT,
                    $studentB->id => AttendanceRecord::STATUS_ABSENT,
                ],
            ]);

        $this->actingAs($teacher)
            ->delete(route('teacher.attendance.destroy', [$class, $session]))
            ->assertRedirect();

        $this->assertSame(0, AttendanceSession::query()->count());
        $this->assertSame(0, (int) $studentA->enrollmentIn($class)->fresh()->seals);
        $this->assertFalse(app(GradeCalculator::class)->hasGradedAttendance($class));
    }

    public function test_teacher_can_open_attendance_tab_after_generating(): void
    {
        [$teacher, $class, $studentA, $studentB] = $this->readyClassWithStudents();

        $this->actingAs($teacher)
            ->post(route('teacher.attendance.store', $class), ['held_on' => '2026-09-08']);

        $session = AttendanceSession::query()->firstOrFail();

        $this->actingAs($teacher)
            ->get(route('teacher.classes.show', [
                'schoolClass' => $class,
                'tab' => 'chamada',
                'session' => $session->id,
            ]))
            ->assertOk()
            ->assertSee('Gerar chamada')
            ->assertSee('Presente')
            ->assertSee('Ausente')
            ->assertSee('Justificada')
            ->assertSee('name="statuses['.$studentA->id.']"', false)
            ->assertSee('Ana')
            ->assertSee('Bruno');
    }

    public function test_teacher_from_another_class_cannot_manage_attendance(): void
    {
        [$teacher, $class] = $this->readyClassWithStudents();
        $otherTeacher = User::factory()->create([
            'role' => 'teacher',
            'must_change_password' => false,
        ]);
        $this->createAreaForTeacher($otherTeacher, ['slug' => 'outro-'.uniqid()]);

        $this->actingAs($otherTeacher)
            ->post(route('teacher.attendance.store', $class), ['held_on' => '2026-09-08'])
            ->assertForbidden();
    }

    public function test_save_requires_status_for_every_student(): void
    {
        [$teacher, $class, $studentA] = $this->readyClassWithStudents();

        $this->actingAs($teacher)
            ->post(route('teacher.attendance.store', $class), ['held_on' => '2026-09-08']);

        $session = AttendanceSession::query()->firstOrFail();

        $this->actingAs($teacher)
            ->from(route('teacher.classes.show', ['schoolClass' => $class, 'tab' => 'chamada', 'session' => $session->id]))
            ->put(route('teacher.attendance.update', [$class, $session]), [
                'statuses' => [
                    $studentA->id => AttendanceRecord::STATUS_PRESENT,
                ],
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('statuses');
    }

    /**
     * @return array{0: User, 1: SchoolClass, 2: User, 3: User}
     */
    private function readyClassWithStudents(): array
    {
        $teacher = User::factory()->create([
            'role' => 'teacher',
            'must_change_password' => false,
        ]);
        $class = $this->createClassForTeacher($teacher, ['name' => 'Turma Chamada']);

        $studentA = User::factory()->create([
            'name' => 'Ana',
            'role' => 'student',
            'must_change_password' => false,
        ]);
        $studentB = User::factory()->create([
            'name' => 'Bruno',
            'role' => 'student',
            'must_change_password' => false,
        ]);

        $class->students()->attach($studentA->id, [
            'ranking_visible' => true,
            'xp' => 0,
            'behavior_score' => 100,
            'seals' => 0,
        ]);
        $class->students()->attach($studentB->id, [
            'ranking_visible' => true,
            'xp' => 0,
            'behavior_score' => 100,
            'seals' => 0,
        ]);

        return [$teacher, $class, $studentA, $studentB];
    }
}
