<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AreaIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_shows_kingdom_map_with_areas(): void
    {
        $area = $this->createArea([
            'name' => 'Mecânica',
            'slug' => 'mecanica',
            'is_active' => true,
        ]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Escolha seu reino')
            ->assertSee('Mecânica')
            ->assertSee('images/kingdom-map.png', false);
    }

    public function test_area_page_lists_only_its_classes(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $areaA = $this->createAreaForTeacher($teacher, ['name' => 'Reino A', 'slug' => 'reino-a']);
        $areaB = $this->createAreaForTeacher($teacher, ['name' => 'Reino B', 'slug' => 'reino-b']);

        $this->createClassForTeacher($teacher, ['area' => $areaA, 'name' => 'Turma do Reino A']);
        $this->createClassForTeacher($teacher, ['area' => $areaB, 'name' => 'Turma do Reino B']);

        $this->get(route('areas.show', $areaA))
            ->assertOk()
            ->assertSee('Turma do Reino A')
            ->assertDontSee('Turma do Reino B');
    }

    public function test_teacher_cannot_create_class_outside_assigned_areas(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $ownArea = $this->createAreaForTeacher($teacher, ['slug' => 'meu-reino']);
        $foreignArea = $this->createArea(['slug' => 'outro-reino']);

        $this->actingAs($teacher)
            ->post(route('teacher.classes.store'), [
                'area_id' => $foreignArea->id,
                'name' => 'Turma Inválida',
                'year' => '2026',
                'score_mode' => 'up_from_zero',
                'team_grade_weight' => 1,
                'behavior_grade_weight' => 1,
            ])
            ->assertSessionHasErrors('area_id');

        $this->actingAs($teacher)
            ->post(route('teacher.classes.store'), [
                'area_id' => $ownArea->id,
                'name' => 'Turma Válida',
                'year' => '2026',
                'score_mode' => 'up_from_zero',
                'team_grade_weight' => 1,
                'behavior_grade_weight' => 1,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('classes', [
            'name' => 'Turma Válida',
            'area_id' => $ownArea->id,
            'teacher_id' => $teacher->id,
        ]);
    }

    public function test_teacher_loses_access_when_removed_from_area(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $class = $this->createClassForTeacher($teacher, ['name' => 'Turma Isolada']);

        $this->actingAs($teacher)
            ->get(route('teacher.classes.show', $class))
            ->assertOk();

        $teacher->areas()->detach($class->area_id);

        $this->actingAs($teacher)
            ->get(route('teacher.classes.show', $class))
            ->assertForbidden();
    }

    public function test_inactive_area_is_hidden_from_public_map(): void
    {
        $this->createArea([
            'name' => 'Reino Oculto',
            'slug' => 'reino-oculto',
            'is_active' => false,
        ]);

        $this->get(route('home'))
            ->assertOk()
            ->assertDontSee('Reino Oculto');
    }
}
