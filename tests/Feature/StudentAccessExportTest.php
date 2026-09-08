<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\ClassAccessPdfService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentAccessExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_roster_shows_the_pdf_export_link(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $class = $this->createClassForTeacher($teacher);

        $this->actingAs($teacher)
            ->get(route('teacher.classes.show', ['schoolClass' => $class, 'tab' => 'alunos']))
            ->assertOk()
            ->assertSee('Exportar PDF')
            ->assertSee(route('teacher.students.export', $class), false);
    }

    public function test_teacher_downloads_a_pdf_with_student_names_and_access_emails(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $class = $this->createClassForTeacher($teacher, ['name' => '3A Matematica']);
        $ana = User::factory()->create([
            'role' => 'student',
            'name' => 'José Araújo',
            'email' => 'jose.araujo@example.com',
            'must_change_password' => true,
        ]);
        $bruno = User::factory()->create([
            'role' => 'student',
            'name' => 'Bruno Lima',
            'email' => 'bruno.lima@example.com',
            'must_change_password' => false,
        ]);
        $class->students()->attach($ana->id, ['ranking_visible' => false, 'xp' => 0]);
        $class->students()->attach($bruno->id, ['ranking_visible' => false, 'xp' => 0]);

        $pdf = $this->actingAs($teacher)
            ->get(route('teacher.students.export', $class))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Content-Disposition', 'attachment; filename="acessos-3a-matematica.pdf"')
            ->getContent();

        $this->assertStringStartsWith('%PDF', $pdf);
        $this->assertStringContainsString((string) iconv('UTF-8', 'Windows-1252', 'José Araújo'), $pdf);
        $this->assertStringContainsString('jose.araujo@example.com', $pdf);
        $this->assertStringContainsString('Bruno Lima', $pdf);
        $this->assertStringContainsString('bruno.lima@example.com', $pdf);
        $this->assertStringContainsString(ClassAccessPdfService::INITIAL_PASSWORD, $pdf);
        $this->assertStringContainsString('Alterada pelo aluno', $pdf);
        $this->assertLessThan(
            strpos($pdf, 'jose.araujo@example.com'),
            strpos($pdf, 'bruno.lima@example.com'),
        );
    }

    public function test_pdf_omits_students_from_another_class(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $class = $this->createClassForTeacher($teacher);
        $otherClass = $this->createClassForTeacher($teacher, ['area' => $class->area]);
        $enrolled = User::factory()->create([
            'role' => 'student',
            'name' => 'Aluno Da Turma',
            'email' => 'da.turma@example.com',
        ]);
        $foreign = User::factory()->create([
            'role' => 'student',
            'name' => 'Aluno De Fora',
            'email' => 'de.fora@example.com',
        ]);
        $class->students()->attach($enrolled->id, ['ranking_visible' => false, 'xp' => 0]);
        $otherClass->students()->attach($foreign->id, ['ranking_visible' => false, 'xp' => 0]);

        $pdf = $this->actingAs($teacher)
            ->get(route('teacher.students.export', $class))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('da.turma@example.com', $pdf);
        $this->assertStringNotContainsString('de.fora@example.com', $pdf);
        $this->assertStringNotContainsString('Aluno De Fora', $pdf);
    }

    public function test_empty_class_still_downloads_a_pdf(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $class = $this->createClassForTeacher($teacher, ['name' => 'Turma Vazia']);

        $pdf = $this->actingAs($teacher)
            ->get(route('teacher.students.export', $class))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->getContent();

        $this->assertStringStartsWith('%PDF', $pdf);
        $this->assertStringContainsString('Nenhum aluno cadastrado', $pdf);
    }

    public function test_escapes_parentheses_in_student_names_inside_the_pdf(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $class = $this->createClassForTeacher($teacher);
        $student = User::factory()->create([
            'role' => 'student',
            'name' => 'Evil) Tj /F2 99 Tf (',
            'email' => 'evil@example.com',
        ]);
        $class->students()->attach($student->id, ['ranking_visible' => false, 'xp' => 0]);

        $pdf = $this->actingAs($teacher)
            ->get(route('teacher.students.export', $class))
            ->assertOk()
            ->getContent();

        $this->assertStringStartsWith('%PDF', $pdf);
        $this->assertStringContainsString('evil@example.com', $pdf);
        $this->assertStringContainsString('\\) Tj /F2 99 Tf \\(', $pdf);
        $this->assertStringNotContainsString(') Tj /F2 99 Tf (', $pdf);
    }

    public function test_admin_can_export_the_class_access_pdf(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $class = $this->createClassForTeacher($teacher);
        $student = User::factory()->create([
            'role' => 'student',
            'email' => 'admin.export@example.com',
        ]);
        $class->students()->attach($student->id, ['ranking_visible' => false, 'xp' => 0]);
        $admin = User::factory()->create(['role' => 'admin', 'must_change_password' => false]);

        $this->actingAs($admin)
            ->get(route('teacher.students.export', $class))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertSee('admin.export@example.com', false);
    }

    public function test_unauthenticated_export_redirects_to_login(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $class = $this->createClassForTeacher($teacher);

        $this->get(route('teacher.students.export', $class))
            ->assertRedirectToRoute('login');
    }

    public function test_student_is_redirected_away_from_export(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $class = $this->createClassForTeacher($teacher);
        $student = User::factory()->create([
            'role' => 'student',
            'must_change_password' => false,
        ]);
        $class->students()->attach($student->id, ['ranking_visible' => false, 'xp' => 0]);

        $this->actingAs($student)
            ->get(route('teacher.students.export', $class))
            ->assertRedirect(route('student.dashboard'));
    }

    public function test_forbids_another_teacher_from_exporting_the_roster(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $other = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $class = $this->createClassForTeacher($teacher);

        $this->actingAs($other)
            ->get(route('teacher.students.export', $class))
            ->assertForbidden();
    }
}
