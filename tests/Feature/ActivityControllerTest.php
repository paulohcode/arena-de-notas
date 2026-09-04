<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_request_redirects_to_login(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $class = $this->makeClassFor($teacher);

        $this->post(route('teacher.activities.store', $class), $this->validPayload())
            ->assertRedirectToRoute('login');
    }

    public function test_forbids_students_from_creating_activities(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $class = $this->makeClassFor($teacher);
        $student = User::factory()->create(['role' => 'student']);

        $this->actingAs($student)
            ->post(route('teacher.activities.store', $class), $this->validPayload())
            ->assertRedirect(route('student.dashboard'));
    }

    public function test_forbids_another_teacher_from_managing_the_class(): void
    {
        $owner = User::factory()->create(['role' => 'teacher']);
        $class = $this->makeClassFor($owner);
        $otherTeacher = User::factory()->create(['role' => 'teacher']);

        $this->actingAs($otherTeacher)
            ->post(route('teacher.activities.store', $class), $this->validPayload())
            ->assertForbidden();
    }

    public function test_valid_payload_creates_activity_and_stays_on_activities_tab(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $class = $this->makeClassFor($teacher);

        $this->actingAs($teacher)
            ->post(route('teacher.activities.store', $class), $this->validPayload([
                'name' => 'Prova 1',
                'type' => 'team',
                'max_score' => 80,
                'weight' => 3,
            ]))
            ->assertRedirectToRoute('teacher.classes.show', ['schoolClass' => $class, 'tab' => 'atividades'])
            ->assertSessionHas('success', 'Atividade criada.');

        $this->assertDatabaseHas('activities', [
            'class_id' => $class->id,
            'name' => 'Prova 1',
            'type' => 'team',
            'max_score' => 80,
            'weight' => 3,
        ]);
    }

    public function test_empty_payload_returns_required_field_messages(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $class = $this->makeClassFor($teacher);

        $this->actingAs($teacher)
            ->from(route('teacher.classes.show', ['schoolClass' => $class, 'tab' => 'atividades']))
            ->post(route('teacher.activities.store', $class), [])
            ->assertRedirect()
            ->assertSessionHasErrors([
                'name' => 'validation.required',
                'type' => 'validation.required',
                'max_score' => 'validation.required',
                'weight' => 'validation.required',
            ]);

        $this->assertDatabaseCount('activities', 0);
    }

    public function test_teacher_updates_activity_fields(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $class = $this->makeClassFor($teacher);
        $activity = $this->makeActivityFor($class);

        $this->actingAs($teacher)
            ->put(route('teacher.activities.update', [$class, $activity]), $this->validPayload([
                'name' => 'Desafio da Guilda',
                'type' => 'team',
                'max_score' => 90,
                'weight' => 4,
            ]))
            ->assertRedirectToRoute('teacher.classes.show', ['schoolClass' => $class, 'tab' => 'atividades'])
            ->assertSessionHas('success', 'Atividade atualizada.');

        $this->assertDatabaseHas('activities', [
            'id' => $activity->id,
            'name' => 'Desafio da Guilda',
            'type' => 'team',
            'max_score' => 90,
            'weight' => 4,
        ]);
    }

    public function test_activity_from_another_class_returns_404(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $class = $this->makeClassFor($teacher);
        $otherClass = $this->makeClassFor($teacher, 'Outra turma');
        $foreignActivity = $this->makeActivityFor($otherClass);

        $this->actingAs($teacher)
            ->put(route('teacher.activities.update', [$class, $foreignActivity]), $this->validPayload())
            ->assertNotFound();

        $this->assertDatabaseHas('activities', [
            'id' => $foreignActivity->id,
            'name' => 'Prova',
        ]);
    }

    public function test_teacher_deletes_activity(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $class = $this->makeClassFor($teacher);
        $activity = $this->makeActivityFor($class);

        $this->actingAs($teacher)
            ->delete(route('teacher.activities.destroy', [$class, $activity]))
            ->assertRedirectToRoute('teacher.classes.show', ['schoolClass' => $class, 'tab' => 'atividades'])
            ->assertSessionHas('success', 'Atividade removida.');

        $this->assertModelMissing($activity);
    }

    public function test_activities_tab_renders_form_and_table(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $class = $this->makeClassFor($teacher);
        $this->makeActivityFor($class, 'Trabalho em dupla');

        $this->actingAs($teacher)
            ->get(route('teacher.classes.show', ['schoolClass' => $class, 'tab' => 'atividades']))
            ->assertOk()
            ->assertSee('Nova atividade')
            ->assertSee('Cadastrar atividade')
            ->assertSee('Atividades cadastradas')
            ->assertSee('Trabalho em dupla')
            ->assertSee('Editar')
            ->assertSee('Excluir');
    }

    public function test_edit_link_fills_the_activity_form(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $class = $this->makeClassFor($teacher);
        $activity = $this->makeActivityFor($class, 'Prova bimestral');

        $this->actingAs($teacher)
            ->get(route('teacher.classes.show', [
                'schoolClass' => $class,
                'tab' => 'atividades',
                'activity' => $activity->id,
            ]))
            ->assertOk()
            ->assertSee('Editar atividade')
            ->assertSee('Salvar alterações')
            ->assertSee('Prova bimestral');
    }

    public function test_escapes_activity_name_in_the_class_table(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $class = $this->makeClassFor($teacher);
        $this->makeActivityFor($class, "<script>alert('xss')</script>");

        $html = $this->actingAs($teacher)
            ->get(route('teacher.classes.show', ['schoolClass' => $class, 'tab' => 'atividades']))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringNotContainsString("<script>alert('xss')</script>", $html);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Prova',
            'type' => 'individual',
            'max_score' => 100,
            'weight' => 1,
        ], $overrides);
    }

    private function makeClassFor(User $teacher, string $name = 'Turma Teste'): SchoolClass
    {
        return $this->createClassForTeacher($teacher, ['name' => $name]);
    }

    private function makeActivityFor(SchoolClass $class, string $name = 'Prova'): Activity
    {
        return Activity::query()->create([
            'class_id' => $class->id,
            'name' => $name,
            'type' => 'individual',
            'max_score' => 100,
            'weight' => 1,
        ]);
    }
}
