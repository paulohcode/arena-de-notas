<?php

namespace Tests\Feature;

use App\Models\Badge;
use App\Models\LedgerEntry;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentRosterTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_student_form_includes_csrf_token(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $class = $this->createClassForTeacher($teacher);

        $html = $this->actingAs($teacher)
            ->get(route('teacher.classes.show', ['schoolClass' => $class, 'tab' => 'alunos']))
            ->assertOk()
            ->getContent();

        $action = preg_quote(e(route('teacher.students.store', $class)), '/');
        $this->assertSame(1, preg_match(
            '/<form[^>]*action="'.$action.'"[^>]*>([\s\S]*?)<\/form>/',
            $html,
            $matches
        ));
        $this->assertStringContainsString('name="_token"', $matches[1]);
    }

    public function test_teacher_can_register_a_student(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $class = $this->createClassForTeacher($teacher);

        $this->actingAs($teacher)
            ->from(route('teacher.classes.show', ['schoolClass' => $class, 'tab' => 'alunos']))
            ->post(route('teacher.students.store', $class), [
                'name' => 'Maria Silva',
                'email' => 'maria@example.com',
            ])
            ->assertRedirect(route('teacher.classes.show', ['schoolClass' => $class, 'tab' => 'alunos']))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('users', [
            'name' => 'Maria Silva',
            'email' => 'maria@example.com',
            'role' => 'student',
            'must_change_password' => true,
        ]);

        $student = User::query()->where('email', 'maria@example.com')->firstOrFail();

        $this->assertDatabaseHas('enrollments', [
            'class_id' => $class->id,
            'student_id' => $student->id,
            'ranking_visible' => false,
            'xp' => 0,
        ]);
    }

    public function test_roster_includes_rename_form(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $class = $this->createClassForTeacher($teacher);
        $student = User::factory()->create(['role' => 'student', 'name' => 'João da Silva']);
        $class->students()->attach($student->id, ['ranking_visible' => false, 'xp' => 0]);

        $html = $this->actingAs($teacher)
            ->get(route('teacher.classes.show', ['schoolClass' => $class, 'tab' => 'alunos']))
            ->assertOk()
            ->assertSee('Editar cadastro')
            ->getContent();

        $this->assertStringContainsString(
            e(route('teacher.students.update', [$class, $student])),
            $html
        );
        $this->assertStringContainsString('name="_token"', $html);
        $this->assertStringContainsString('name="_method" value="PUT"', $html);
        $this->assertStringContainsString('name="email"', $html);
    }

    public function test_roster_groups_students_by_guild_in_alphabetical_order(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $class = $this->createClassForTeacher($teacher);

        $carlos = User::factory()->create(['role' => 'student', 'name' => 'Carlos Alfa']);
        $bruno = User::factory()->create(['role' => 'student', 'name' => 'Bruno Alfa']);
        $zoe = User::factory()->create(['role' => 'student', 'name' => 'Zoe Beta']);
        $ana = User::factory()->create(['role' => 'student', 'name' => 'Ana Beta']);
        $diego = User::factory()->create(['role' => 'student', 'name' => 'Diego Livre']);

        foreach ([$carlos, $bruno, $zoe, $ana, $diego] as $student) {
            $class->students()->attach($student->id, ['ranking_visible' => false, 'xp' => 0]);
        }

        $lobos = Team::query()->create([
            'class_id' => $class->id,
            'name' => 'Lobos',
            'emblem' => 'wolf',
        ]);
        $dragoes = Team::query()->create([
            'class_id' => $class->id,
            'name' => 'Dragões',
            'emblem' => 'dragon',
        ]);
        Team::query()->create([
            'class_id' => $class->id,
            'name' => 'Vazia',
            'emblem' => 'owl',
        ]);

        $dragoes->members()->attach([$carlos->id, $bruno->id]);
        $lobos->members()->attach([$zoe->id, $ana->id]);

        $html = $this->actingAs($teacher)
            ->get(route('teacher.classes.show', ['schoolClass' => $class, 'tab' => 'alunos']))
            ->assertOk()
            ->getContent();

        $roster = $this->studentRosterHtml($html);

        $this->assertDoesNotMatchRegularExpression('/Vazia/', $roster);
        $this->assertMatchesRegularExpression(
            '/Dragões[\s\S]*Bruno Alfa[\s\S]*Carlos Alfa[\s\S]*Lobos[\s\S]*Ana Beta[\s\S]*Zoe Beta[\s\S]*Sem guilda[\s\S]*Diego Livre/',
            $roster
        );
    }

    public function test_admin_sees_roster_grouped_by_guild(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $class = $this->createClassForTeacher($teacher);
        $student = User::factory()->create(['role' => 'student', 'name' => 'Ana Admin']);
        $class->students()->attach($student->id, ['ranking_visible' => false, 'xp' => 0]);
        $team = Team::query()->create([
            'class_id' => $class->id,
            'name' => 'Águias',
            'emblem' => 'owl',
        ]);
        $team->members()->attach($student->id);
        $admin = User::factory()->create(['role' => 'admin', 'must_change_password' => false]);

        $roster = $this->studentRosterHtml(
            $this->actingAs($admin)
                ->get(route('teacher.classes.show', ['schoolClass' => $class, 'tab' => 'alunos']))
                ->assertOk()
                ->getContent()
        );

        $this->assertMatchesRegularExpression('/Águias[\s\S]*Ana Admin/', $roster);
        $this->assertStringNotContainsString('Sem guilda', $roster);
    }

    public function test_roster_lists_unguilded_students_alphabetically_when_there_are_no_guilds(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $class = $this->createClassForTeacher($teacher);
        $bruno = User::factory()->create(['role' => 'student', 'name' => 'Bruno Sem']);
        $ana = User::factory()->create(['role' => 'student', 'name' => 'Ana Sem']);
        $class->students()->attach($bruno->id, ['ranking_visible' => false, 'xp' => 0]);
        $class->students()->attach($ana->id, ['ranking_visible' => false, 'xp' => 0]);

        $roster = $this->studentRosterHtml(
            $this->actingAs($teacher)
                ->get(route('teacher.classes.show', ['schoolClass' => $class, 'tab' => 'alunos']))
                ->assertOk()
                ->getContent()
        );

        $this->assertMatchesRegularExpression('/Sem guilda[\s\S]*Ana Sem[\s\S]*Bruno Sem/', $roster);
    }

    public function test_escapes_guild_name_in_the_roster_heading(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $class = $this->createClassForTeacher($teacher);
        $student = User::factory()->create(['role' => 'student', 'name' => 'Aluno Guilda']);
        $class->students()->attach($student->id, ['ranking_visible' => false, 'xp' => 0]);
        $team = Team::query()->create([
            'class_id' => $class->id,
            'name' => "<script>alert('xss')</script>",
            'emblem' => 'shield',
        ]);
        $team->members()->attach($student->id);

        $roster = $this->studentRosterHtml(
            $this->actingAs($teacher)
                ->get(route('teacher.classes.show', ['schoolClass' => $class, 'tab' => 'alunos']))
                ->assertOk()
                ->getContent()
        );

        $this->assertStringContainsString('&lt;script&gt;', $roster);
        $this->assertStringNotContainsString("<script>alert('xss')</script>", $roster);
    }

    public function test_teacher_can_rename_a_student(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $class = $this->createClassForTeacher($teacher);
        $student = User::factory()->create([
            'role' => 'student',
            'name' => 'João da Silva',
            'email' => 'joao@example.com',
        ]);
        $class->students()->attach($student->id, ['ranking_visible' => false, 'xp' => 0]);

        $this->actingAs($teacher)
            ->from(route('teacher.classes.show', ['schoolClass' => $class, 'tab' => 'alunos']))
            ->put(route('teacher.students.update', [$class, $student]), [
                'name' => 'João Pedro da Silva',
                'email' => 'joao@example.com',
            ])
            ->assertRedirect(route('teacher.classes.show', ['schoolClass' => $class, 'tab' => 'alunos']))
            ->assertSessionHas('success');

        $student->refresh();

        $this->assertSame('João Pedro da Silva', $student->name);
        $this->assertSame('joao@example.com', $student->email);
        $this->assertSame('student', $student->role);
    }

    public function test_teacher_can_change_student_login_email(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $class = $this->createClassForTeacher($teacher);
        $student = User::factory()->create([
            'role' => 'student',
            'name' => 'João da Silva',
            'email' => 'joao@example.com',
        ]);
        $class->students()->attach($student->id, ['ranking_visible' => false, 'xp' => 0]);

        $this->actingAs($teacher)
            ->from(route('teacher.classes.show', ['schoolClass' => $class, 'tab' => 'alunos']))
            ->put(route('teacher.students.update', [$class, $student]), [
                'name' => 'João da Silva',
                'email' => 'joao.novo@example.com',
            ])
            ->assertRedirect(route('teacher.classes.show', ['schoolClass' => $class, 'tab' => 'alunos']))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('users', [
            'id' => $student->id,
            'email' => 'joao.novo@example.com',
            'role' => 'student',
        ]);
        $this->assertDatabaseMissing('users', [
            'id' => $student->id,
            'email' => 'joao@example.com',
        ]);

        $this->post(route('logout'));

        $this->post(route('login'), [
            'email' => 'joao@example.com',
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->post(route('login'), [
            'email' => 'joao.novo@example.com',
            'password' => 'password',
        ])->assertRedirect(route('student.dashboard'));
    }

    public function test_update_applies_email_and_ignores_role_in_the_payload(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $class = $this->createClassForTeacher($teacher);
        $student = User::factory()->create([
            'role' => 'student',
            'name' => 'Aluno Original',
            'email' => 'aluno@example.com',
        ]);
        $class->students()->attach($student->id, ['ranking_visible' => false, 'xp' => 0]);

        $this->actingAs($teacher)
            ->put(route('teacher.students.update', [$class, $student]), [
                'name' => 'Aluno Novo',
                'email' => 'aluno.novo@example.com',
                'role' => 'admin',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $student->refresh();

        $this->assertSame('Aluno Novo', $student->name);
        $this->assertSame('aluno.novo@example.com', $student->email);
        $this->assertSame('student', $student->role);
    }

    public function test_admin_can_rename_a_student(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $class = $this->createClassForTeacher($teacher);
        $student = User::factory()->create(['role' => 'student', 'name' => 'Aluno Original']);
        $class->students()->attach($student->id, ['ranking_visible' => false, 'xp' => 0]);
        $admin = User::factory()->create(['role' => 'admin', 'must_change_password' => false]);

        $this->actingAs($admin)
            ->from(route('teacher.students.show', [$class, $student]))
            ->put(route('teacher.students.update', [$class, $student]), [
                'name' => 'Aluno Admin',
                'email' => 'aluno.admin@example.com',
            ])
            ->assertRedirectToRoute('teacher.students.show', [$class, $student])
            ->assertSessionHas('success');

        $student->refresh();

        $this->assertSame('Aluno Admin', $student->name);
        $this->assertSame('aluno.admin@example.com', $student->email);
    }

    public function test_unauthenticated_rename_redirects_to_login(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $class = $this->createClassForTeacher($teacher);
        $student = User::factory()->create(['role' => 'student', 'name' => 'Aluno Login']);
        $class->students()->attach($student->id, ['ranking_visible' => false, 'xp' => 0]);

        $originalEmail = $student->email;

        $this->put(route('teacher.students.update', [$class, $student]), [
            'name' => 'Nome Novo',
            'email' => 'novo@example.com',
        ])->assertRedirectToRoute('login');

        $student->refresh();

        $this->assertSame('Aluno Login', $student->name);
        $this->assertSame($originalEmail, $student->email);
    }

    public function test_student_is_redirected_away_from_rename(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $class = $this->createClassForTeacher($teacher);
        $student = User::factory()->create([
            'role' => 'student',
            'name' => 'Aluno Próprio',
            'must_change_password' => false,
        ]);
        $class->students()->attach($student->id, ['ranking_visible' => false, 'xp' => 0]);

        $originalEmail = $student->email;

        $this->actingAs($student)
            ->put(route('teacher.students.update', [$class, $student]), [
                'name' => 'Nome Inválido',
                'email' => 'hacked@example.com',
            ])
            ->assertRedirect(route('student.dashboard'));

        $student->refresh();

        $this->assertSame('Aluno Próprio', $student->name);
        $this->assertSame($originalEmail, $student->email);
    }

    public function test_forbids_another_teacher_from_renaming_a_student(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $other = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $class = $this->createClassForTeacher($teacher);
        $student = User::factory()->create(['role' => 'student', 'name' => 'Aluno Protegido']);
        $class->students()->attach($student->id, ['ranking_visible' => false, 'xp' => 0]);

        $originalEmail = $student->email;

        $this->actingAs($other)
            ->put(route('teacher.students.update', [$class, $student]), [
                'name' => 'Nome Alheio',
                'email' => 'alheio@example.com',
            ])
            ->assertForbidden();

        $student->refresh();

        $this->assertSame('Aluno Protegido', $student->name);
        $this->assertSame($originalEmail, $student->email);
    }

    public function test_student_from_another_class_returns_404_when_renaming(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $class = $this->createClassForTeacher($teacher);
        $otherClass = $this->createClassForTeacher($teacher, ['area' => $class->area]);
        $foreignStudent = User::factory()->create(['role' => 'student', 'name' => 'Aluno de Fora']);
        $otherClass->students()->attach($foreignStudent->id, ['ranking_visible' => false, 'xp' => 0]);

        $originalEmail = $foreignStudent->email;

        $this->actingAs($teacher)
            ->put(route('teacher.students.update', [$class, $foreignStudent]), [
                'name' => 'Nome Infiltrado',
                'email' => 'infiltrado@example.com',
            ])
            ->assertNotFound();

        $foreignStudent->refresh();

        $this->assertSame('Aluno de Fora', $foreignStudent->name);
        $this->assertSame($originalEmail, $foreignStudent->email);
    }

    public function test_empty_name_is_rejected(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $class = $this->createClassForTeacher($teacher);
        $student = User::factory()->create(['role' => 'student', 'name' => 'Nome Válido']);
        $class->students()->attach($student->id, ['ranking_visible' => false, 'xp' => 0]);

        $this->actingAs($teacher)
            ->from(route('teacher.classes.show', ['schoolClass' => $class, 'tab' => 'alunos']))
            ->put(route('teacher.students.update', [$class, $student]), [
                'name' => '',
                'email' => $student->email,
            ])
            ->assertRedirect(route('teacher.classes.show', ['schoolClass' => $class, 'tab' => 'alunos']))
            ->assertSessionHasErrors('name');

        $this->assertSame('Nome Válido', $student->fresh()->name);
    }

    public function test_name_longer_than_120_characters_is_rejected(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $class = $this->createClassForTeacher($teacher);
        $student = User::factory()->create(['role' => 'student', 'name' => 'Nome Curto']);
        $class->students()->attach($student->id, ['ranking_visible' => false, 'xp' => 0]);

        $this->actingAs($teacher)
            ->from(route('teacher.classes.show', ['schoolClass' => $class, 'tab' => 'alunos']))
            ->put(route('teacher.students.update', [$class, $student]), [
                'name' => str_repeat('A', 121),
                'email' => $student->email,
            ])
            ->assertSessionHasErrors('name');

        $this->assertSame('Nome Curto', $student->fresh()->name);
    }

    public function test_empty_email_is_rejected(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $class = $this->createClassForTeacher($teacher);
        $student = User::factory()->create([
            'role' => 'student',
            'name' => 'Nome Válido',
            'email' => 'aluno@example.com',
        ]);
        $class->students()->attach($student->id, ['ranking_visible' => false, 'xp' => 0]);

        $this->actingAs($teacher)
            ->from(route('teacher.classes.show', ['schoolClass' => $class, 'tab' => 'alunos']))
            ->put(route('teacher.students.update', [$class, $student]), [
                'name' => 'Nome Válido',
                'email' => '',
            ])
            ->assertRedirect(route('teacher.classes.show', ['schoolClass' => $class, 'tab' => 'alunos']))
            ->assertSessionHasErrors('email');

        $this->assertSame('aluno@example.com', $student->fresh()->email);
    }

    public function test_invalid_email_is_rejected(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $class = $this->createClassForTeacher($teacher);
        $student = User::factory()->create([
            'role' => 'student',
            'email' => 'aluno@example.com',
        ]);
        $class->students()->attach($student->id, ['ranking_visible' => false, 'xp' => 0]);

        $this->actingAs($teacher)
            ->from(route('teacher.classes.show', ['schoolClass' => $class, 'tab' => 'alunos']))
            ->put(route('teacher.students.update', [$class, $student]), [
                'name' => $student->name,
                'email' => 'nao-e-email',
            ])
            ->assertSessionHasErrors('email');

        $this->assertSame('aluno@example.com', $student->fresh()->email);
    }

    public function test_duplicate_email_is_rejected(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $class = $this->createClassForTeacher($teacher);
        $student = User::factory()->create([
            'role' => 'student',
            'email' => 'aluno@example.com',
        ]);
        User::factory()->create([
            'role' => 'student',
            'email' => 'ocupado@example.com',
        ]);
        $class->students()->attach($student->id, ['ranking_visible' => false, 'xp' => 0]);

        $this->actingAs($teacher)
            ->from(route('teacher.classes.show', ['schoolClass' => $class, 'tab' => 'alunos']))
            ->put(route('teacher.students.update', [$class, $student]), [
                'name' => $student->name,
                'email' => 'ocupado@example.com',
            ])
            ->assertSessionHasErrors('email');

        $this->assertSame('aluno@example.com', $student->fresh()->email);
    }

    public function test_email_longer_than_180_characters_is_rejected(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $class = $this->createClassForTeacher($teacher);
        $student = User::factory()->create([
            'role' => 'student',
            'email' => 'aluno@example.com',
        ]);
        $class->students()->attach($student->id, ['ranking_visible' => false, 'xp' => 0]);

        $this->actingAs($teacher)
            ->from(route('teacher.classes.show', ['schoolClass' => $class, 'tab' => 'alunos']))
            ->put(route('teacher.students.update', [$class, $student]), [
                'name' => $student->name,
                'email' => str_repeat('a', 169).'@example.com',
            ])
            ->assertSessionHasErrors('email');

        $this->assertSame('aluno@example.com', $student->fresh()->email);
    }

    public function test_profile_shows_the_rename_form(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $class = $this->createClassForTeacher($teacher);
        $student = User::factory()->create(['role' => 'student', 'name' => 'Ana Ficha']);
        $class->students()->attach($student->id, ['ranking_visible' => false, 'xp' => 0]);

        $this->actingAs($teacher)
            ->get(route('teacher.students.show', [$class, $student]))
            ->assertOk()
            ->assertSee('Salvar cadastro')
            ->assertSee('E-mail de acesso')
            ->assertSee(route('teacher.students.update', [$class, $student]), false);
    }

    public function test_escapes_student_name_in_the_roster_form(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $class = $this->createClassForTeacher($teacher);
        $student = User::factory()->create([
            'role' => 'student',
            'name' => "<script>alert('xss')</script>",
        ]);
        $class->students()->attach($student->id, ['ranking_visible' => false, 'xp' => 0]);

        $html = $this->actingAs($teacher)
            ->get(route('teacher.classes.show', ['schoolClass' => $class, 'tab' => 'alunos']))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringNotContainsString("<script>alert('xss')</script>", $html);
    }

    public function test_escapes_student_email_in_the_roster_form(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $class = $this->createClassForTeacher($teacher);
        $student = User::factory()->create([
            'role' => 'student',
            'email' => 'xss"><script>alert(1)</script>@example.com',
        ]);
        $class->students()->attach($student->id, ['ranking_visible' => false, 'xp' => 0]);

        $html = $this->actingAs($teacher)
            ->get(route('teacher.classes.show', ['schoolClass' => $class, 'tab' => 'alunos']))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
    }

    public function test_teacher_can_transfer_student_to_another_class(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $source = $this->createClassForTeacher($teacher, ['name' => 'Turma A']);
        $target = $this->createClassForTeacher($teacher, [
            'name' => 'Turma B',
            'area' => $source->area,
        ]);

        $student = User::factory()->create(['role' => 'student']);
        $source->students()->attach($student->id, [
            'ranking_visible' => true,
            'xp' => 40,
            'behavior_score' => 88,
        ]);

        $team = Team::query()->create([
            'class_id' => $source->id,
            'name' => 'Alfa',
            'emblem' => 'shield',
        ]);
        $team->members()->attach($student->id);

        $activity = $source->activities()->create([
            'name' => 'Prova 1',
            'type' => 'individual',
            'weight' => 1,
        ]);

        LedgerEntry::query()->create([
            'class_id' => $source->id,
            'activity_id' => $activity->id,
            'student_id' => $student->id,
            'created_by' => $teacher->id,
            'type' => 'activity',
            'raw_score' => 9.5,
            'delta' => 0,
            'reason' => 'Prova 1',
        ]);

        $badge = Badge::query()->create([
            'name' => 'Estrela',
            'slug' => 'estrela-transfer',
            'icon' => '⭐',
            'description' => 'Teste',
        ]);
        $student->badges()->attach($badge->id, ['class_id' => $source->id]);

        $this->actingAs($teacher)
            ->from(route('teacher.classes.show', ['schoolClass' => $source, 'tab' => 'alunos']))
            ->post(route('teacher.students.transfer', [$source, $student]), [
                'target_class_id' => $target->id,
            ])
            ->assertRedirect(route('teacher.classes.show', ['schoolClass' => $source, 'tab' => 'alunos']))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('enrollments', [
            'class_id' => $source->id,
            'student_id' => $student->id,
        ]);
        $this->assertDatabaseHas('enrollments', [
            'class_id' => $target->id,
            'student_id' => $student->id,
            'xp' => 0,
            'behavior_score' => 100,
            'ranking_visible' => true,
        ]);
        $this->assertDatabaseMissing('team_members', [
            'team_id' => $team->id,
            'student_id' => $student->id,
        ]);
        $this->assertDatabaseMissing('ledger_entries', [
            'class_id' => $source->id,
            'student_id' => $student->id,
        ]);
        $this->assertDatabaseMissing('user_badges', [
            'user_id' => $student->id,
            'class_id' => $source->id,
        ]);
    }

    public function test_transfer_form_warns_about_wiping_progress(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $source = $this->createClassForTeacher($teacher, ['name' => 'Turma A']);
        $this->createClassForTeacher($teacher, [
            'name' => 'Turma B',
            'area' => $source->area,
        ]);
        $student = User::factory()->create(['role' => 'student', 'name' => 'Aluno Aviso']);
        $source->students()->attach($student->id, ['ranking_visible' => true, 'xp' => 0]);

        $this->actingAs($teacher)
            ->get(route('teacher.classes.show', ['schoolClass' => $source, 'tab' => 'alunos']))
            ->assertOk()
            ->assertSee('perde TODAS as notas, XP, medalhas', false);
    }

    public function test_teacher_cannot_transfer_student_to_another_teachers_class(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $other = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $source = $this->createClassForTeacher($teacher, ['name' => 'Turma A']);
        $foreign = $this->createClassForTeacher($other, ['name' => 'Turma Alheia']);

        $student = User::factory()->create(['role' => 'student']);
        $source->students()->attach($student->id, ['ranking_visible' => true, 'xp' => 0]);

        $this->actingAs($teacher)
            ->post(route('teacher.students.transfer', [$source, $student]), [
                'target_class_id' => $foreign->id,
            ])
            ->assertSessionHasErrors('target_class_id');

        $this->assertDatabaseHas('enrollments', [
            'class_id' => $source->id,
            'student_id' => $student->id,
        ]);
    }

    public function test_guild_rejects_student_already_in_another_guild(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $class = $this->createClassForTeacher($teacher);
        $student = User::factory()->create(['role' => 'student']);
        $class->students()->attach($student->id, ['ranking_visible' => true, 'xp' => 0]);

        $alpha = Team::query()->create([
            'class_id' => $class->id,
            'name' => 'Alpha',
            'emblem' => 'shield',
        ]);
        $alpha->members()->attach($student->id);

        $this->actingAs($teacher)
            ->from(route('teacher.classes.show', ['schoolClass' => $class, 'tab' => 'guildas']))
            ->post(route('teacher.teams.store', $class), [
                'name' => 'Beta',
                'color' => '#7c3aed',
                'emblem' => 'sword',
                'members' => [$student->id],
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('members');

        $this->assertDatabaseMissing('teams', [
            'class_id' => $class->id,
            'name' => 'Beta',
        ]);
        $this->assertSame(1, $alpha->members()->count());
    }

    public function test_create_guild_form_lists_available_students_and_who_already_has_a_guild(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $class = $this->createClassForTeacher($teacher);
        $taken = User::factory()->create(['role' => 'student', 'name' => 'Aluno Ocupado']);
        $free = User::factory()->create(['role' => 'student', 'name' => 'Aluno Livre']);
        $class->students()->attach($taken->id, ['ranking_visible' => true, 'xp' => 0]);
        $class->students()->attach($free->id, ['ranking_visible' => true, 'xp' => 0]);

        $team = Team::query()->create([
            'class_id' => $class->id,
            'name' => 'Dragões',
            'emblem' => 'dragon',
        ]);
        $team->members()->attach($taken->id);

        $html = $this->actingAs($teacher)
            ->get(route('teacher.classes.show', ['schoolClass' => $class, 'tab' => 'guildas']))
            ->assertOk()
            ->getContent();

        $createForm = $this->formHtml($html, route('teacher.teams.store', $class));
        $guildCard = $this->formHtml($html, route('teacher.teams.update', [$class, $team]));

        $this->assertStringContainsString('Disponíveis', $createForm);
        $this->assertStringContainsString('Aluno Livre', $createForm);
        $this->assertStringContainsString('Já em outra guilda', $createForm);
        $this->assertStringContainsString('Aluno Ocupado · Dragões', $createForm);
        $this->assertStringContainsString('name="members[]" value="'.$free->id.'"', $createForm);

        $this->assertStringContainsString('Aluno Ocupado', $guildCard);
        $this->assertStringNotContainsString('Aluno Livre', $guildCard);
        $this->assertStringNotContainsString('Já em outra guilda', $guildCard);
        $this->assertSame(
            1,
            preg_match_all('/name="members\[\]" value="'.$taken->id.'"/', $guildCard)
        );
    }

    public function test_saved_guild_card_does_not_list_members_of_another_guild(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $class = $this->createClassForTeacher($teacher);
        $ana = User::factory()->create(['role' => 'student', 'name' => 'Ana Guilda']);
        $bruno = User::factory()->create(['role' => 'student', 'name' => 'Bruno Guilda']);
        $class->students()->attach($ana->id, ['ranking_visible' => true, 'xp' => 0]);
        $class->students()->attach($bruno->id, ['ranking_visible' => true, 'xp' => 0]);

        $dragons = Team::query()->create([
            'class_id' => $class->id,
            'name' => 'Dragões',
            'emblem' => 'dragon',
        ]);
        $wolves = Team::query()->create([
            'class_id' => $class->id,
            'name' => 'Lobos',
            'emblem' => 'wolf',
        ]);
        $dragons->members()->attach($ana->id);
        $wolves->members()->attach($bruno->id);

        $html = $this->actingAs($teacher)
            ->get(route('teacher.classes.show', ['schoolClass' => $class, 'tab' => 'guildas']))
            ->assertOk()
            ->getContent();

        $dragonsCard = $this->formHtml($html, route('teacher.teams.update', [$class, $dragons]));
        $wolvesCard = $this->formHtml($html, route('teacher.teams.update', [$class, $wolves]));

        $this->assertStringContainsString('Ana Guilda', $dragonsCard);
        $this->assertStringNotContainsString('Bruno Guilda', $dragonsCard);
        $this->assertStringContainsString('Bruno Guilda', $wolvesCard);
        $this->assertStringNotContainsString('Ana Guilda', $wolvesCard);
    }

    public function test_edit_members_view_lists_available_students_on_the_guild_card(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $class = $this->createClassForTeacher($teacher);
        $taken = User::factory()->create(['role' => 'student', 'name' => 'Aluno Ocupado']);
        $free = User::factory()->create(['role' => 'student', 'name' => 'Aluno Livre']);
        $class->students()->attach($taken->id, ['ranking_visible' => true, 'xp' => 0]);
        $class->students()->attach($free->id, ['ranking_visible' => true, 'xp' => 0]);

        $team = Team::query()->create([
            'class_id' => $class->id,
            'name' => 'Dragões',
            'emblem' => 'dragon',
        ]);
        $team->members()->attach($taken->id);

        $html = $this->actingAs($teacher)
            ->get(route('teacher.classes.show', [
                'schoolClass' => $class,
                'tab' => 'guildas',
                'edit_team' => $team->id,
            ]))
            ->assertOk()
            ->getContent();

        $guildCard = $this->formHtml($html, route('teacher.teams.update', [$class, $team]));

        $this->assertStringContainsString('Aluno Ocupado', $guildCard);
        $this->assertStringContainsString('Aluno Livre', $guildCard);
        $this->assertStringContainsString('name="members[]" value="'.$free->id.'"', $guildCard);
    }

    /**
     * @return non-empty-string
     */
    private function formHtml(string $html, string $action): string
    {
        $quoted = preg_quote(e($action), '/');
        $this->assertSame(1, preg_match(
            '/<form[^>]*action="'.$quoted.'"[^>]*>([\s\S]*?)<\/form>/',
            $html,
            $matches
        ));

        return $matches[1];
    }

    /**
     * @return non-empty-string
     */
    private function studentRosterHtml(string $html): string
    {
        $this->assertSame(1, preg_match(
            '/<section[^>]*data-student-roster[^>]*>([\s\S]*?)<\/section>/',
            $html,
            $matches
        ));

        return $matches[1];
    }
}
