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
