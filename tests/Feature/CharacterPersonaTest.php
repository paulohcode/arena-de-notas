<?php

namespace Tests\Feature;

use App\Models\SchoolClass;
use App\Models\User;
use App\Notifications\GameAlert;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class CharacterPersonaTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_request_redirects_to_login(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $class = $this->createClassForTeacher($teacher);
        $student = $this->enrollStudent($class);

        $this->post(route('teacher.characters.approve', [$class, $student]))
            ->assertRedirectToRoute('login');
    }

    public function test_student_submission_stays_pending_and_notifies_the_teacher(): void
    {
        Notification::fake();

        $teacher = User::factory()->create(['role' => 'teacher']);
        $class = $this->createClassForTeacher($teacher);
        $student = $this->enrollStudent($class, 'Ana Souza');

        $this->actingAs($student)
            ->post(route('student.character.update'), [
                'character_class' => 'feiticeira',
                'character_name' => 'Luna Arcana',
                'character_avatar' => 'lua',
            ])
            ->assertRedirect(route('student.dashboard'));

        $this->assertDatabaseHas('users', [
            'id' => $student->id,
            'character_class' => 'feiticeira',
            'pending_character_name' => 'Luna Arcana',
            'pending_character_avatar' => 'lua',
            'character_name' => null,
            'character_approval_status' => 'pending',
        ]);

        Notification::assertSentTo($teacher, GameAlert::class);
    }

    public function test_empty_payload_returns_required_field_messages(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $class = $this->createClassForTeacher($teacher);
        $student = $this->enrollStudent($class);

        $this->actingAs($student)
            ->from(route('student.character.edit'))
            ->post(route('student.character.update'), [])
            ->assertRedirect()
            ->assertSessionHasErrors(['character_class', 'character_name', 'character_avatar']);
    }

    public function test_rejects_a_character_name_already_in_use(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $class = $this->createClassForTeacher($teacher);
        $holder = $this->enrollStudent($class, 'Bruno');
        $holder->update([
            'character_name' => 'Escudo de Ferro',
            'character_avatar' => 'elmo',
            'character_approval_status' => 'approved',
        ]);
        $student = $this->enrollStudent($class, 'Carla');

        $this->actingAs($student)
            ->from(route('student.character.edit'))
            ->post(route('student.character.update'), [
                'character_class' => 'arqueiro',
                'character_name' => 'escudo de ferro',
                'character_avatar' => 'aguia',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors(['character_name' => 'Este nome de personagem já está em uso.']);
    }

    public function test_teacher_approves_persona_and_it_appears_on_the_sheet(): void
    {
        Notification::fake();

        $teacher = User::factory()->create(['role' => 'teacher']);
        $class = $this->createClassForTeacher($teacher);
        $student = $this->enrollStudent($class, 'Ana Souza');
        $student->update([
            'pending_character_name' => 'Luna Arcana',
            'pending_character_avatar' => 'lua',
            'character_approval_status' => 'pending',
        ]);

        $this->actingAs($teacher)
            ->post(route('teacher.characters.approve', [$class, $student]))
            ->assertRedirect(route('teacher.classes.show', ['schoolClass' => $class, 'tab' => 'personagens']))
            ->assertSessionHas('success');

        $student->refresh();

        $this->assertSame('Luna Arcana', $student->character_name);
        $this->assertSame('lua', $student->character_avatar);
        $this->assertSame('approved', $student->character_approval_status);
        $this->assertNull($student->pending_character_name);

        Notification::assertSentTo($student, GameAlert::class);

        $this->actingAs($teacher)
            ->get(route('teacher.students.show', [$class, $student]))
            ->assertOk()
            ->assertSee('Ana Souza')
            ->assertSee('Luna Arcana');
    }

    public function test_teacher_rejects_persona_without_publishing_the_name(): void
    {
        Notification::fake();

        $teacher = User::factory()->create(['role' => 'teacher']);
        $class = $this->createClassForTeacher($teacher);
        $student = $this->enrollStudent($class, 'Ana Souza');
        $student->update([
            'pending_character_name' => 'Nome Feio',
            'pending_character_avatar' => 'lobo',
            'character_approval_status' => 'pending',
        ]);

        $this->actingAs($teacher)
            ->post(route('teacher.characters.reject', [$class, $student]), [
                'reason' => 'Escolha outro nome.',
            ])
            ->assertRedirect(route('teacher.classes.show', ['schoolClass' => $class, 'tab' => 'personagens']));

        $student->refresh();

        $this->assertNull($student->character_name);
        $this->assertSame('rejected', $student->character_approval_status);
        $this->assertSame('Escolha outro nome.', $student->character_rejection_reason);
        $this->assertNull($student->pending_character_name);

        Notification::assertSentTo($student, GameAlert::class);
    }

    public function test_approving_already_approved_persona_is_idempotent(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $class = $this->createClassForTeacher($teacher);
        $student = $this->enrollStudent($class, 'Ana Souza');
        $student->update([
            'character_name' => 'Luna Arcana',
            'character_avatar' => 'lua',
            'character_approval_status' => 'approved',
            'pending_character_name' => null,
            'pending_character_avatar' => null,
        ]);

        $this->actingAs($teacher)
            ->post(route('teacher.characters.approve', [$class, $student]))
            ->assertRedirect(route('teacher.classes.show', ['schoolClass' => $class, 'tab' => 'personagens']))
            ->assertSessionHas('success');

        $student->refresh();
        $this->assertSame('Luna Arcana', $student->character_name);
        $this->assertSame('approved', $student->character_approval_status);
    }

    public function test_rejecting_when_not_pending_returns_validation_error(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $class = $this->createClassForTeacher($teacher);
        $student = $this->enrollStudent($class, 'Ana Souza');
        $student->update([
            'character_name' => 'Luna Arcana',
            'character_avatar' => 'lua',
            'character_approval_status' => 'approved',
        ]);

        $this->actingAs($teacher)
            ->from(route('teacher.classes.show', ['schoolClass' => $class, 'tab' => 'personagens']))
            ->post(route('teacher.characters.reject', [$class, $student]))
            ->assertRedirect(route('teacher.classes.show', ['schoolClass' => $class, 'tab' => 'personagens']))
            ->assertSessionHasErrors('character');
    }

    public function test_forbids_another_teacher_from_approving_the_persona(): void
    {
        $owner = User::factory()->create(['role' => 'teacher']);
        $class = $this->createClassForTeacher($owner);
        $student = $this->enrollStudent($class);
        $student->update([
            'pending_character_name' => 'Luna Arcana',
            'pending_character_avatar' => 'lua',
            'character_approval_status' => 'pending',
        ]);
        $other = User::factory()->create(['role' => 'teacher']);

        $this->actingAs($other)
            ->post(route('teacher.characters.approve', [$class, $student]))
            ->assertForbidden();

        $this->assertSame('pending', $student->fresh()->character_approval_status);
    }

    public function test_student_from_another_class_returns_404_on_approve(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $class = $this->createClassForTeacher($teacher);
        $otherClass = $this->createClassForTeacher($teacher, ['area' => $class->area]);
        $foreign = $this->enrollStudent($otherClass);
        $foreign->update([
            'pending_character_name' => 'Luna Arcana',
            'pending_character_avatar' => 'lua',
            'character_approval_status' => 'pending',
        ]);

        $this->actingAs($teacher)
            ->post(route('teacher.characters.approve', [$class, $foreign]))
            ->assertNotFound();
    }

    public function test_unauthenticated_approve_all_redirects_to_login(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $class = $this->createClassForTeacher($teacher);

        $this->post(route('teacher.characters.approve-all', $class))
            ->assertRedirectToRoute('login');
    }

    public function test_teacher_approves_all_pending_personas_in_the_class(): void
    {
        Notification::fake();

        $teacher = User::factory()->create(['role' => 'teacher']);
        $class = $this->createClassForTeacher($teacher);
        $ana = $this->enrollStudent($class, 'Ana Souza');
        $ana->update([
            'pending_character_name' => 'Luna Arcana',
            'pending_character_avatar' => 'lua',
            'character_approval_status' => 'pending',
        ]);
        $bruno = $this->enrollStudent($class, 'Bruno Lima');
        $bruno->update([
            'pending_character_name' => 'Escudo de Ferro',
            'pending_character_avatar' => 'elmo',
            'character_approval_status' => 'pending',
        ]);

        $this->actingAs($teacher)
            ->post(route('teacher.characters.approve-all', $class))
            ->assertRedirect(route('teacher.classes.show', ['schoolClass' => $class, 'tab' => 'personagens']))
            ->assertSessionHas('success', '2 personagens aprovados.');

        $this->assertSame('Luna Arcana', $ana->fresh()->character_name);
        $this->assertSame('approved', $ana->fresh()->character_approval_status);
        $this->assertSame('Escudo de Ferro', $bruno->fresh()->character_name);
        $this->assertSame('approved', $bruno->fresh()->character_approval_status);

        Notification::assertSentTo($ana, GameAlert::class);
        Notification::assertSentTo($bruno, GameAlert::class);
    }

    public function test_approve_all_skips_duplicate_names_and_approves_the_rest(): void
    {
        Notification::fake();

        $teacher = User::factory()->create(['role' => 'teacher']);
        $class = $this->createClassForTeacher($teacher);
        $ana = $this->enrollStudent($class, 'Ana Souza');
        $ana->update([
            'pending_character_name' => 'Luna Arcana',
            'pending_character_avatar' => 'lua',
            'character_approval_status' => 'pending',
        ]);
        $bruno = $this->enrollStudent($class, 'Bruno Lima');
        $bruno->update([
            'pending_character_name' => 'Luna Arcana',
            'pending_character_avatar' => 'elmo',
            'character_approval_status' => 'pending',
        ]);
        $carla = $this->enrollStudent($class, 'Carla Dias');
        $carla->update([
            'pending_character_name' => 'Flecha Certa',
            'pending_character_avatar' => 'aguia',
            'character_approval_status' => 'pending',
        ]);

        $this->actingAs($teacher)
            ->post(route('teacher.characters.approve-all', $class))
            ->assertRedirect(route('teacher.classes.show', ['schoolClass' => $class, 'tab' => 'personagens']))
            ->assertSessionHas('success', '2 personagem(ns) aprovado(s). 1 ignorado(s) por nome duplicado.');

        $this->assertSame('approved', $ana->fresh()->character_approval_status);
        $this->assertSame('pending', $bruno->fresh()->character_approval_status);
        $this->assertNull($bruno->fresh()->character_name);
        $this->assertSame('approved', $carla->fresh()->character_approval_status);
    }

    public function test_forbids_another_teacher_from_approving_all_personas(): void
    {
        $owner = User::factory()->create(['role' => 'teacher']);
        $class = $this->createClassForTeacher($owner);
        $student = $this->enrollStudent($class);
        $student->update([
            'pending_character_name' => 'Luna Arcana',
            'pending_character_avatar' => 'lua',
            'character_approval_status' => 'pending',
        ]);
        $other = User::factory()->create(['role' => 'teacher']);

        $this->actingAs($other)
            ->post(route('teacher.characters.approve-all', $class))
            ->assertForbidden();

        $this->assertSame('pending', $student->fresh()->character_approval_status);
    }

    public function test_escapes_pending_character_name_on_the_teacher_tab(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $class = $this->createClassForTeacher($teacher);
        $student = $this->enrollStudent($class, 'Ana');
        $student->update([
            'pending_character_name' => "<script>alert('xss')</script>",
            'pending_character_avatar' => 'lobo',
            'character_approval_status' => 'pending',
        ]);

        $html = $this->actingAs($teacher)
            ->get(route('teacher.classes.show', ['schoolClass' => $class, 'tab' => 'personagens']))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringNotContainsString("<script>alert('xss')</script>", $html);
    }

    public function test_public_ranking_hides_pending_character_name(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $class = $this->createClassForTeacher($teacher);
        $student = $this->enrollStudent($class, 'Ana Souza');
        $student->update([
            'pending_character_name' => 'Luna Arcana',
            'pending_character_avatar' => 'lua',
            'character_approval_status' => 'pending',
        ]);

        $this->get(route('ranking.show', $class))
            ->assertOk()
            ->assertSee('Ana Souza')
            ->assertDontSee('Luna Arcana');
    }

    private function enrollStudent(SchoolClass $class, string $name = 'Aluno Teste'): User
    {
        $student = User::factory()->create([
            'name' => $name,
            'role' => 'student',
            'character_class' => 'guerreiro',
            'must_change_password' => false,
        ]);

        $class->students()->attach($student->id, [
            'ranking_visible' => true,
            'xp' => 0,
            'behavior_score' => 100,
        ]);

        return $student;
    }
}
