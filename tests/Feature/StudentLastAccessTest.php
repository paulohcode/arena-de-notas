<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentLastAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_login_records_last_access(): void
    {
        $this->travelTo('2026-09-09 02:00:00');

        $student = User::factory()->create([
            'role' => 'student',
            'email' => 'aluno@example.com',
            'must_change_password' => false,
        ]);

        $this->post(route('login'), [
            'email' => 'aluno@example.com',
            'password' => 'password',
        ])->assertRedirect(route('student.dashboard'));

        $this->assertSame(
            '2026-09-09 02:00:00',
            $student->fresh()->last_accessed_at?->format('Y-m-d H:i:s')
        );
    }

    public function test_failed_login_does_not_record_last_access(): void
    {
        $student = User::factory()->create([
            'role' => 'student',
            'email' => 'aluno@example.com',
        ]);

        $this->from(route('login'))
            ->post(route('login'), [
                'email' => 'aluno@example.com',
                'password' => 'senha-errada',
            ])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        $this->assertNull($student->fresh()->last_accessed_at);
    }

    public function test_teacher_login_does_not_record_last_access(): void
    {
        $teacher = User::factory()->create([
            'role' => 'teacher',
            'email' => 'prof@example.com',
            'must_change_password' => false,
        ]);

        $this->post(route('login'), [
            'email' => 'prof@example.com',
            'password' => 'password',
        ])->assertRedirect(route('teacher.dashboard'));

        $this->assertNull($teacher->fresh()->last_accessed_at);
    }

    public function test_student_visit_records_last_access(): void
    {
        $this->travelTo('2026-09-09 02:00:00');

        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $class = $this->createClassForTeacher($teacher);
        $student = User::factory()->create([
            'role' => 'student',
            'must_change_password' => false,
            'character_class' => 'mago',
        ]);
        $class->students()->attach($student->id, ['ranking_visible' => false, 'xp' => 0]);

        $this->actingAs($student)
            ->get(route('student.dashboard'))
            ->assertOk();

        $this->assertSame(
            '2026-09-09 02:00:00',
            $student->fresh()->last_accessed_at?->format('Y-m-d H:i:s')
        );
    }

    public function test_student_visit_does_not_refresh_last_access_within_a_minute(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $class = $this->createClassForTeacher($teacher);
        $student = User::factory()->create([
            'role' => 'student',
            'must_change_password' => false,
            'character_class' => 'mago',
            'last_accessed_at' => '2026-09-09 02:00:00',
        ]);
        $class->students()->attach($student->id, ['ranking_visible' => false, 'xp' => 0]);

        $this->travelTo('2026-09-09 02:00:30');

        $this->actingAs($student)
            ->get(route('student.dashboard'))
            ->assertOk();

        $this->assertSame(
            '2026-09-09 02:00:00',
            $student->fresh()->last_accessed_at?->format('Y-m-d H:i:s')
        );
    }

    public function test_teacher_sees_last_access_in_brasilia_on_the_roster(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $class = $this->createClassForTeacher($teacher);
        $student = User::factory()->create([
            'role' => 'student',
            'name' => 'Ana Acesso',
            'last_accessed_at' => '2026-09-09 02:00:00',
        ]);
        $class->students()->attach($student->id, ['ranking_visible' => false, 'xp' => 0]);

        $this->actingAs($teacher)
            ->get(route('teacher.classes.show', ['schoolClass' => $class, 'tab' => 'alunos']))
            ->assertOk()
            ->assertSee('Último acesso')
            ->assertSee('08/09/2026 23:00')
            ->assertDontSee('09/09/2026 02:00');
    }

    public function test_teacher_sees_never_accessed_when_student_has_not_logged_in(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $class = $this->createClassForTeacher($teacher);
        $student = User::factory()->create(['role' => 'student', 'name' => 'Bruno Novo']);
        $class->students()->attach($student->id, ['ranking_visible' => false, 'xp' => 0]);

        $this->actingAs($teacher)
            ->get(route('teacher.classes.show', ['schoolClass' => $class, 'tab' => 'alunos']))
            ->assertOk()
            ->assertSee('Nunca acessou');
    }

    public function test_teacher_sees_last_access_in_brasilia_on_the_profile(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $class = $this->createClassForTeacher($teacher);
        $student = User::factory()->create([
            'role' => 'student',
            'last_accessed_at' => '2026-09-09 02:00:00',
        ]);
        $class->students()->attach($student->id, ['ranking_visible' => false, 'xp' => 0]);

        $this->actingAs($teacher)
            ->get(route('teacher.students.show', [$class, $student]))
            ->assertOk()
            ->assertSee('Último acesso')
            ->assertSee('08/09/2026 23:00');
    }

    public function test_admin_sees_last_access_on_the_profile(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $class = $this->createClassForTeacher($teacher);
        $student = User::factory()->create([
            'role' => 'student',
            'last_accessed_at' => '2026-09-09 02:00:00',
        ]);
        $class->students()->attach($student->id, ['ranking_visible' => false, 'xp' => 0]);
        $admin = User::factory()->create(['role' => 'admin', 'must_change_password' => false]);

        $this->actingAs($admin)
            ->get(route('teacher.students.show', [$class, $student]))
            ->assertOk()
            ->assertSee('Último acesso')
            ->assertSee('08/09/2026 23:00');
    }

    public function test_student_update_ignores_last_accessed_at_in_the_payload(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $class = $this->createClassForTeacher($teacher);
        $student = User::factory()->create([
            'role' => 'student',
            'name' => 'Aluno Original',
            'email' => 'aluno@example.com',
            'last_accessed_at' => '2026-09-01 12:00:00',
        ]);
        $class->students()->attach($student->id, ['ranking_visible' => false, 'xp' => 0]);

        $this->actingAs($teacher)
            ->put(route('teacher.students.update', [$class, $student]), [
                'name' => 'Aluno Original',
                'email' => 'aluno@example.com',
                'last_accessed_at' => '2026-09-09 02:00:00',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(
            '2026-09-01 12:00:00',
            $student->fresh()->last_accessed_at?->format('Y-m-d H:i:s')
        );
    }

    public function test_student_does_not_see_last_access_on_own_dashboard(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $class = $this->createClassForTeacher($teacher);
        $student = User::factory()->create([
            'role' => 'student',
            'must_change_password' => false,
            'character_class' => 'mago',
            'last_accessed_at' => '2026-09-09 02:00:00',
        ]);
        $class->students()->attach($student->id, ['ranking_visible' => false, 'xp' => 0]);

        $this->actingAs($student)
            ->get(route('student.dashboard'))
            ->assertOk()
            ->assertDontSee('Último acesso')
            ->assertDontSee('08/09/2026 23:00')
            ->assertDontSee('Nunca acessou');
    }

    public function test_public_ranking_does_not_show_last_access(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $class = $this->createClassForTeacher($teacher);
        $student = User::factory()->create([
            'role' => 'student',
            'name' => 'Carla Ranking',
            'last_accessed_at' => '2026-09-09 02:00:00',
        ]);
        $class->students()->attach($student->id, ['ranking_visible' => true, 'xp' => 0]);

        $this->get(route('ranking.show', $class))
            ->assertOk()
            ->assertSee('Carla Ranking')
            ->assertDontSee('Último acesso')
            ->assertDontSee('08/09/2026 23:00')
            ->assertDontSee('Nunca acessou');
    }
}
