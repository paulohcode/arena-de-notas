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

    public function test_guild_form_hides_students_already_in_another_guild(): void
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

        $this->assertStringContainsString('Aluno Livre', $html);
        $this->assertStringContainsString('Aluno Ocupado · Dragões', $html);
        $this->assertSame(
            1,
            preg_match_all('/name="members\[\]" value="'.$taken->id.'"/', $html)
        );
    }
}
