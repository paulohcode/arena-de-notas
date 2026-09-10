<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\GameEvent;
use App\Models\GameEventQuestion;
use App\Models\LedgerEntry;
use App\Models\SchoolClass;
use App\Models\User;
use App\Services\GameEventService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GameEventActivityGradeTest extends TestCase
{
    use RefreshDatabase;

    public function test_ten_questions_five_correct_gives_grade_fifty(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $class = $this->createClassForTeacher($teacher);
        $student = $this->enrollStudent($class, 'Ana');

        $questions = [];
        for ($i = 0; $i < 10; $i++) {
            $questions[] = [
                'prompt' => 'P'.($i + 1).'?',
                'type' => GameEventQuestion::TYPE_TRUE_FALSE,
                'options' => ['Verdadeiro', 'Falso'],
                'correct_index' => 0,
            ];
        }

        $event = app(GameEventService::class)->create($teacher, [
            'title' => 'Prova quiz',
            'kind' => GameEvent::KIND_ACTIVITY,
            'mode' => GameEvent::MODE_WINDOW,
            'question_seconds' => 60,
            'starts_at' => now()->subMinute()->toDateTimeString(),
            'ends_at' => now()->addHour()->toDateTimeString(),
            'publish' => true,
            'weight' => 1,
            'max_score' => 100,
            'relics_per_correct' => 2,
            'seals_per_correct' => 1,
            'auras_per_correct' => 0,
            'questions' => $questions,
        ], $class);

        $service = app(GameEventService::class);
        $service->startOrResumeAttempt($event, $student, $class);

        foreach ($event->questions()->orderBy('position')->get() as $index => $question) {
            $selected = $index < 5 ? 0 : 1;
            $service->answer($event->fresh(), $student, $class, $question->id, $selected);
        }

        $entry = LedgerEntry::query()
            ->where('activity_id', $event->activity_id)
            ->where('student_id', $student->id)
            ->first();

        $this->assertNotNull($entry);
        $this->assertSame(50.0, (float) $entry->raw_score);

        $enrollment = $student->enrollmentIn($class)->fresh();
        $this->assertSame(10, (int) $enrollment->relics);
        $this->assertSame(5, (int) $enrollment->seals);

        $activity = Activity::query()->findOrFail($event->activity_id);
        $this->assertTrue($activity->isEvent());
    }

    public function test_manual_grade_rejected_for_event_activity(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $class = $this->createClassForTeacher($teacher);
        $student = $this->enrollStudent($class, 'Ana');

        $event = app(GameEventService::class)->create($teacher, [
            'title' => 'Prova quiz',
            'kind' => GameEvent::KIND_ACTIVITY,
            'mode' => GameEvent::MODE_WINDOW,
            'question_seconds' => 30,
            'publish' => true,
            'weight' => 1,
            'questions' => [[
                'prompt' => 'Ok?',
                'type' => GameEventQuestion::TYPE_TRUE_FALSE,
                'options' => ['Verdadeiro', 'Falso'],
                'correct_index' => 0,
            ]],
        ], $class);

        $this->actingAs($teacher)
            ->from(route('teacher.classes.show', ['schoolClass' => $class, 'tab' => 'notas']))
            ->post(route('teacher.grades.store', $class), [
                'activity_id' => $event->activity_id,
                'scores' => [$student->id => 80],
            ])
            ->assertRedirect()
            ->assertSessionHasErrors(['activity_id']);
    }

    private function enrollStudent(SchoolClass $class, string $name): User
    {
        $student = User::factory()->create([
            'name' => $name,
            'role' => 'student',
            'character_class' => 'guerreiro',
            'must_change_password' => false,
            'character_name' => 'Hero '.$name,
            'character_avatar' => 'lobo',
            'character_approval_status' => 'approved',
        ]);

        $class->students()->syncWithoutDetaching([
            $student->id => [
                'ranking_visible' => true,
                'xp' => 0,
                'glory' => 0,
                'relics' => 0,
                'seals' => 0,
                'arena_wins' => 0,
                'arena_losses' => 0,
                'behavior_score' => 100,
            ],
        ]);

        return $student->fresh();
    }
}
