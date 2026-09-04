<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AreaAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_area_and_teacher(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'must_change_password' => false,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.areas.store'), [
                'name' => 'Informática',
                'slug' => 'informatica',
                'description' => 'Área de TI',
                'color' => '#b45309',
                'emblem' => 'gear',
                'map_x' => 30,
                'map_y' => 40,
                'is_active' => 1,
            ])
            ->assertRedirect(route('admin.areas.index'))
            ->assertSessionHas('success');

        $area = Area::query()->where('slug', 'informatica')->first();
        $this->assertNotNull($area);

        $this->actingAs($admin)
            ->post(route('admin.teachers.store'), [
                'name' => 'Prof Nova',
                'email' => 'prof.nova@escola.local',
                'password' => 'senha123',
                'password_confirmation' => 'senha123',
                'area_ids' => [$area->id],
            ])
            ->assertRedirect(route('admin.teachers.index'));

        $teacher = User::query()->where('email', 'prof.nova@escola.local')->first();
        $this->assertTrue($teacher?->isTeacher());
        $this->assertTrue($teacher->belongsToArea($area));
    }

    public function test_teacher_cannot_access_admin_panel(): void
    {
        $teacher = User::factory()->create([
            'role' => 'teacher',
            'must_change_password' => false,
        ]);

        $this->actingAs($teacher)
            ->get(route('admin.dashboard'))
            ->assertRedirect(route('teacher.dashboard'));
    }

    public function test_admin_login_from_admin_url_opens_dashboard(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'email' => 'admin.sistema@escola.local',
            'password' => 'Admin@123',
            'must_change_password' => false,
        ]);

        $this->get(route('admin.dashboard'))
            ->assertRedirect(route('login'));

        $this->post(route('login'), [
            'email' => $admin->email,
            'password' => 'Admin@123',
        ])->assertRedirect(route('admin.dashboard'));
    }

    public function test_teacher_login_from_admin_url_goes_to_teacher_panel(): void
    {
        $teacher = User::factory()->create([
            'role' => 'teacher',
            'must_change_password' => false,
        ]);

        $this->get(route('admin.dashboard'))
            ->assertRedirect(route('login'));

        $this->post(route('login'), [
            'email' => $teacher->email,
            'password' => 'password',
        ])->assertRedirect(route('teacher.dashboard'));
    }

    public function test_admin_login_redirects_to_admin_dashboard(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'email' => 'admin.sistema@escola.local',
            'password' => 'Admin@123',
            'must_change_password' => false,
        ]);

        $this->post(route('login'), [
            'email' => $admin->email,
            'password' => 'Admin@123',
        ])->assertRedirect(route('admin.dashboard'));
    }

    public function test_admin_can_update_area_map_position(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'must_change_password' => false,
        ]);
        $area = $this->createArea([
            'map_x' => 20,
            'map_y' => 20,
        ]);

        $this->actingAs($admin)
            ->patchJson(route('admin.areas.position', $area), [
                'map_x' => 72,
                'map_y' => 38,
            ])
            ->assertOk()
            ->assertJson([
                'map_x' => 72,
                'map_y' => 38,
            ]);

        $this->assertDatabaseHas('areas', [
            'id' => $area->id,
            'map_x' => 72,
            'map_y' => 38,
        ]);
    }

    public function test_teacher_cannot_update_area_map_position(): void
    {
        $teacher = User::factory()->create([
            'role' => 'teacher',
            'must_change_password' => false,
        ]);
        $area = $this->createArea([
            'map_x' => 20,
            'map_y' => 20,
        ]);

        $this->actingAs($teacher)
            ->patchJson(route('admin.areas.position', $area), [
                'map_x' => 80,
                'map_y' => 80,
            ])
            ->assertRedirect(route('teacher.dashboard'));

        $this->assertDatabaseHas('areas', [
            'id' => $area->id,
            'map_x' => 20,
            'map_y' => 20,
        ]);
    }

    public function test_home_enables_map_drag_only_for_admin(): void
    {
        $area = $this->createArea(['name' => 'Sistemas', 'slug' => 'sistemas-mapa']);
        $admin = User::factory()->create([
            'role' => 'admin',
            'must_change_password' => false,
        ]);

        $this->actingAs($admin)
            ->get(route('home'))
            ->assertOk()
            ->assertSee('Arraste os cards', false)
            ->assertSee(route('admin.areas.position', $area), false);

        $teacher = User::factory()->create([
            'role' => 'teacher',
            'must_change_password' => false,
        ]);

        $this->actingAs($teacher)
            ->get(route('home'))
            ->assertOk()
            ->assertDontSee('Arraste os cards')
            ->assertDontSee(route('admin.areas.position', $area), false);
    }
}
