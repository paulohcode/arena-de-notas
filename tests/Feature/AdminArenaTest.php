<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminArenaTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_arena_hub_redirects_to_login(): void
    {
        $this->get(route('admin.arena'))
            ->assertRedirectToRoute('login');
    }

    public function test_teacher_cannot_open_arena_hub(): void
    {
        $teacher = User::factory()->create([
            'role' => 'teacher',
            'must_change_password' => false,
        ]);

        $this->actingAs($teacher)
            ->get(route('admin.arena'))
            ->assertRedirect(route('teacher.dashboard'));
    }

    public function test_student_cannot_open_arena_hub(): void
    {
        $student = User::factory()->create([
            'role' => 'student',
            'must_change_password' => false,
        ]);

        $this->actingAs($student)
            ->get(route('admin.arena'))
            ->assertRedirect(route('student.dashboard'));
    }

    public function test_admin_nav_links_to_arena_hub(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'must_change_password' => false]);

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee(route('admin.arena'), false);
    }

    public function test_arena_hub_with_one_area_opens_that_arena(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'must_change_password' => false]);
        $area = Area::query()->sole();

        $this->actingAs($admin)
            ->get(route('admin.arena'))
            ->assertRedirect(route('admin.areas.arena', $area));
    }

    public function test_arena_hub_with_several_areas_lists_them(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'must_change_password' => false]);
        $this->createArea(['name' => 'Reino Norte']);
        $this->createArea(['name' => 'Reino Sul']);

        $this->actingAs($admin)
            ->get(route('admin.arena'))
            ->assertOk()
            ->assertSee('Reino Norte')
            ->assertSee('Reino Sul')
            ->assertSee('Abrir arena');
    }

    public function test_admin_arena_page_separates_class_and_realm_tabs(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'must_change_password' => false]);
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $area = $this->createAreaForTeacher($teacher);
        $this->createClassForTeacher($teacher, [
            'area' => $area,
            'name' => 'Turma Norte',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.areas.arena', $area))
            ->assertOk()
            ->assertSee('Arena da turma')
            ->assertSee('Arena das turmas')
            ->assertSee('Turmas do reino')
            ->assertSee('Turma Norte')
            ->assertDontSee('Configurações da arena entre turmas');

        $this->actingAs($admin)
            ->get(route('admin.areas.arena', ['area' => $area, 'tab' => 'turmas']))
            ->assertOk()
            ->assertSee('Configurações da arena entre turmas')
            ->assertDontSee('Turmas do reino');
    }
}
