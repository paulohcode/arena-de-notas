<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminClassAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_teacher_dashboard_and_any_class(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'must_change_password' => false,
        ]);
        $teacher = User::factory()->create([
            'role' => 'teacher',
            'must_change_password' => false,
        ]);
        $class = $this->createClassForTeacher($teacher, ['name' => 'Turma Admin']);

        $this->actingAs($admin)
            ->get(route('teacher.dashboard'))
            ->assertOk()
            ->assertSee('Turma Admin')
            ->assertSee($teacher->name);

        $this->actingAs($admin)
            ->get(route('teacher.classes.show', $class))
            ->assertOk()
            ->assertSee('Turma Admin');
    }

    public function test_admin_can_operate_class_after_teacher_leaves_area(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'must_change_password' => false,
        ]);
        $oldTeacher = User::factory()->create([
            'role' => 'teacher',
            'must_change_password' => false,
        ]);
        $newTeacher = User::factory()->create([
            'role' => 'teacher',
            'must_change_password' => false,
        ]);

        $class = $this->createClassForTeacher($oldTeacher, ['name' => 'Turma Órfã']);
        $area = $class->area;
        $newTeacher->areas()->syncWithoutDetaching([$area->id]);

        // Professor sai do reino: turma fica inacessível para ele.
        $oldTeacher->areas()->detach($area->id);

        $this->actingAs($oldTeacher)
            ->get(route('teacher.classes.show', $class))
            ->assertForbidden();

        $this->actingAs($admin)
            ->get(route('teacher.classes.show', $class))
            ->assertOk();

        $this->actingAs($admin)
            ->put(route('teacher.classes.update', $class), [
                'area_id' => $area->id,
                'teacher_id' => $newTeacher->id,
                'name' => 'Turma Órfã',
                'year' => $class->year,
                'score_mode' => $class->score_mode,
                'team_grade_weight' => $class->team_grade_weight,
                'behavior_grade_weight' => $class->behavior_grade_weight,
            ])
            ->assertRedirect(route('teacher.classes.show', $class));

        $this->assertDatabaseHas('classes', [
            'id' => $class->id,
            'teacher_id' => $newTeacher->id,
        ]);

        $this->actingAs($newTeacher)
            ->get(route('teacher.classes.show', $class))
            ->assertOk();
    }

    public function test_admin_cannot_remove_teacher_from_area_while_classes_remain(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'must_change_password' => false,
        ]);
        $teacher = User::factory()->create([
            'role' => 'teacher',
            'must_change_password' => false,
        ]);
        $class = $this->createClassForTeacher($teacher);
        $area = $class->area;

        $this->actingAs($admin)
            ->put(route('admin.teachers.update', $teacher), [
                'name' => $teacher->name,
                'email' => $teacher->email,
                'area_ids' => [],
            ])
            ->assertSessionHasErrors('area_ids');

        $this->assertTrue($teacher->fresh()->belongsToArea($area));
    }

    public function test_admin_can_create_class_for_any_teacher(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'must_change_password' => false,
        ]);
        $teacher = User::factory()->create([
            'role' => 'teacher',
            'must_change_password' => false,
        ]);
        $area = $this->createAreaForTeacher($teacher);

        $this->actingAs($admin)
            ->post(route('teacher.classes.store'), [
                'area_id' => $area->id,
                'teacher_id' => $teacher->id,
                'name' => 'Turma Criada pelo Admin',
                'year' => '2026',
                'score_mode' => 'up_from_zero',
                'team_grade_weight' => 1,
                'behavior_grade_weight' => 1,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('classes', [
            'name' => 'Turma Criada pelo Admin',
            'teacher_id' => $teacher->id,
            'area_id' => $area->id,
        ]);
    }
}
