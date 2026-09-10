<?php

namespace Tests\Feature;

use App\Models\EnrollmentCosmetic;
use App\Models\GameEvent;
use App\Models\GameEventQuestion;
use App\Models\SchoolClass;
use App\Models\User;
use App\Services\GameEventService;
use App\Support\CosmeticCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GameEventPrizeTest extends TestCase
{
    use RefreshDatabase;

    public function test_class_event_awards_prize_to_winner_on_close(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $class = $this->createClassForTeacher($teacher);
        $winner = $this->enrollStudent($class, 'Vencedor');
        $loser = $this->enrollStudent($class, 'Segundo');

        $event = app(GameEventService::class)->create($teacher, [
            'title' => 'Premio turma',
            'kind' => GameEvent::KIND_CLASS,
            'mode' => GameEvent::MODE_WINDOW,
            'question_seconds' => 60,
            'starts_at' => now()->subMinute()->toDateTimeString(),
            'ends_at' => now()->addHour()->toDateTimeString(),
            'publish' => true,
            'questions' => [[
                'prompt' => 'Certa?',
                'type' => GameEventQuestion::TYPE_TRUE_FALSE,
                'options' => ['Verdadeiro', 'Falso'],
                'correct_index' => 0,
            ]],
            'prize' => [
                'name' => 'Coroa da Turma',
                'slot' => CosmeticCatalog::SLOT_ACCESSORY,
                'price' => 0,
                'currency' => CosmeticCatalog::CURRENCY_RELICS,
                'rarity' => 'epic',
                'icon' => '👑',
            ],
        ], $class);

        $service = app(GameEventService::class);
        $question = $event->questions()->firstOrFail();

        $service->startOrResumeAttempt($event, $winner, $class);
        $service->answer($event->fresh(), $winner, $class, $question->id, 0);

        $service->startOrResumeAttempt($event, $loser, $class);
        $service->answer($event->fresh(), $loser, $class, $question->id, 1);

        $service->close($event->fresh());

        $enrollment = $winner->enrollmentIn($class);
        $this->assertTrue(
            EnrollmentCosmetic::query()
                ->where('enrollment_id', $enrollment->id)
                ->where('item_key', $event->prizeItem->item_key)
                ->exists()
        );

        $this->assertFalse(
            EnrollmentCosmetic::query()
                ->where('enrollment_id', $loser->enrollmentIn($class)->id)
                ->where('item_key', $event->prizeItem->item_key)
                ->exists()
        );
    }

    public function test_realm_event_awards_best_student_across_classes(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $area = $this->createAreaForTeacher($teacher);
        $classA = $this->createClassForTeacher($teacher, ['name' => 'A', 'area' => $area]);
        $classB = $this->createClassForTeacher($teacher, ['name' => 'B', 'area' => $area]);
        $fromA = $this->enrollStudent($classA, 'De A');
        $fromB = $this->enrollStudent($classB, 'De B');

        $event = app(GameEventService::class)->create($teacher, [
            'title' => 'Premio reino',
            'kind' => GameEvent::KIND_REALM,
            'mode' => GameEvent::MODE_WINDOW,
            'question_seconds' => 60,
            'starts_at' => now()->subMinute()->toDateTimeString(),
            'ends_at' => now()->addHour()->toDateTimeString(),
            'publish' => true,
            'questions' => [[
                'prompt' => 'Certa?',
                'type' => GameEventQuestion::TYPE_TRUE_FALSE,
                'options' => ['Verdadeiro', 'Falso'],
                'correct_index' => 0,
            ]],
            'prize' => [
                'name' => 'Cetro do Reino',
                'slot' => CosmeticCatalog::SLOT_TITLE,
                'price' => 0,
                'currency' => CosmeticCatalog::CURRENCY_RELICS,
                'rarity' => 'epic',
                'icon' => '🪄',
            ],
        ], area: $area);

        $service = app(GameEventService::class);
        $question = $event->questions()->firstOrFail();

        $service->startOrResumeAttempt($event, $fromA, $classA);
        $service->answer($event->fresh(), $fromA, $classA, $question->id, 1);

        $service->startOrResumeAttempt($event, $fromB, $classB);
        $service->answer($event->fresh(), $fromB, $classB, $question->id, 0);

        $service->close($event->fresh());

        $this->assertTrue(
            EnrollmentCosmetic::query()
                ->where('enrollment_id', $fromB->enrollmentIn($classB)->id)
                ->where('item_key', $event->fresh()->prizeItem->item_key)
                ->exists()
        );
    }

    public function test_admin_can_create_realm_event(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'must_change_password' => false]);
        $area = $this->createArea();

        $this->actingAs($admin)
            ->post(route('admin.areas.events.store', $area), [
                'title' => 'Grande evento',
                'mode' => GameEvent::MODE_WINDOW,
                'question_seconds' => 30,
                'starts_at' => now()->subMinute()->format('Y-m-d\TH:i'),
                'ends_at' => now()->addHour()->format('Y-m-d\TH:i'),
                'prize_name' => 'Medalha',
                'prize_slot' => CosmeticCatalog::SLOT_ACCESSORY,
                'prize_icon' => '🥇',
                'prize_rarity' => 'rare',
                'questions' => [[
                    'prompt' => 'Ok?',
                    'type' => 'true_false',
                    'options' => ['Verdadeiro', 'Falso'],
                    'correct_index' => 0,
                ]],
            ])
            ->assertRedirect(route('admin.areas.events.index', $area))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('game_events', [
            'area_id' => $area->id,
            'kind' => GameEvent::KIND_REALM,
            'title' => 'Grande evento',
        ]);
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
