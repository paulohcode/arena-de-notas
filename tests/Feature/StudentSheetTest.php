<?php

namespace Tests\Feature;

use App\Models\Badge;
use App\Models\SchoolClass;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentSheetTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_request_redirects_to_login(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $class = $this->createClassForTeacher($teacher);
        $student = $this->enrollStudent($class);

        $this->get(route('teacher.students.show', [$class, $student]))
            ->assertRedirectToRoute('login');
    }

    public function test_student_is_redirected_away_from_teacher_sheet(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $class = $this->createClassForTeacher($teacher);
        $student = $this->enrollStudent($class);

        $this->actingAs($student)
            ->get(route('teacher.students.show', [$class, $student]))
            ->assertRedirect(route('student.dashboard'));
    }

    public function test_forbids_another_teacher_from_viewing_the_sheet(): void
    {
        $owner = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $class = $this->createClassForTeacher($owner);
        $student = $this->enrollStudent($class);
        $other = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);

        $this->actingAs($other)
            ->get(route('teacher.students.show', [$class, $student]))
            ->assertForbidden();
    }

    public function test_student_from_another_class_returns_404(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $class = $this->createClassForTeacher($teacher);
        $otherClass = $this->createClassForTeacher($teacher, ['area' => $class->area]);
        $foreignStudent = $this->enrollStudent($otherClass, 'Aluno de fora');

        $this->actingAs($teacher)
            ->get(route('teacher.students.show', [$class, $foreignStudent]))
            ->assertNotFound();
    }

    public function test_teacher_sees_student_xp_badges_and_grade_on_the_sheet(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $class = $this->createClassForTeacher($teacher);
        $student = $this->enrollStudent($class, 'Ana Maga', 150, 'mago');

        $badge = Badge::query()->create([
            'slug' => 'veterano',
            'name' => 'Veterano',
            'description' => 'Concluiu 5 atividades.',
            'icon' => '📜',
        ]);
        $student->badges()->attach($badge->id, ['class_id' => $class->id]);

        $this->actingAs($teacher)
            ->get(route('teacher.students.show', [$class, $student]))
            ->assertOk()
            ->assertSee('Ficha do aluno')
            ->assertSee('Ana Maga')
            ->assertSee('Mago')
            ->assertSee('class-aura--mago', false)
            ->assertSee('150 XP')
            ->assertSee('Aprendiz')
            ->assertSee('Veterano')
            ->assertSee('Composição da nota')
            ->assertSee('Medalhas')
            ->assertDontSee('Trocar classe')
            ->assertDontSee('Mostrar meu nome e nota no ranking');
    }

    public function test_class_roster_links_to_the_student_sheet(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $class = $this->createClassForTeacher($teacher);
        $student = $this->enrollStudent($class, 'Bruno');

        $this->actingAs($teacher)
            ->get(route('teacher.classes.show', ['schoolClass' => $class, 'tab' => 'alunos']))
            ->assertOk()
            ->assertSee(route('teacher.students.show', [$class, $student]), false)
            ->assertSee('Ficha');
    }

    public function test_class_ranking_links_teacher_to_the_student_sheet(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $class = $this->createClassForTeacher($teacher);
        $student = $this->enrollStudent($class, 'Bruno Ranking');
        $profileUrl = route('teacher.students.show', [$class, $student]);

        $this->actingAs($teacher)
            ->get(route('ranking.show', $class))
            ->assertOk()
            ->assertSee($profileUrl, false)
            ->assertSee('Ver ficha de Bruno Ranking', false);
    }

    public function test_guest_ranking_does_not_link_to_the_student_sheet(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $class = $this->createClassForTeacher($teacher);
        $student = $this->enrollStudent($class, 'Bruno Publico');

        $this->get(route('ranking.show', $class))
            ->assertOk()
            ->assertSee('Bruno Publico')
            ->assertSee('class-aura--guerreiro', false)
            ->assertDontSee(route('teacher.students.show', [$class, $student]), false);
    }

    public function test_other_teacher_ranking_does_not_link_to_the_student_sheet(): void
    {
        $owner = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $class = $this->createClassForTeacher($owner);
        $student = $this->enrollStudent($class, 'Bruno Isolado');
        $outsider = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $this->createAreaForTeacher($outsider, ['slug' => 'reino-ficha-ranking']);

        $this->actingAs($outsider)
            ->get(route('ranking.show', $class))
            ->assertOk()
            ->assertDontSee(route('teacher.students.show', [$class, $student]), false);
    }

    public function test_student_ranking_does_not_link_to_the_teacher_sheet(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $class = $this->createClassForTeacher($teacher);
        $student = $this->enrollStudent($class, 'Bruno Aluno');

        $this->actingAs($student)
            ->get(route('ranking.show', $class))
            ->assertOk()
            ->assertDontSee(route('teacher.students.show', [$class, $student]), false);
    }

    public function test_admin_ranking_links_to_the_student_sheet(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $class = $this->createClassForTeacher($teacher);
        $student = $this->enrollStudent($class, 'Bruno Admin');
        $admin = User::factory()->create(['role' => 'admin', 'must_change_password' => false]);

        $this->actingAs($admin)
            ->get(route('ranking.show', $class))
            ->assertOk()
            ->assertSee(route('teacher.students.show', [$class, $student]), false);
    }

    public function test_guild_page_links_teacher_to_the_student_sheet(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $class = $this->createClassForTeacher($teacher);
        $student = $this->enrollStudent($class, 'Bruno Guilda');
        $team = Team::query()->create([
            'class_id' => $class->id,
            'name' => 'Guilda Link',
            'emblem' => 'owl',
        ]);
        $team->members()->attach($student->id);

        $this->actingAs($teacher)
            ->get(route('ranking.guild', [$class, $team]))
            ->assertOk()
            ->assertSee(route('teacher.students.show', [$class, $student]), false);
    }

    public function test_escapes_student_name_on_the_teacher_sheet(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $class = $this->createClassForTeacher($teacher);
        $student = $this->enrollStudent($class, "<script>alert('xss')</script>");

        $html = $this->actingAs($teacher)
            ->get(route('teacher.students.show', [$class, $student]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringNotContainsString("<script>alert('xss')</script>", $html);
    }

    private function enrollStudent(
        SchoolClass $class,
        string $name = 'Aluno Teste',
        int $xp = 0,
        ?string $characterClass = 'guerreiro',
    ): User {
        $student = User::factory()->create([
            'name' => $name,
            'role' => 'student',
            'character_class' => $characterClass,
            'must_change_password' => false,
        ]);

        $class->students()->attach($student->id, [
            'ranking_visible' => true,
            'xp' => $xp,
            'behavior_score' => 100,
        ]);

        return $student;
    }
}
