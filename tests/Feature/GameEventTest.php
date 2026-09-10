<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\GameEvent;
use App\Models\GameEventQuestion;
use App\Models\SchoolClass;
use App\Models\User;
use App\Services\GameEventService;
use App\Support\CosmeticCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class GameEventTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_student_events_redirect_to_login(): void
    {
        $this->get(route('student.events.index'))
            ->assertRedirectToRoute('login');
    }

    public function test_teacher_can_create_class_event_with_prize(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $class = $this->createClassForTeacher($teacher);

        $this->actingAs($teacher)
            ->post(route('teacher.events.store', $class), $this->classEventPayload())
            ->assertRedirect()
            ->assertSessionHas('success');

        $event = GameEvent::query()->firstOrFail();
        $this->assertSame(GameEvent::KIND_CLASS, $event->kind);
        $this->assertSame(2, $event->questions()->count());
        $this->assertNotNull($event->prize_item_id);
        $this->assertTrue((bool) $event->prizeItem->prize_only);
        $this->assertFalse(CosmeticCatalog::isAvailableTo($event->prizeItem->item_key, $class));
    }

    public function test_teacher_can_create_activity_event(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $class = $this->createClassForTeacher($teacher);

        $this->actingAs($teacher)
            ->post(route('teacher.activities.event.store', $class), $this->activityEventPayload())
            ->assertRedirect()
            ->assertSessionHas('success');

        $activity = Activity::query()->firstOrFail();
        $this->assertSame(Activity::TYPE_EVENT, $activity->type);
        $this->assertSame(100, (int) $activity->max_score);

        $event = GameEvent::query()->firstOrFail();
        $this->assertSame(GameEvent::KIND_ACTIVITY, $event->kind);
        $this->assertSame($activity->id, $event->activity_id);
        $this->assertSame(5, (int) $event->relics_per_correct);
    }

    public function test_student_from_other_class_gets_404(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $classA = $this->createClassForTeacher($teacher, ['name' => 'Turma A']);
        $classB = $this->createClassForTeacher($teacher, ['name' => 'Turma B']);
        $outsider = $this->enrollStudent($classB, 'Fora');

        $event = $this->makeWindowEvent($teacher, $classA, GameEvent::KIND_CLASS);

        $this->actingAs($outsider)
            ->get(route('student.events.show', $event))
            ->assertNotFound();
    }

    public function test_student_can_answer_window_event_once_per_question(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $class = $this->createClassForTeacher($teacher);
        $student = $this->enrollStudent($class, 'Ana');
        $event = $this->makeWindowEvent($teacher, $class, GameEvent::KIND_CLASS, questionCount: 1);

        $this->actingAs($student)
            ->post(route('student.events.join', $event))
            ->assertRedirect();

        $question = $event->questions()->firstOrFail();

        $this->actingAs($student)
            ->postJson(route('student.events.answer', $event), [
                'question_id' => $question->id,
                'selected_index' => 0,
            ])
            ->assertOk();

        $this->actingAs($student)
            ->postJson(route('student.events.answer', $event), [
                'question_id' => $question->id,
                'selected_index' => 0,
            ])
            ->assertUnprocessable();
    }

    public function test_faster_correct_answers_win_tiebreak(): void
    {
        Carbon::setTestNow('2026-09-10 12:00:00');

        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $class = $this->createClassForTeacher($teacher);
        $fast = $this->enrollStudent($class, 'Rapido');
        $slow = $this->enrollStudent($class, 'Lento');
        $event = $this->makeWindowEvent($teacher, $class, GameEvent::KIND_CLASS, questionCount: 1);
        $question = $event->questions()->firstOrFail();
        $service = app(GameEventService::class);

        $service->startOrResumeAttempt($event, $fast, $class);
        Carbon::setTestNow('2026-09-10 12:00:01');
        $service->answer($event, $fast, $class, $question->id, 0);

        Carbon::setTestNow('2026-09-10 12:00:05');
        $service->startOrResumeAttempt($event, $slow, $class);
        Carbon::setTestNow('2026-09-10 12:00:10');
        $service->answer($event, $slow, $class, $question->id, 0);

        $winner = $service->winner($event->fresh());
        $this->assertSame($fast->id, $winner?->student_id);

        Carbon::setTestNow();
    }

    public function test_rejects_answer_after_question_timeout(): void
    {
        Carbon::setTestNow('2026-09-10 12:00:00');

        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $class = $this->createClassForTeacher($teacher);
        $student = $this->enrollStudent($class, 'Ana');
        $event = $this->makeWindowEvent($teacher, $class, GameEvent::KIND_CLASS, questionCount: 1, seconds: 5);
        $question = $event->questions()->firstOrFail();
        $service = app(GameEventService::class);

        $service->startOrResumeAttempt($event, $student, $class);
        Carbon::setTestNow('2026-09-10 12:00:10');

        $this->expectException(ValidationException::class);
        $service->answer($event, $student, $class, $question->id, 0);

        Carbon::setTestNow();
    }

    /**
     * @return array<string, mixed>
     */
    private function classEventPayload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Torneio da turma',
            'mode' => GameEvent::MODE_WINDOW,
            'question_seconds' => 30,
            'starts_at' => now()->subMinute()->format('Y-m-d\TH:i'),
            'ends_at' => now()->addHour()->format('Y-m-d\TH:i'),
            'prize_name' => 'Trofeu Turma',
            'prize_slot' => CosmeticCatalog::SLOT_ACCESSORY,
            'prize_icon' => '🏆',
            'prize_rarity' => 'rare',
            'questions' => [
                [
                    'prompt' => '2+2?',
                    'type' => 'multiple_choice',
                    'options' => ['3', '4', '5'],
                    'correct_index' => 1,
                ],
                [
                    'prompt' => 'A Terra é redonda?',
                    'type' => 'true_false',
                    'options' => ['Verdadeiro', 'Falso'],
                    'correct_index' => 0,
                ],
            ],
        ], $overrides);
    }

    /**
     * @return array<string, mixed>
     */
    private function activityEventPayload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Quiz nota',
            'mode' => GameEvent::MODE_WINDOW,
            'question_seconds' => 30,
            'weight' => 2,
            'relics_per_correct' => 5,
            'seals_per_correct' => 1,
            'auras_per_correct' => 0,
            'starts_at' => now()->subMinute()->format('Y-m-d\TH:i'),
            'ends_at' => now()->addHour()->format('Y-m-d\TH:i'),
            'questions' => [
                [
                    'prompt' => 'Q1',
                    'type' => 'true_false',
                    'options' => ['Verdadeiro', 'Falso'],
                    'correct_index' => 0,
                ],
                [
                    'prompt' => 'Q2',
                    'type' => 'true_false',
                    'options' => ['Verdadeiro', 'Falso'],
                    'correct_index' => 0,
                ],
            ],
        ], $overrides);
    }

    private function makeWindowEvent(
        User $teacher,
        SchoolClass $class,
        string $kind,
        int $questionCount = 2,
        int $seconds = 30,
    ): GameEvent {
        $questions = [];
        for ($i = 0; $i < $questionCount; $i++) {
            $questions[] = [
                'prompt' => 'Pergunta '.($i + 1).'?',
                'type' => GameEventQuestion::TYPE_TRUE_FALSE,
                'options' => ['Verdadeiro', 'Falso'],
                'correct_index' => 0,
            ];
        }

        $payload = [
            'title' => 'Evento teste',
            'kind' => $kind,
            'mode' => GameEvent::MODE_WINDOW,
            'question_seconds' => $seconds,
            'starts_at' => now()->subMinute()->toDateTimeString(),
            'ends_at' => now()->addHour()->toDateTimeString(),
            'publish' => true,
            'questions' => $questions,
            'weight' => 1,
            'relics_per_correct' => 0,
            'seals_per_correct' => 0,
            'auras_per_correct' => 0,
        ];

        if ($kind !== GameEvent::KIND_ACTIVITY) {
            $payload['prize'] = [
                'name' => 'Item Premio',
                'slot' => CosmeticCatalog::SLOT_TITLE,
                'price' => 0,
                'currency' => CosmeticCatalog::CURRENCY_RELICS,
                'rarity' => 'epic',
                'icon' => '👑',
            ];
        }

        return app(GameEventService::class)->create($teacher, $payload, $class);
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
