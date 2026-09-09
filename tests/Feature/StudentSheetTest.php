<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
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

    public function test_teacher_sees_weighted_average_calculation_on_the_sheet(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $class = $this->createClassForTeacher($teacher);
        $student = $this->enrollStudent($class, 'Ana Maga');
        $activity = Activity::query()->create([
            'class_id' => $class->id,
            'name' => 'Prova',
            'type' => 'individual',
            'max_score' => 100,
            'weight' => 2,
        ]);

        $this->actingAs($teacher)
            ->post(route('teacher.grades.store', $class), [
                'activity_id' => $activity->id,
                'scores' => [$student->id => 80],
            ]);

        $this->actingAs($teacher)
            ->get(route('teacher.students.show', [$class, $student]))
            ->assertSee('A média ponderada é a soma de (nota × peso) dividida pela soma dos pesos.')
            ->assertSee('Prova')
            ->assertSee('peso 2')
            ->assertSee('80.0 × 2 = 160.0')
            ->assertSee('Comportamento')
            ->assertSee('100.0 × 1 = 100.0')
            ->assertSee('Soma (nota × peso)')
            ->assertSee('260.0')
            ->assertSee('Soma dos pesos')
            ->assertSee('Média ponderada')
            ->assertSee('86.7')
            ->assertSee('Média final');
    }

    public function test_admin_sees_weighted_average_calculation_on_the_sheet(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $class = $this->createClassForTeacher($teacher);
        $student = $this->enrollStudent($class, 'Bruno Admin');
        $activity = Activity::query()->create([
            'class_id' => $class->id,
            'name' => 'Prova',
            'type' => 'individual',
            'max_score' => 100,
            'weight' => 2,
        ]);
        $admin = User::factory()->create(['role' => 'admin', 'must_change_password' => false]);

        $this->actingAs($teacher)
            ->post(route('teacher.grades.store', $class), [
                'activity_id' => $activity->id,
                'scores' => [$student->id => 80],
            ]);

        $this->actingAs($admin)
            ->get(route('teacher.students.show', [$class, $student]))
            ->assertSee('A média ponderada é a soma de (nota × peso) dividida pela soma dos pesos.')
            ->assertSee('80.0 × 2 = 160.0')
            ->assertSee('Média final');
    }

    public function test_student_dashboard_hides_the_staff_average_calculation(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $class = $this->createClassForTeacher($teacher);
        $student = $this->enrollStudent($class, 'Carla Aluna');
        $activity = Activity::query()->create([
            'class_id' => $class->id,
            'name' => 'Prova',
            'type' => 'individual',
            'max_score' => 100,
            'weight' => 2,
        ]);

        $this->actingAs($teacher)
            ->post(route('teacher.grades.store', $class), [
                'activity_id' => $activity->id,
                'scores' => [$student->id => 80],
            ]);

        $this->actingAs($student)
            ->get(route('student.dashboard'))
            ->assertSee('Composição da nota')
            ->assertSee('Prova')
            ->assertSee('peso 2')
            ->assertDontSee('A média ponderada é a soma de (nota × peso) dividida pela soma dos pesos.')
            ->assertDontSee('80.0 × 2 = 160.0')
            ->assertDontSee('Média final')
            ->assertDontSee('sem lançamento · usa nota padrão');
    }

    public function test_student_dashboard_lists_own_attendance_newest_first(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $class = $this->createClassForTeacher($teacher);
        $student = $this->enrollStudent($class, 'Carla Aluna');

        $this->recordAttendance($class, $teacher, $student, '2026-09-01', AttendanceRecord::STATUS_ABSENT);
        $this->recordAttendance($class, $teacher, $student, '2026-09-08', AttendanceRecord::STATUS_PRESENT);
        $this->recordAttendance($class, $teacher, $student, '2026-09-03', AttendanceRecord::STATUS_JUSTIFIED);

        $this->actingAs($student)
            ->get(route('student.dashboard'))
            ->assertSee('data-attendance-dropdown', false)
            ->assertSeeInOrder([
                '08/09/2026 · Presença',
                '03/09/2026 · Justificada',
                '01/09/2026 · Falta',
            ]);
    }

    public function test_student_dashboard_omits_draft_attendance(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $class = $this->createClassForTeacher($teacher);
        $student = $this->enrollStudent($class, 'Carla Aluna');

        $this->recordAttendance($class, $teacher, $student, '2026-09-08', AttendanceRecord::STATUS_PRESENT);
        $this->recordAttendance($class, $teacher, $student, '2026-09-09', null);

        $this->actingAs($student)
            ->get(route('student.dashboard'))
            ->assertSee('08/09/2026 · Presença')
            ->assertDontSee('09/09/2026');
    }

    public function test_student_dashboard_does_not_show_classmate_attendance(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $class = $this->createClassForTeacher($teacher);
        $student = $this->enrollStudent($class, 'Carla Aluna');
        $classmate = $this->enrollStudent($class, 'Bruno Colega');

        $this->recordAttendance($class, $teacher, $student, '2026-09-08', AttendanceRecord::STATUS_PRESENT);
        $this->recordAttendance($class, $teacher, $classmate, '2026-09-08', AttendanceRecord::STATUS_ABSENT);

        $this->actingAs($student)
            ->get(route('student.dashboard'))
            ->assertSee('08/09/2026 · Presença')
            ->assertDontSee('08/09/2026 · Falta');
    }

    public function test_student_dashboard_does_not_show_attendance_from_another_class(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $class = $this->createClassForTeacher($teacher, ['name' => 'Turma Atual']);
        $otherClass = $this->createClassForTeacher($teacher, ['name' => 'Outra Turma', 'area' => $class->area]);
        $student = $this->enrollStudent($class, 'Carla Aluna');
        $otherClass->students()->attach($student->id, [
            'ranking_visible' => true,
            'xp' => 0,
            'behavior_score' => 100,
        ]);

        $this->recordAttendance($class, $teacher, $student, '2026-09-08', AttendanceRecord::STATUS_PRESENT);
        $this->recordAttendance($otherClass, $teacher, $student, '2026-09-01', AttendanceRecord::STATUS_ABSENT);

        $this->actingAs($student)
            ->withSession(['current_class_id' => $class->id])
            ->get(route('student.dashboard'))
            ->assertSee('08/09/2026 · Presença')
            ->assertDontSee('01/09/2026 · Falta');
    }

    public function test_student_dashboard_shows_empty_attendance_when_none_saved(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $class = $this->createClassForTeacher($teacher);
        $student = $this->enrollStudent($class, 'Carla Aluna');

        $this->actingAs($student)
            ->get(route('student.dashboard'))
            ->assertSee('data-attendance-dropdown', false)
            ->assertSee('Nenhuma chamada ainda');
    }

    public function test_teacher_sheet_does_not_show_the_attendance_dropdown(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $class = $this->createClassForTeacher($teacher);
        $student = $this->enrollStudent($class, 'Ana Maga');

        $this->recordAttendance($class, $teacher, $student, '2026-09-08', AttendanceRecord::STATUS_PRESENT);

        $this->actingAs($teacher)
            ->get(route('teacher.students.show', [$class, $student]))
            ->assertDontSee('data-attendance-dropdown', false)
            ->assertDontSee('08/09/2026 · Presença')
            ->assertDontSee('Nenhuma chamada ainda');
    }

    public function test_escapes_activity_name_in_the_average_breakdown(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $class = $this->createClassForTeacher($teacher);
        $student = $this->enrollStudent($class, 'Ana Maga');
        Activity::query()->create([
            'class_id' => $class->id,
            'name' => "<script>alert('xss')</script>",
            'type' => 'individual',
            'max_score' => 100,
            'weight' => 1,
        ]);

        $html = $this->actingAs($teacher)
            ->get(route('teacher.students.show', [$class, $student]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringNotContainsString("<script>alert('xss')</script>", $html);
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

    private function recordAttendance(
        SchoolClass $class,
        User $teacher,
        User $student,
        string $heldOn,
        ?string $status,
    ): AttendanceRecord {
        $session = AttendanceSession::query()
            ->where('class_id', $class->id)
            ->whereDate('held_on', $heldOn)
            ->first();

        if (! $session) {
            $session = AttendanceSession::query()->create([
                'class_id' => $class->id,
                'held_on' => $heldOn,
                'created_by' => $teacher->id,
            ]);
        }

        return AttendanceRecord::query()->create([
            'attendance_session_id' => $session->id,
            'student_id' => $student->id,
            'status' => $status,
        ]);
    }
}
