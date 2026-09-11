<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CharacterClassSelectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_without_class_is_redirected_to_selection(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $class = $this->createClassForTeacher($teacher, ['name' => 'Turma A']);

        $student = User::factory()->create([
            'role' => 'student',
            'character_class' => null,
            'must_change_password' => false,
        ]);
        $class->students()->attach($student->id, ['ranking_visible' => true, 'xp' => 0]);

        $this->actingAs($student)
            ->get(route('student.dashboard'))
            ->assertRedirect(route('student.character.edit'));
    }

    public function test_student_can_choose_character_class(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $class = $this->createClassForTeacher($teacher, ['name' => 'Turma A']);

        $student = User::factory()->create([
            'role' => 'student',
            'character_class' => null,
            'must_change_password' => false,
        ]);
        $class->students()->attach($student->id, ['ranking_visible' => true, 'xp' => 0]);

        $this->actingAs($student)
            ->post(route('student.character.update'), [
                'character_class' => 'mago',
                'character_name' => 'Sombra Azul',
                'character_avatar' => 'lobo',
            ])
            ->assertRedirect(route('student.dashboard'));

        $student->refresh();

        $this->assertSame('mago', $student->character_class);
        $this->assertSame('pending', $student->character_approval_status);
        $this->assertSame('Sombra Azul', $student->pending_character_name);
        $this->assertNull($student->character_name);

        $this->actingAs($student)
            ->get(route('student.dashboard'))
            ->assertOk()
            ->assertSee('Mago')
            ->assertSee('class-aura--mago', false)
            ->assertSee('aguardando o professor')
            ->assertDontSeeText('Sombra Azul');
    }

    public function test_class_picker_previews_each_class_aura(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $class = $this->createClassForTeacher($teacher, ['name' => 'Turma A']);

        $student = User::factory()->create([
            'role' => 'student',
            'character_class' => null,
            'must_change_password' => false,
        ]);
        $class->students()->attach($student->id, ['ranking_visible' => true, 'xp' => 0]);

        $this->actingAs($student)
            ->get(route('student.character.edit'))
            ->assertOk()
            ->assertSee('class-aura--mago', false)
            ->assertSee('class-aura--guerreiro', false)
            ->assertSee('class-fx--mago', false)
            ->assertSee('class-fx--necromante', false)
            ->assertSee('class-aura--anao', false)
            ->assertSee('Anão')
            ->assertSee('class-aura--frankenstein', false)
            ->assertSee('Frankenstein')
            ->assertSee('Linha de frente')
            ->assertSee('A classe muda o')
            ->assertSee('não substitui a prova')
            ->assertSee('Menos vida, feitiços mais fortes.')
            ->assertSee('Ler as regras da arena')
            ->assertSee('name="character_class"', false)
            ->assertSee('só o professor ou o admin pode trocar');
    }

    public function test_student_can_choose_dwarf_class(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $class = $this->createClassForTeacher($teacher, ['name' => 'Turma A']);

        $student = User::factory()->create([
            'role' => 'student',
            'character_class' => null,
            'must_change_password' => false,
        ]);
        $class->students()->attach($student->id, ['ranking_visible' => true, 'xp' => 0]);

        $this->actingAs($student)
            ->post(route('student.character.update'), [
                'character_class' => 'anao',
                'character_name' => 'Martelo de Rocha',
                'character_avatar' => 'elmo',
            ])
            ->assertRedirect(route('student.dashboard'));

        $this->assertSame('anao', $student->fresh()->character_class);

        $this->actingAs($student)
            ->get(route('student.dashboard'))
            ->assertOk()
            ->assertSee('Anão')
            ->assertSee('class-aura--anao', false);
    }

    public function test_student_can_choose_frankenstein_class(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $class = $this->createClassForTeacher($teacher, ['name' => 'Turma A']);

        $student = User::factory()->create([
            'role' => 'student',
            'character_class' => null,
            'must_change_password' => false,
        ]);
        $class->students()->attach($student->id, ['ranking_visible' => true, 'xp' => 0]);

        $this->actingAs($student)
            ->post(route('student.character.update'), [
                'character_class' => 'frankenstein',
                'character_name' => 'Raio Verde',
                'character_avatar' => 'runa',
            ])
            ->assertRedirect(route('student.dashboard'));

        $this->assertSame('frankenstein', $student->fresh()->character_class);

        $this->actingAs($student)
            ->get(route('student.dashboard'))
            ->assertOk()
            ->assertSee('Frankenstein')
            ->assertSee('class-aura--frankenstein', false);
    }

    public function test_first_choice_requires_a_character_class(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $class = $this->createClassForTeacher($teacher, ['name' => 'Turma A']);

        $student = User::factory()->create([
            'role' => 'student',
            'character_class' => null,
            'must_change_password' => false,
        ]);
        $class->students()->attach($student->id, ['ranking_visible' => true, 'xp' => 0]);

        $this->actingAs($student)
            ->from(route('student.character.edit'))
            ->post(route('student.character.update'), [
                'character_name' => 'Sombra Azul',
                'character_avatar' => 'lobo',
            ])
            ->assertRedirect(route('student.character.edit'))
            ->assertSessionHasErrors('character_class');

        $this->assertNull($student->fresh()->character_class);
    }

    public function test_student_cannot_change_character_class_after_choosing(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $class = $this->createClassForTeacher($teacher, ['name' => 'Turma A']);

        $student = User::factory()->create([
            'role' => 'student',
            'character_class' => 'guerreiro',
            'must_change_password' => false,
        ]);
        $class->students()->attach($student->id, ['ranking_visible' => true, 'xp' => 0]);

        $this->actingAs($student)
            ->post(route('student.character.update'), [
                'character_class' => 'mago',
                'character_name' => 'Sombra Azul',
                'character_avatar' => 'lobo',
            ])
            ->assertRedirect(route('student.dashboard'));

        $student->refresh();

        $this->assertSame('guerreiro', $student->character_class);
        $this->assertSame('pending', $student->character_approval_status);
        $this->assertSame('Sombra Azul', $student->pending_character_name);
    }

    public function test_character_edit_locks_class_picker_after_choice(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $class = $this->createClassForTeacher($teacher, ['name' => 'Turma A']);

        $student = User::factory()->create([
            'role' => 'student',
            'character_class' => 'mago',
            'must_change_password' => false,
        ]);
        $class->students()->attach($student->id, ['ranking_visible' => true, 'xp' => 0]);

        $this->actingAs($student)
            ->get(route('student.character.edit'))
            ->assertOk()
            ->assertSee('Mago')
            ->assertSee('A classe já foi escolhida')
            ->assertSee('Só o professor ou o admin pode trocar')
            ->assertDontSee('name="character_class"', false)
            ->assertDontSee('Ler as regras da arena');
    }
}
