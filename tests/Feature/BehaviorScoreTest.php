<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\User;
use App\Services\GameLoopService;
use App\Services\GradeCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BehaviorScoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_behavior_starts_at_100_and_enters_student_average(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $class = $this->createClassForTeacher($teacher, ['name' => 'Turma Comp']);

        $activity = Activity::query()->create([
            'class_id' => $class->id,
            'name' => 'Prova',
            'type' => 'individual',
            'max_score' => 100,
            'weight' => 1,
        ]);

        $student = User::factory()->create(['role' => 'student']);
        $class->students()->attach($student->id, ['ranking_visible' => true, 'xp' => 0]);

        $grades = app(GradeCalculator::class);

        // Sem nota da prova: default 0 (modo sobe do 0) + comportamento 100 => média 50
        $this->assertEquals(50.0, $grades->studentAverage($student, $class), 0.01);
        $this->assertEquals(100.0, $grades->behaviorScore($student, $class), 0.01);

        // Lança prova 50: (50 + 100) / 2 = 75
        app(GameLoopService::class)->recordActivityGrade(
            $class,
            $activity,
            50,
            $teacher,
            student: $student
        );

        $this->assertEquals(75.0, $grades->studentAverage($student->fresh(), $class), 0.01);
    }

    public function test_teacher_can_adjust_behavior_with_quick_buttons(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $class = $this->createClassForTeacher($teacher, ['name' => 'Turma Comp']);

        $student = User::factory()->create(['role' => 'student']);
        $class->students()->attach($student->id, ['ranking_visible' => true, 'xp' => 0, 'behavior_score' => 100]);

        $this->actingAs($teacher)
            ->post(route('teacher.grades.behavior', [$class, $student]), ['delta' => -5])
            ->assertRedirect();

        $enrollment = $student->enrollmentIn($class);
        $this->assertEquals(95.0, (float) $enrollment->behavior_score);

        $this->actingAs($teacher)
            ->post(route('teacher.grades.behavior', [$class, $student]), ['delta' => 1])
            ->assertRedirect();

        $enrollment->refresh();
        $this->assertEquals(96.0, (float) $enrollment->behavior_score);
    }

    public function test_teacher_can_adjust_behavior_by_custom_amount(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $class = $this->createClassForTeacher($teacher, ['name' => 'Turma Comp']);

        $student = User::factory()->create(['role' => 'student']);
        $class->students()->attach($student->id, ['ranking_visible' => true, 'xp' => 0, 'behavior_score' => 80]);

        $this->actingAs($teacher)
            ->post(route('teacher.grades.behavior', [$class, $student]), ['delta' => -12])
            ->assertRedirect();

        $enrollment = $student->enrollmentIn($class);
        $this->assertEquals(68.0, (float) $enrollment->behavior_score);

        $this->actingAs($teacher)
            ->post(route('teacher.grades.behavior', [$class, $student]), ['delta' => 10])
            ->assertRedirect();

        $enrollment->refresh();
        $this->assertEquals(78.0, (float) $enrollment->behavior_score);
    }

    public function test_behavior_json_request_returns_updated_score_without_redirect(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $class = $this->createClassForTeacher($teacher, ['name' => 'Turma Comp']);

        $student = User::factory()->create(['role' => 'student']);
        $class->students()->attach($student->id, ['ranking_visible' => true, 'xp' => 0, 'behavior_score' => 80]);

        $response = $this->actingAs($teacher)
            ->postJson(route('teacher.grades.behavior', [$class, $student]), ['delta' => 7]);

        $response
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('message', 'Comportamento atualizado.');

        $this->assertSame(87.0, (float) $response->json('behavior_score'));
        $this->assertEquals(87.0, (float) $student->enrollmentIn($class)->behavior_score);
    }

    public function test_behavior_rejects_zero_delta(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $class = $this->createClassForTeacher($teacher, ['name' => 'Turma Comp']);

        $student = User::factory()->create(['role' => 'student']);
        $class->students()->attach($student->id, ['ranking_visible' => true, 'xp' => 0, 'behavior_score' => 80]);

        $this->actingAs($teacher)
            ->from(route('teacher.classes.show', ['schoolClass' => $class, 'tab' => 'alunos']))
            ->post(route('teacher.grades.behavior', [$class, $student]), ['delta' => 0])
            ->assertRedirect()
            ->assertSessionHasErrors('delta');

        $this->assertEquals(80.0, (float) $student->enrollmentIn($class)->behavior_score);
    }

    public function test_behavior_rejects_delta_above_100(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $class = $this->createClassForTeacher($teacher, ['name' => 'Turma Comp']);

        $student = User::factory()->create(['role' => 'student']);
        $class->students()->attach($student->id, ['ranking_visible' => true, 'xp' => 0, 'behavior_score' => 80]);

        $this->actingAs($teacher)
            ->postJson(route('teacher.grades.behavior', [$class, $student]), ['delta' => 101])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('delta');

        $this->assertEquals(80.0, (float) $student->enrollmentIn($class)->behavior_score);
    }

    public function test_raising_behavior_grants_xp_to_the_student(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $class = $this->createClassForTeacher($teacher, ['name' => 'Turma Comp']);

        $student = User::factory()->create(['role' => 'student']);
        $class->students()->attach($student->id, ['ranking_visible' => true, 'xp' => 0, 'behavior_score' => 50]);

        app(GameLoopService::class)->adjustBehavior($class, $student, 10, $teacher);

        $enrollment = $student->enrollmentIn($class);
        $this->assertEquals(60.0, (float) $enrollment->behavior_score);
        $this->assertSame(100, (int) $enrollment->xp);
    }

    public function test_unauthenticated_behavior_request_redirects_to_login(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $class = $this->createClassForTeacher($teacher, ['name' => 'Turma Comp']);

        $student = User::factory()->create(['role' => 'student']);
        $class->students()->attach($student->id, ['ranking_visible' => true, 'xp' => 0, 'behavior_score' => 80]);

        $this->post(route('teacher.grades.behavior', [$class, $student]), ['delta' => -1])
            ->assertRedirectToRoute('login');
    }

    public function test_forbids_another_teacher_from_adjusting_behavior(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $class = $this->createClassForTeacher($teacher, ['name' => 'Turma Comp']);

        $student = User::factory()->create(['role' => 'student']);
        $class->students()->attach($student->id, ['ranking_visible' => true, 'xp' => 0, 'behavior_score' => 80]);

        $otherTeacher = User::factory()->create(['role' => 'teacher']);

        $this->actingAs($otherTeacher)
            ->post(route('teacher.grades.behavior', [$class, $student]), ['delta' => -1])
            ->assertForbidden();

        $this->assertEquals(80.0, (float) $student->enrollmentIn($class)->behavior_score);
    }

    public function test_behavior_adjustment_for_student_outside_class_returns_404(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $class = $this->createClassForTeacher($teacher, ['name' => 'Turma Comp']);
        $otherClass = $this->createClassForTeacher($teacher, ['name' => 'Outra Turma']);

        $outsider = User::factory()->create(['role' => 'student']);
        $otherClass->students()->attach($outsider->id, ['ranking_visible' => true, 'xp' => 0, 'behavior_score' => 80]);

        $this->actingAs($teacher)
            ->post(route('teacher.grades.behavior', [$class, $outsider]), ['delta' => -1])
            ->assertNotFound();
    }

    public function test_behavior_is_clamped_between_0_and_100(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $class = $this->createClassForTeacher($teacher, ['name' => 'Turma Comp']);

        $student = User::factory()->create(['role' => 'student']);
        $class->students()->attach($student->id, ['ranking_visible' => true, 'xp' => 0, 'behavior_score' => 2]);

        app(GameLoopService::class)->adjustBehavior($class, $student, -5, $teacher);

        $this->assertEquals(0.0, app(GradeCalculator::class)->behaviorScore($student->fresh(), $class));
    }
}
