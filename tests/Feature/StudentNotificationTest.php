<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\GameAlert;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_json_request_returns_401(): void
    {
        $this->getJson(route('student.notifications'))
            ->assertUnauthorized();
    }

    public function test_teacher_is_redirected_away_from_student_alerts(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);

        $this->actingAs($teacher)
            ->get(route('student.notifications'))
            ->assertRedirect($teacher->homeRoute());
    }

    public function test_lists_unread_alerts_with_same_origin_paths(): void
    {
        $student = $this->enrollStudent();
        $student->notify(new GameAlert('grade', 'Nota lançada', 'Você recebeu 8 em prova.', [
            'url' => 'http://localhost:8000/aluno/arena',
        ]));

        $this->actingAs($student)
            ->getJson(route('student.notifications'))
            ->assertOk()
            ->assertJsonPath('unread', 1)
            ->assertJsonPath('items.0.title', 'Nota lançada')
            ->assertJsonPath('items.0.payload.url', '/aluno/arena');
    }

    public function test_marking_specific_ids_leaves_other_alerts_unread(): void
    {
        $student = $this->enrollStudent();
        $student->notify(new GameAlert('grade', 'Primeira', 'Um.'));
        $student->notify(new GameAlert('badge', 'Segunda', 'Dois.'));

        $alerts = $student->unreadNotifications()->orderBy('id')->get();
        $this->assertCount(2, $alerts);

        $firstId = $alerts[0]->id;
        $secondId = $alerts[1]->id;
        $this->assertNotSame($firstId, $secondId);

        $this->actingAs($student)
            ->postJson(route('student.notifications.read'), ['ids' => [$firstId]])
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertNotNull($student->notifications()->findOrFail($firstId)->read_at);
        $this->assertNull($student->notifications()->findOrFail($secondId)->read_at);
    }

    public function test_marking_all_alerts_clears_the_unread_list(): void
    {
        $student = $this->enrollStudent();
        $student->notify(new GameAlert('grade', 'Primeira', 'Um.'));
        $student->notify(new GameAlert('badge', 'Segunda', 'Dois.'));

        $this->actingAs($student)
            ->postJson(route('student.notifications.read'))
            ->assertOk();

        $this->assertSame(0, $student->unreadNotifications()->count());
    }

    private function enrollStudent(): User
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $class = $this->createClassForTeacher($teacher);
        $student = User::factory()->create([
            'role' => 'student',
            'character_class' => 'guerreiro',
            'must_change_password' => false,
            'character_name' => 'Heroi Teste',
            'character_avatar' => 'lobo',
            'character_approval_status' => 'approved',
        ]);

        $class->students()->attach($student->id, [
            'ranking_visible' => true,
            'xp' => 0,
            'glory' => 0,
            'arena_wins' => 0,
            'arena_losses' => 0,
            'behavior_score' => 100,
        ]);

        return $student->fresh();
    }
}
