<?php

namespace Tests\Feature;

use App\Models\SchoolClass;
use App\Models\User;
use App\Notifications\GameAlert;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class TeacherStudentCharacterTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_request_redirects_to_login(): void
    {
        [$class, $student] = $this->classWithStudent();

        $this->put(route('teacher.characters.update', [$class, $student]), $this->characterPayload())
            ->assertRedirectToRoute('login');
    }

    public function test_student_is_redirected_away_from_staff_character_update(): void
    {
        [$class, $student] = $this->classWithStudent();

        $this->actingAs($student)
            ->put(route('teacher.characters.update', [$class, $student]), $this->characterPayload())
            ->assertRedirect(route('student.dashboard'));

        $student->refresh();

        $this->assertSame('guerreiro', $student->character_class);
        $this->assertSame('Aluno Teste', $student->name);
    }

    public function test_forbids_another_teacher_from_updating_the_character(): void
    {
        [$class, $student] = $this->classWithStudent();
        $other = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);

        $this->actingAs($other)
            ->put(route('teacher.characters.update', [$class, $student]), $this->characterPayload())
            ->assertForbidden();

        $student->refresh();

        $this->assertSame('guerreiro', $student->character_class);
        $this->assertSame('Aluno Teste', $student->name);
    }

    public function test_student_from_another_class_returns_404(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $class = $this->createClassForTeacher($teacher);
        $otherClass = $this->createClassForTeacher($teacher, ['area' => $class->area]);
        $foreignStudent = $this->enrollStudent($otherClass, 'Aluno de fora');

        $this->actingAs($teacher)
            ->put(route('teacher.characters.update', [$class, $foreignStudent]), $this->characterPayload())
            ->assertNotFound();
    }

    public function test_teacher_updates_class_and_avatar_from_the_profile(): void
    {
        Notification::fake();
        [$class, $student, $teacher] = $this->classWithStudent();

        $this->actingAs($teacher)
            ->put(route('teacher.characters.update', [$class, $student]), $this->characterPayload())
            ->assertRedirectToRoute('teacher.students.show', [$class, $student])
            ->assertSessionHas('success');

        $student->refresh();

        $this->assertSame('Aluno Teste', $student->name);
        $this->assertSame('mago', $student->character_class);
        $this->assertSame('Sombra Azul', $student->character_name);
        $this->assertSame('fenix', $student->character_avatar);
        $this->assertSame('approved', $student->character_approval_status);
        $this->assertNull($student->pending_character_name);
        $this->assertNull($student->pending_character_avatar);

        Notification::assertSentTo($student, GameAlert::class);
    }

    public function test_admin_updates_class_and_avatar_from_the_profile(): void
    {
        [$class, $student] = $this->classWithStudent();
        $admin = User::factory()->create(['role' => 'admin', 'must_change_password' => false]);

        $this->actingAs($admin)
            ->put(route('teacher.characters.update', [$class, $student]), $this->characterPayload([
                'character_class' => 'paladino',
                'character_name' => 'Escudo Solar',
                'character_avatar' => 'leao',
            ]))
            ->assertRedirectToRoute('teacher.students.show', [$class, $student]);

        $student->refresh();

        $this->assertSame('Aluno Teste', $student->name);
        $this->assertSame('paladino', $student->character_class);
        $this->assertSame('Escudo Solar', $student->character_name);
        $this->assertSame('leao', $student->character_avatar);
    }

    public function test_teacher_profile_shows_the_identity_form(): void
    {
        [$class, $student, $teacher] = $this->classWithStudent();

        $this->actingAs($teacher)
            ->get(route('teacher.students.show', [$class, $student]))
            ->assertOk()
            ->assertSee('Identidade do aluno')
            ->assertSee('Salvar identidade')
            ->assertSee('name="character_class"', false)
            ->assertSee(route('teacher.characters.update', [$class, $student]), false);
    }

    public function test_empty_payload_returns_validation_errors(): void
    {
        [$class, $student, $teacher] = $this->classWithStudent();

        $this->actingAs($teacher)
            ->from(route('teacher.students.show', [$class, $student]))
            ->put(route('teacher.characters.update', [$class, $student]), [])
            ->assertRedirectToRoute('teacher.students.show', [$class, $student])
            ->assertSessionHasErrors(['character_class', 'character_name', 'character_avatar']);
    }

    public function test_rejects_an_unknown_character_class(): void
    {
        [$class, $student, $teacher] = $this->classWithStudent();

        $this->actingAs($teacher)
            ->from(route('teacher.students.show', [$class, $student]))
            ->put(route('teacher.characters.update', [$class, $student]), $this->characterPayload([
                'character_class' => 'ninja',
            ]))
            ->assertRedirectToRoute('teacher.students.show', [$class, $student])
            ->assertSessionHasErrors('character_class');

        $this->assertSame('guerreiro', $student->fresh()->character_class);
    }

    public function test_rejects_a_character_name_already_in_use(): void
    {
        [$class, $student, $teacher] = $this->classWithStudent();
        $this->enrollStudent($class, 'Outro Aluno', [
            'character_name' => 'Sombra Azul',
        ]);

        $this->actingAs($teacher)
            ->from(route('teacher.students.show', [$class, $student]))
            ->put(route('teacher.characters.update', [$class, $student]), $this->characterPayload())
            ->assertRedirectToRoute('teacher.students.show', [$class, $student])
            ->assertSessionHasErrors(['character_name' => 'Este nome de personagem já está em uso.']);

        $this->assertNull($student->fresh()->character_name);
    }

    public function test_teacher_assignment_clears_a_pending_persona_request(): void
    {
        [$class, $student, $teacher] = $this->classWithStudent([
            'pending_character_name' => 'Lobo Antigo',
            'pending_character_avatar' => 'lobo',
            'character_approval_status' => 'pending',
        ]);

        $this->actingAs($teacher)
            ->put(route('teacher.characters.update', [$class, $student]), $this->characterPayload())
            ->assertRedirectToRoute('teacher.students.show', [$class, $student]);

        $student->refresh();

        $this->assertSame('approved', $student->character_approval_status);
        $this->assertSame('Sombra Azul', $student->character_name);
        $this->assertNull($student->pending_character_name);
    }

    public function test_escapes_student_name_in_the_identity_form(): void
    {
        [$class, $student, $teacher] = $this->classWithStudent([
            'name' => "<script>alert('xss')</script>",
        ]);

        $html = $this->actingAs($teacher)
            ->get(route('teacher.students.show', [$class, $student]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringNotContainsString("<script>alert('xss')</script>", $html);
    }

    /**
     * @param  array<string, mixed>  $studentOverrides
     * @return array{0: SchoolClass, 1: User, 2: User}
     */
    private function classWithStudent(array $studentOverrides = []): array
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $class = $this->createClassForTeacher($teacher);
        $student = $this->enrollStudent($class, $studentOverrides['name'] ?? 'Aluno Teste', $studentOverrides);

        return [$class, $student, $teacher];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function enrollStudent(SchoolClass $class, string $name = 'Aluno Teste', array $overrides = []): User
    {
        unset($overrides['name']);

        $student = User::factory()->create(array_merge([
            'name' => $name,
            'role' => 'student',
            'character_class' => 'guerreiro',
            'must_change_password' => false,
        ], $overrides));

        $class->students()->attach($student->id, [
            'ranking_visible' => true,
            'xp' => 0,
            'behavior_score' => 100,
        ]);

        return $student;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function characterPayload(array $overrides = []): array
    {
        return array_merge([
            'character_class' => 'mago',
            'character_name' => 'Sombra Azul',
            'character_avatar' => 'fenix',
        ], $overrides);
    }
}
