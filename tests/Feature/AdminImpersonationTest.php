<?php

namespace Tests\Feature;

use App\Models\SchoolClass;
use App\Models\User;
use App\Services\ImpersonationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminImpersonationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_as_student_and_see_their_dashboard(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $class = $this->createClassForTeacher($teacher);
        $student = $this->enrollStudent($class, 'Ana Vista');
        $admin = User::factory()->create(['role' => 'admin', 'must_change_password' => false]);

        $this->actingAs($admin)
            ->post(route('admin.impersonate.start', [$class, $student]))
            ->assertRedirect(route('student.dashboard'));

        $this->assertAuthenticatedAs($student);
        $this->assertTrue(app(ImpersonationService::class)->isActive());

        $this->get(route('student.dashboard'))
            ->assertOk()
            ->assertSee('Ana Vista')
            ->assertSee('Visão de aluno · Ana Vista')
            ->assertSee('Alterações estão bloqueadas')
            ->assertSee('Visão de admin')
            ->assertDontSee('Nenhuma turma ainda');
    }

    public function test_admin_can_open_student_view_picker(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $class = $this->createClassForTeacher($teacher, ['name' => 'Turma Picker']);
        $this->enrollStudent($class, 'Carla Picker');
        $admin = User::factory()->create(['role' => 'admin', 'must_change_password' => false]);

        $this->actingAs($admin)
            ->get(route('admin.impersonate.index'))
            ->assertOk()
            ->assertSee('Visão de aluno')
            ->assertSee('Turma Picker')
            ->assertSee('Carla Picker')
            ->assertSee('Entrar na visão');
    }

    public function test_picker_search_filters_students(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $class = $this->createClassForTeacher($teacher);
        $this->enrollStudent($class, 'Ana Encontrada');
        $this->enrollStudent($class, 'Bruno Oculto');
        $admin = User::factory()->create(['role' => 'admin', 'must_change_password' => false]);

        $this->actingAs($admin)
            ->get(route('admin.impersonate.index', ['q' => 'Ana']))
            ->assertOk()
            ->assertSee('Ana Encontrada')
            ->assertDontSee('Bruno Oculto');
    }

    public function test_start_from_picker_returns_to_picker_on_stop(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $class = $this->createClassForTeacher($teacher);
        $student = $this->enrollStudent($class, 'Diana Return');
        $admin = User::factory()->create(['role' => 'admin', 'must_change_password' => false]);
        $returnUrl = route('admin.impersonate.index', ['q' => 'Diana']);

        $this->actingAs($admin)
            ->post(route('admin.impersonate.start', [$class, $student]), [
                'return_url' => $returnUrl,
            ])
            ->assertRedirect(route('student.dashboard'));

        $this->post(route('admin.impersonate.stop'))
            ->assertRedirect($returnUrl);

        $this->assertAuthenticatedAs($admin);
        $this->assertFalse(app(ImpersonationService::class)->isActive());
    }

    public function test_teacher_cannot_start_impersonation(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $class = $this->createClassForTeacher($teacher);
        $student = $this->enrollStudent($class);

        $this->actingAs($teacher)
            ->post(route('admin.impersonate.start', [$class, $student]))
            ->assertRedirect();

        $this->assertAuthenticatedAs($teacher);
        $this->assertFalse(app(ImpersonationService::class)->isActive());
    }

    public function test_student_cannot_start_impersonation(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $class = $this->createClassForTeacher($teacher);
        $student = $this->enrollStudent($class);

        $this->actingAs($student)
            ->post(route('admin.impersonate.start', [$class, $student]))
            ->assertRedirect();

        $this->assertFalse(app(ImpersonationService::class)->isActive());
    }

    public function test_admin_cannot_impersonate_teacher(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $class = $this->createClassForTeacher($teacher);
        $admin = User::factory()->create(['role' => 'admin', 'must_change_password' => false]);

        $this->actingAs($admin)
            ->post(route('admin.impersonate.start', [$class, $teacher]))
            ->assertNotFound();
    }

    public function test_admin_cannot_impersonate_student_from_another_class(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $class = $this->createClassForTeacher($teacher);
        $otherClass = $this->createClassForTeacher($teacher, ['area' => $class->area]);
        $foreignStudent = $this->enrollStudent($otherClass, 'Fora');
        $admin = User::factory()->create(['role' => 'admin', 'must_change_password' => false]);

        $this->actingAs($admin)
            ->post(route('admin.impersonate.start', [$class, $foreignStudent]))
            ->assertNotFound();
    }

    public function test_impersonation_blocks_shop_purchase_but_allows_class_switch(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $classA = $this->createClassForTeacher($teacher, ['name' => 'Turma A']);
        $classB = $this->createClassForTeacher($teacher, ['area' => $classA->area, 'name' => 'Turma B']);
        $student = $this->enrollStudent($classA, 'Ana Dual');
        $classB->students()->attach($student->id, [
            'ranking_visible' => true,
            'xp' => 0,
            'behavior_score' => 100,
        ]);
        $admin = User::factory()->create(['role' => 'admin', 'must_change_password' => false]);

        $enrollment = $student->enrollmentIn($classA);
        $enrollment->update(['relics' => 100]);

        $this->actingAs($admin)
            ->post(route('admin.impersonate.start', [$classA, $student]))
            ->assertRedirect(route('student.dashboard'));

        $this->post(route('student.shop.purchase'), ['item' => 'frame_bronze'])
            ->assertRedirect()
            ->assertSessionHas('message', 'Visão de aluno é só para análise. Alterações estão bloqueadas.');

        $this->assertSame(100, (int) $enrollment->fresh()->relics);
        $this->assertSame(0, $enrollment->fresh()->cosmetics()->count());

        $this->from(route('student.dashboard'))
            ->post(route('student.class.switch'), ['class_id' => $classB->id])
            ->assertRedirect(route('student.dashboard'));

        $this->assertSame($classB->id, (int) session('current_class_id'));
    }

    public function test_stop_restores_admin_and_returns_to_staff_sheet(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $class = $this->createClassForTeacher($teacher);
        $student = $this->enrollStudent($class, 'Bruno Stop');
        $admin = User::factory()->create(['role' => 'admin', 'must_change_password' => false]);
        $sheetUrl = route('teacher.students.show', [$class, $student]);

        $this->actingAs($admin)
            ->post(route('admin.impersonate.start', [$class, $student]), [
                'return_url' => $sheetUrl,
            ])
            ->assertRedirect(route('student.dashboard'));

        $this->post(route('admin.impersonate.stop'))
            ->assertRedirect($sheetUrl);

        $this->assertAuthenticatedAs($admin);
        $this->assertFalse(app(ImpersonationService::class)->isActive());
    }

    public function test_stop_without_return_url_goes_to_student_view_picker(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $class = $this->createClassForTeacher($teacher);
        $student = $this->enrollStudent($class, 'Eva Default');
        $admin = User::factory()->create(['role' => 'admin', 'must_change_password' => false]);

        $this->actingAs($admin)
            ->post(route('admin.impersonate.start', [$class, $student]))
            ->assertRedirect(route('student.dashboard'));

        $this->post(route('admin.impersonate.stop'))
            ->assertRedirect(route('admin.impersonate.index'));

        $this->assertAuthenticatedAs($admin);
    }

    public function test_impersonation_does_not_update_student_last_accessed_at(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $class = $this->createClassForTeacher($teacher);
        $student = $this->enrollStudent($class, 'Ana Acesso');
        $student->forceFill(['last_accessed_at' => now()->subDay()])->save();
        $before = $student->fresh()->last_accessed_at?->toIso8601String();
        $admin = User::factory()->create(['role' => 'admin', 'must_change_password' => false]);

        $this->actingAs($admin)
            ->post(route('admin.impersonate.start', [$class, $student]))
            ->assertRedirect(route('student.dashboard'));

        $this->get(route('student.dashboard'))->assertOk();

        $this->assertSame($before, $student->fresh()->last_accessed_at?->toIso8601String());
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
