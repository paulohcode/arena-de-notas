<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\LedgerEntry;
use App\Models\SchoolClass;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BulkActivityGradesTest extends TestCase
{
    use RefreshDatabase;

    public function test_teacher_can_save_multiple_individual_grades_at_once(): void
    {
        [$teacher, $class, $activity, $alice, $bob] = $this->makeIndividualSetup();

        $this->actingAs($teacher)
            ->from(route('teacher.classes.show', ['schoolClass' => $class, 'tab' => 'notas']))
            ->post(route('teacher.grades.store', $class), [
                'activity_id' => $activity->id,
                'scores' => [
                    $alice->id => 80,
                    $bob->id => 65.5,
                ],
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('ledger_entries', [
            'activity_id' => $activity->id,
            'student_id' => $alice->id,
            'type' => 'activity',
            'raw_score' => 80,
        ]);

        $this->assertDatabaseHas('ledger_entries', [
            'activity_id' => $activity->id,
            'student_id' => $bob->id,
            'type' => 'activity',
            'raw_score' => 65.5,
        ]);
    }

    public function test_teacher_can_save_multiple_team_grades_at_once(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $class = $this->makeClassFor($teacher);

        $activity = Activity::query()->create([
            'class_id' => $class->id,
            'name' => 'Missão em equipe',
            'type' => 'team',
            'max_score' => 100,
            'weight' => 1,
        ]);

        $alpha = Team::query()->create([
            'class_id' => $class->id,
            'name' => 'Alpha',
            'emblem' => 'shield',
        ]);
        $beta = Team::query()->create([
            'class_id' => $class->id,
            'name' => 'Beta',
            'emblem' => 'sword',
        ]);

        $this->actingAs($teacher)
            ->post(route('teacher.grades.store', $class), [
                'activity_id' => $activity->id,
                'scores' => [
                    $alpha->id => 90,
                    $beta->id => 70,
                ],
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('ledger_entries', [
            'activity_id' => $activity->id,
            'team_id' => $alpha->id,
            'type' => 'activity',
            'raw_score' => 90,
        ]);
        $this->assertDatabaseHas('ledger_entries', [
            'activity_id' => $activity->id,
            'team_id' => $beta->id,
            'type' => 'activity',
            'raw_score' => 70,
        ]);
    }

    public function test_empty_scores_returns_validation_error(): void
    {
        [$teacher, $class, $activity] = $this->makeIndividualSetup();

        $this->actingAs($teacher)
            ->from(route('teacher.classes.show', ['schoolClass' => $class, 'tab' => 'notas']))
            ->post(route('teacher.grades.store', $class), [
                'activity_id' => $activity->id,
                'scores' => [
                    '1' => '',
                ],
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('scores');

        $this->assertSame(0, LedgerEntry::query()->count());
    }

    public function test_forbids_another_teacher_from_saving_grades(): void
    {
        [$owner, $class, $activity, $alice] = $this->makeIndividualSetup();
        $otherTeacher = User::factory()->create(['role' => 'teacher']);

        $this->actingAs($otherTeacher)
            ->post(route('teacher.grades.store', $class), [
                'activity_id' => $activity->id,
                'scores' => [
                    $alice->id => 50,
                ],
            ])
            ->assertForbidden();
    }

    public function test_updating_existing_grade_keeps_single_ledger_entry(): void
    {
        [$teacher, $class, $activity, $alice] = $this->makeIndividualSetup();

        $this->actingAs($teacher)
            ->post(route('teacher.grades.store', $class), [
                'activity_id' => $activity->id,
                'scores' => [$alice->id => 40],
            ])
            ->assertRedirect();

        $this->actingAs($teacher)
            ->post(route('teacher.grades.store', $class), [
                'activity_id' => $activity->id,
                'scores' => [$alice->id => 95],
            ])
            ->assertRedirect();

        $this->assertSame(1, LedgerEntry::query()
            ->where('activity_id', $activity->id)
            ->where('student_id', $alice->id)
            ->count());

        $this->assertDatabaseHas('ledger_entries', [
            'activity_id' => $activity->id,
            'student_id' => $alice->id,
            'raw_score' => 95,
        ]);
    }

    /**
     * @return array{0: User, 1: SchoolClass, 2: Activity, 3: User, 4: User}
     */
    private function makeIndividualSetup(): array
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $class = $this->makeClassFor($teacher);

        $activity = Activity::query()->create([
            'class_id' => $class->id,
            'name' => 'Prova',
            'type' => 'individual',
            'max_score' => 100,
            'weight' => 1,
        ]);

        $alice = User::factory()->create(['role' => 'student', 'name' => 'Alice']);
        $bob = User::factory()->create(['role' => 'student', 'name' => 'Bob']);
        $class->students()->attach($alice->id, ['ranking_visible' => true, 'xp' => 0]);
        $class->students()->attach($bob->id, ['ranking_visible' => true, 'xp' => 0]);

        return [$teacher, $class, $activity, $alice, $bob];
    }

    private function makeClassFor(User $teacher): SchoolClass
    {
        return $this->createClassForTeacher($teacher, ['name' => 'Turma Notas']);
    }
}
