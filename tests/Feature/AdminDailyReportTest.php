<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\Duel;
use App\Models\Enrollment;
use App\Models\EnrollmentCosmetic;
use App\Models\LedgerEntry;
use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AdminDailyReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_report_redirects_to_login(): void
    {
        $this->get(route('admin.reports.daily'))
            ->assertRedirectToRoute('login');
    }

    public function test_teacher_cannot_open_daily_report(): void
    {
        $teacher = User::factory()->create([
            'role' => 'teacher',
            'must_change_password' => false,
        ]);

        $this->actingAs($teacher)
            ->get(route('admin.reports.daily'))
            ->assertRedirect(route('teacher.dashboard'));
    }

    public function test_student_cannot_open_daily_report(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $class = $this->createClassForTeacher($teacher);
        $student = $this->enrollStudent($class, 'Ana Souza');

        $this->actingAs($student)
            ->get(route('admin.reports.daily'))
            ->assertRedirect(route('student.dashboard'));
    }

    public function test_admin_sees_empty_states_for_quiet_day(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'must_change_password' => false]);

        $this->actingAs($admin)
            ->get(route('admin.reports.daily', ['date' => '2026-09-14']))
            ->assertOk()
            ->assertSee('Relatório diário')
            ->assertSee('Nenhuma chamada registrada neste dia.')
            ->assertSee('Nenhuma batalha resolvida neste dia.')
            ->assertSee('Nenhuma nota lançada neste dia.')
            ->assertSee('Nenhum item adquirido neste dia.');
    }

    public function test_admin_sees_absentee_for_held_on_day_only(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'must_change_password' => false]);
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $class = $this->createClassForTeacher($teacher, ['name' => 'Turma Alpha']);
        $absent = $this->enrollStudent($class, 'Faltoso Silva');
        $justified = $this->enrollStudent($class, 'Justificado Melo');
        $present = $this->enrollStudent($class, 'Presente Costa');

        $session = AttendanceSession::query()->create([
            'class_id' => $class->id,
            'held_on' => '2026-09-14',
            'created_by' => $teacher->id,
        ]);

        AttendanceRecord::query()->create([
            'attendance_session_id' => $session->id,
            'student_id' => $absent->id,
            'status' => AttendanceRecord::STATUS_ABSENT,
        ]);
        AttendanceRecord::query()->create([
            'attendance_session_id' => $session->id,
            'student_id' => $justified->id,
            'status' => AttendanceRecord::STATUS_JUSTIFIED,
        ]);
        AttendanceRecord::query()->create([
            'attendance_session_id' => $session->id,
            'student_id' => $present->id,
            'status' => AttendanceRecord::STATUS_PRESENT,
        ]);

        $response = $this->actingAs($admin)
            ->get(route('admin.reports.daily', ['date' => '2026-09-14']));

        $response->assertOk()
            ->assertSee('Faltoso Silva')
            ->assertSee('Justificado Melo')
            ->assertSee('Turma Alpha')
            ->assertDontSee('Presente Costa');

        $this->assertMatchesRegularExpression(
            '/Justificado Melo.*?Justificada/s',
            $response->getContent()
        );

        $this->actingAs($admin)
            ->get(route('admin.reports.daily', ['date' => '2026-09-13']))
            ->assertOk()
            ->assertDontSee('Faltoso Silva')
            ->assertDontSee('Justificado Melo')
            ->assertSee('Nenhuma chamada registrada neste dia.');
    }

    public function test_resolved_duel_appears_but_expired_does_not(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'must_change_password' => false]);
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $class = $this->createClassForTeacher($teacher, ['name' => 'Turma Arena']);
        $challenger = $this->enrollStudent($class, 'Desafiante Rocha');
        $opponent = $this->enrollStudent($class, 'Oponente Lima');
        $expiredChallenger = $this->enrollStudent($class, 'Expirado Alves');
        $expiredOpponent = $this->enrollStudent($class, 'Expirado Nunes');

        $resolvedAt = Carbon::parse('2026-09-14 15:00:00', config('app.display_timezone'))->utc();

        Duel::query()->create([
            'class_id' => $class->id,
            'challenger_id' => $challenger->id,
            'opponent_id' => $opponent->id,
            'status' => Duel::STATUS_RESOLVED,
            'seed' => 1,
            'winner_id' => $challenger->id,
            'glory_winner' => 10,
            'glory_loser' => 2,
            'resolved_at' => $resolvedAt,
        ]);

        Duel::query()->create([
            'class_id' => $class->id,
            'challenger_id' => $expiredChallenger->id,
            'opponent_id' => $expiredOpponent->id,
            'status' => Duel::STATUS_EXPIRED,
            'seed' => 2,
            'resolved_at' => $resolvedAt,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.reports.daily', ['date' => '2026-09-14']))
            ->assertOk()
            ->assertSee('Desafiante Rocha')
            ->assertSee('Oponente Lima')
            ->assertDontSee('Expirado Alves')
            ->assertDontSee('Expirado Nunes');
    }

    public function test_activity_grade_updated_on_day_appears(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'must_change_password' => false]);
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $class = $this->createClassForTeacher($teacher, ['name' => 'Turma Notas']);
        $student = $this->enrollStudent($class, 'Nota Aluno');

        $activity = Activity::query()->create([
            'class_id' => $class->id,
            'name' => 'Missão Relatório',
            'type' => Activity::TYPE_INDIVIDUAL,
            'max_score' => 10,
            'weight' => 1,
        ]);

        $createdAt = Carbon::parse('2026-09-10 12:00:00', config('app.display_timezone'))->utc();
        $updatedAt = Carbon::parse('2026-09-14 18:30:00', config('app.display_timezone'))->utc();

        $entry = LedgerEntry::query()->create([
            'class_id' => $class->id,
            'activity_id' => $activity->id,
            'student_id' => $student->id,
            'created_by' => $teacher->id,
            'type' => 'activity',
            'raw_score' => 8.5,
            'delta' => 0,
            'reason' => null,
        ]);
        $entry->forceFill([
            'created_at' => $createdAt,
            'updated_at' => $updatedAt,
        ])->saveQuietly();

        $this->actingAs($admin)
            ->get(route('admin.reports.daily', ['date' => '2026-09-14']))
            ->assertOk()
            ->assertSee('Nota Aluno')
            ->assertSee('Missão Relatório')
            ->assertSee('8,5');

        $this->actingAs($admin)
            ->get(route('admin.reports.daily', ['date' => '2026-09-10']))
            ->assertOk()
            ->assertDontSee('Missão Relatório');
    }

    public function test_item_created_on_day_appears(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'must_change_password' => false]);
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $class = $this->createClassForTeacher($teacher, ['name' => 'Turma Loja']);
        $student = $this->enrollStudent($class, 'Comprador Dias');
        $enrollment = Enrollment::query()
            ->where('class_id', $class->id)
            ->where('student_id', $student->id)
            ->firstOrFail();

        $cosmetic = EnrollmentCosmetic::query()->create([
            'enrollment_id' => $enrollment->id,
            'item_key' => 'frame_bronze',
        ]);
        $cosmetic->forceFill([
            'created_at' => Carbon::parse('2026-09-14 10:00:00', config('app.display_timezone'))->utc(),
            'updated_at' => Carbon::parse('2026-09-14 10:00:00', config('app.display_timezone'))->utc(),
        ])->saveQuietly();

        $this->actingAs($admin)
            ->get(route('admin.reports.daily', ['date' => '2026-09-14']))
            ->assertOk()
            ->assertSee('Comprador Dias')
            ->assertSee('Anel de Bronze');
    }

    public function test_area_filter_hides_other_realm_class(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'must_change_password' => false]);
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);

        $areaA = $this->createArea(['name' => 'Reino Norte']);
        $areaB = $this->createArea(['name' => 'Reino Sul']);
        $teacher->areas()->syncWithoutDetaching([$areaA->id, $areaB->id]);

        $classA = $this->createClassForTeacher($teacher, [
            'name' => 'Turma Norte',
            'area' => $areaA,
        ]);
        $classB = $this->createClassForTeacher($teacher, [
            'name' => 'Turma Sul',
            'area' => $areaB,
        ]);

        $studentA = $this->enrollStudent($classA, 'Aluno Norte');
        $studentB = $this->enrollStudent($classB, 'Aluno Sul');

        foreach ([[$classA, $studentA], [$classB, $studentB]] as [$class, $student]) {
            $session = AttendanceSession::query()->create([
                'class_id' => $class->id,
                'held_on' => '2026-09-14',
                'created_by' => $teacher->id,
            ]);
            AttendanceRecord::query()->create([
                'attendance_session_id' => $session->id,
                'student_id' => $student->id,
                'status' => AttendanceRecord::STATUS_ABSENT,
            ]);
        }

        $this->actingAs($admin)
            ->get(route('admin.reports.daily', [
                'date' => '2026-09-14',
                'area' => $areaA->id,
            ]))
            ->assertOk()
            ->assertSee('Aluno Norte')
            ->assertSee('Turma Norte')
            ->assertDontSee('Aluno Sul')
            ->assertDontSee('Turma Sul');
    }

    public function test_active_student_via_last_accessed_at_appears(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'must_change_password' => false]);
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $class = $this->createClassForTeacher($teacher, ['name' => 'Turma Ativa']);
        $student = $this->enrollStudent($class, 'Ativo Mendes');

        $student->forceFill([
            'last_accessed_at' => Carbon::parse('2026-09-14 09:15:00', config('app.display_timezone'))->utc(),
        ])->save();

        $this->actingAs($admin)
            ->get(route('admin.reports.daily', ['date' => '2026-09-14']))
            ->assertOk()
            ->assertSee('Ativo Mendes')
            ->assertSee('09:15');
    }

    private function enrollStudent(SchoolClass $class, string $name): User
    {
        $student = User::factory()->create([
            'name' => $name,
            'role' => 'student',
            'character_class' => 'guerreiro',
            'must_change_password' => false,
            'character_name' => 'Heroi '.$name,
            'character_avatar' => 'lobo',
            'character_approval_status' => 'approved',
        ]);

        $class->students()->syncWithoutDetaching([
            $student->id => [
                'ranking_visible' => true,
                'xp' => 0,
                'glory' => 0,
                'relics' => 0,
                'arena_wins' => 0,
                'arena_losses' => 0,
                'behavior_score' => 100,
            ],
        ]);

        return $student->fresh();
    }
}
