<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\SchoolClass;
use App\Models\Team;
use App\Models\User;
use App\Notifications\GameAlert;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ActivityMissingWorkTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_grade_notifies_students_without_a_score(): void
    {
        [$teacher, $class, $activity, $alice, $bob] = $this->makeIndividualSetup();

        Notification::fake();

        $this->actingAs($teacher)
            ->post(route('teacher.grades.store', $class), [
                'activity_id' => $activity->id,
                'scores' => [$alice->id => 80],
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        Notification::assertSentTo($bob, GameAlert::class, function (GameAlert $alert): bool {
            return $alert->alertType === 'warn'
                && $alert->title === 'Missão pendente'
                && $alert->message === 'Missão Prova falta concluir.';
        });
        Notification::assertNotSentTo($alice, GameAlert::class, function (GameAlert $alert): bool {
            return $alert->alertType === 'warn';
        });
    }

    public function test_later_grade_updates_do_not_repeat_the_missing_work_alert(): void
    {
        [$teacher, $class, $activity, $alice, $bob] = $this->makeIndividualSetup();

        $this->actingAs($teacher)
            ->post(route('teacher.grades.store', $class), [
                'activity_id' => $activity->id,
                'scores' => [$alice->id => 80],
            ]);

        Notification::fake();

        $this->actingAs($teacher)
            ->post(route('teacher.grades.store', $class), [
                'activity_id' => $activity->id,
                'scores' => [$alice->id => 90],
            ])
            ->assertRedirect();

        Notification::assertNotSentTo($bob, GameAlert::class, function (GameAlert $alert): bool {
            return $alert->alertType === 'warn';
        });
    }

    public function test_ungraded_student_sees_pending_mission_on_the_dashboard(): void
    {
        [$teacher, $class, $activity, $alice, $bob] = $this->makeIndividualSetup();

        $this->actingAs($teacher)
            ->post(route('teacher.grades.store', $class), [
                'activity_id' => $activity->id,
                'scores' => [$alice->id => 80],
            ]);

        $this->actingAs($bob)
            ->get(route('student.dashboard'))
            ->assertOk()
            ->assertSee('Missões pendentes')
            ->assertSee('Missão Prova falta concluir.');

        $this->actingAs($alice)
            ->get(route('student.dashboard'))
            ->assertOk()
            ->assertDontSee('Missão Prova falta concluir.');
    }

    public function test_pending_mission_disappears_after_the_student_receives_a_grade(): void
    {
        [$teacher, $class, $activity, $alice, $bob] = $this->makeIndividualSetup();

        $this->actingAs($teacher)
            ->post(route('teacher.grades.store', $class), [
                'activity_id' => $activity->id,
                'scores' => [$alice->id => 80],
            ]);

        $this->actingAs($teacher)
            ->post(route('teacher.grades.store', $class), [
                'activity_id' => $activity->id,
                'scores' => [$bob->id => 70],
            ]);

        $this->actingAs($bob)
            ->get(route('student.dashboard'))
            ->assertOk()
            ->assertDontSee('Missão Prova falta concluir.');
    }

    public function test_teacher_can_emit_missing_work_warning(): void
    {
        [$teacher, $class, $activity, $alice, $bob] = $this->makeIndividualSetup();

        $this->actingAs($teacher)
            ->post(route('teacher.grades.store', $class), [
                'activity_id' => $activity->id,
                'scores' => [$alice->id => 80],
            ]);

        Notification::fake();

        $this->actingAs($teacher)
            ->post(route('teacher.activities.warn', [$class, $activity]))
            ->assertRedirectToRoute('teacher.classes.show', ['schoolClass' => $class, 'tab' => 'atividades'])
            ->assertSessionHas('success', 'Aviso enviado para 1 aluno pendente.');

        Notification::assertSentTo($bob, GameAlert::class, function (GameAlert $alert): bool {
            return $alert->alertType === 'warn'
                && $alert->message === 'Missão Prova falta concluir.';
        });
        Notification::assertNotSentTo($alice, GameAlert::class, function (GameAlert $alert): bool {
            return $alert->alertType === 'warn';
        });
    }

    public function test_warn_before_any_grade_asks_teacher_to_launch_a_score_first(): void
    {
        [$teacher, $class, $activity] = $this->makeIndividualSetup();

        $this->actingAs($teacher)
            ->post(route('teacher.activities.warn', [$class, $activity]))
            ->assertRedirectToRoute('teacher.classes.show', ['schoolClass' => $class, 'tab' => 'atividades'])
            ->assertSessionHas('success', 'Lance ao menos uma nota antes de avisar quem falta entregar.');
    }

    public function test_warn_when_everyone_has_a_grade_reports_nobody_pending(): void
    {
        [$teacher, $class, $activity, $alice, $bob] = $this->makeIndividualSetup();

        $this->actingAs($teacher)
            ->post(route('teacher.grades.store', $class), [
                'activity_id' => $activity->id,
                'scores' => [
                    $alice->id => 80,
                    $bob->id => 70,
                ],
            ]);

        $this->actingAs($teacher)
            ->post(route('teacher.activities.warn', [$class, $activity]))
            ->assertRedirectToRoute('teacher.classes.show', ['schoolClass' => $class, 'tab' => 'atividades'])
            ->assertSessionHas('success', 'Nenhum aluno está pendente nesta atividade.');
    }

    public function test_forbids_another_teacher_from_emitting_the_warning(): void
    {
        [$owner, $class, $activity] = $this->makeIndividualSetup();
        $otherTeacher = User::factory()->create(['role' => 'teacher']);

        $this->actingAs($otherTeacher)
            ->post(route('teacher.activities.warn', [$class, $activity]))
            ->assertForbidden();
    }

    public function test_activity_from_another_class_returns_404_when_warning(): void
    {
        [$teacher, $class] = $this->makeIndividualSetup();
        $otherClass = $this->createClassForTeacher($teacher, ['area' => $class->area, 'name' => 'Outra turma']);
        $foreign = Activity::query()->create([
            'class_id' => $otherClass->id,
            'name' => 'Fora',
            'type' => 'individual',
            'max_score' => 100,
            'weight' => 1,
        ]);

        $this->actingAs($teacher)
            ->post(route('teacher.activities.warn', [$class, $foreign]))
            ->assertNotFound();
    }

    public function test_first_team_grade_notifies_members_of_ungraded_guilds(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $class = $this->createClassForTeacher($teacher, ['name' => 'Turma Guilda']);
        $activity = Activity::query()->create([
            'class_id' => $class->id,
            'name' => 'Missão em equipe',
            'type' => 'team',
            'max_score' => 100,
            'weight' => 1,
        ]);

        $alice = $this->enrollStudent($class, 'Alice');
        $bob = $this->enrollStudent($class, 'Bob');
        $alpha = Team::query()->create(['class_id' => $class->id, 'name' => 'Alpha', 'emblem' => 'shield']);
        $beta = Team::query()->create(['class_id' => $class->id, 'name' => 'Beta', 'emblem' => 'sword']);
        $alpha->members()->attach($alice->id);
        $beta->members()->attach($bob->id);

        Notification::fake();

        $this->actingAs($teacher)
            ->post(route('teacher.grades.store', $class), [
                'activity_id' => $activity->id,
                'scores' => [$alpha->id => 90],
            ])
            ->assertRedirect();

        Notification::assertSentTo($bob, GameAlert::class, function (GameAlert $alert): bool {
            return $alert->alertType === 'warn'
                && $alert->message === 'Missão Missão em equipe falta concluir.';
        });
        Notification::assertNotSentTo($alice, GameAlert::class, function (GameAlert $alert): bool {
            return $alert->alertType === 'warn';
        });
    }

    public function test_escapes_activity_name_in_the_pending_mission_banner(): void
    {
        [$teacher, $class, $activity, $alice, $bob] = $this->makeIndividualSetup("<script>alert('xss')</script>");

        $this->actingAs($teacher)
            ->post(route('teacher.grades.store', $class), [
                'activity_id' => $activity->id,
                'scores' => [$alice->id => 80],
            ]);

        $html = $this->actingAs($bob)
            ->get(route('student.dashboard'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringNotContainsString("<script>alert('xss')</script>", $html);
    }

    public function test_guild_members_see_which_teammate_has_not_finished_the_mission(): void
    {
        [$teacher, $class, $activity, $alice, $bob] = $this->makeIndividualSetup();
        $team = Team::query()->create(['class_id' => $class->id, 'name' => 'Águias', 'emblem' => 'owl']);
        $team->members()->attach([$alice->id, $bob->id]);

        $this->actingAs($teacher)
            ->post(route('teacher.grades.store', $class), [
                'activity_id' => $activity->id,
                'scores' => [$alice->id => 80],
            ]);

        $this->actingAs($alice)
            ->get(route('student.dashboard'))
            ->assertOk()
            ->assertSee('Pendências da guilda')
            ->assertSee('Bob ainda não concluiu a missão Prova.')
            ->assertDontSee('Alice ainda não concluiu a missão Prova.');

        $this->actingAs($bob)
            ->get(route('student.dashboard'))
            ->assertOk()
            ->assertSee('Pendências da guilda')
            ->assertSee('Bob ainda não concluiu a missão Prova.');
    }

    public function test_other_guild_does_not_see_another_teams_missing_member(): void
    {
        [$teacher, $class, $activity, $alice, $bob] = $this->makeIndividualSetup();
        $carla = $this->enrollStudent($class, 'Carla');
        $eagles = Team::query()->create(['class_id' => $class->id, 'name' => 'Águias', 'emblem' => 'owl']);
        $wolves = Team::query()->create(['class_id' => $class->id, 'name' => 'Lobos', 'emblem' => 'wolf']);
        $eagles->members()->attach([$alice->id, $bob->id]);
        $wolves->members()->attach($carla->id);

        $this->actingAs($teacher)
            ->post(route('teacher.grades.store', $class), [
                'activity_id' => $activity->id,
                'scores' => [$alice->id => 80],
            ]);

        $this->actingAs($carla)
            ->get(route('student.dashboard'))
            ->assertOk()
            ->assertSee('Pendências da guilda')
            ->assertSee('Carla ainda não concluiu a missão Prova.')
            ->assertDontSee('Bob ainda não concluiu a missão Prova.');
    }

    public function test_guild_page_hides_pending_member_alerts_from_students_in_other_guilds(): void
    {
        [$teacher, $class, $activity, $alice, $bob] = $this->makeIndividualSetup();
        $carla = $this->enrollStudent($class, 'Carla');
        $eagles = Team::query()->create(['class_id' => $class->id, 'name' => 'Águias', 'emblem' => 'owl']);
        $wolves = Team::query()->create(['class_id' => $class->id, 'name' => 'Lobos', 'emblem' => 'wolf']);
        $eagles->members()->attach([$alice->id, $bob->id]);
        $wolves->members()->attach($carla->id);

        $this->actingAs($teacher)
            ->post(route('teacher.grades.store', $class), [
                'activity_id' => $activity->id,
                'scores' => [$alice->id => 80],
            ]);

        $this->actingAs($carla)
            ->get(route('ranking.guild', [$class, $eagles]))
            ->assertOk()
            ->assertDontSee('Pendências da guilda')
            ->assertDontSee('Bob ainda não concluiu a missão Prova.');
    }

    public function test_guild_members_see_when_the_team_mission_is_still_open(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $class = $this->createClassForTeacher($teacher, ['name' => 'Turma Guilda']);
        $activity = Activity::query()->create([
            'class_id' => $class->id,
            'name' => 'Missão em equipe',
            'type' => 'team',
            'max_score' => 100,
            'weight' => 1,
        ]);

        $alice = $this->enrollStudent($class, 'Alice');
        $bob = $this->enrollStudent($class, 'Bob');
        $alpha = Team::query()->create(['class_id' => $class->id, 'name' => 'Alpha', 'emblem' => 'shield']);
        $beta = Team::query()->create(['class_id' => $class->id, 'name' => 'Beta', 'emblem' => 'sword']);
        $alpha->members()->attach($alice->id);
        $beta->members()->attach($bob->id);

        $this->actingAs($teacher)
            ->post(route('teacher.grades.store', $class), [
                'activity_id' => $activity->id,
                'scores' => [$alpha->id => 90],
            ]);

        $this->actingAs($bob)
            ->get(route('student.dashboard'))
            ->assertOk()
            ->assertSee('Pendências da guilda')
            ->assertSee('A guilda ainda não concluiu a missão Missão em equipe.');

        $this->actingAs($alice)
            ->get(route('student.dashboard'))
            ->assertOk()
            ->assertDontSee('Pendências da guilda')
            ->assertDontSee('A guilda ainda não concluiu a missão Missão em equipe.');
    }

    public function test_guild_page_shows_pending_members_to_teammates(): void
    {
        [$teacher, $class, $activity, $alice, $bob] = $this->makeIndividualSetup();
        $team = Team::query()->create(['class_id' => $class->id, 'name' => 'Águias', 'emblem' => 'owl']);
        $team->members()->attach([$alice->id, $bob->id]);

        $this->actingAs($teacher)
            ->post(route('teacher.grades.store', $class), [
                'activity_id' => $activity->id,
                'scores' => [$alice->id => 80],
            ]);

        $this->actingAs($alice)
            ->get(route('ranking.guild', [$class, $team]))
            ->assertOk()
            ->assertSee('Pendências da guilda')
            ->assertSee('Bob ainda não concluiu a missão Prova.');
    }

    public function test_guest_guild_page_hides_pending_member_alerts(): void
    {
        [$teacher, $class, $activity, $alice, $bob] = $this->makeIndividualSetup();
        $team = Team::query()->create(['class_id' => $class->id, 'name' => 'Águias', 'emblem' => 'owl']);
        $team->members()->attach([$alice->id, $bob->id]);

        $this->actingAs($teacher)
            ->post(route('teacher.grades.store', $class), [
                'activity_id' => $activity->id,
                'scores' => [$alice->id => 80],
            ]);

        $this->app['auth']->forgetGuards();

        $this->get(route('ranking.guild', [$class, $team]))
            ->assertOk()
            ->assertDontSee('Pendências da guilda')
            ->assertDontSee('Bob ainda não concluiu a missão Prova.');
    }

    public function test_escapes_member_name_in_the_guild_pending_alert(): void
    {
        [$teacher, $class, $activity, $alice] = $this->makeIndividualSetup();
        $bob = $this->enrollStudent($class, "<script>alert('xss')</script>");
        $team = Team::query()->create(['class_id' => $class->id, 'name' => 'Águias', 'emblem' => 'owl']);
        $team->members()->attach([$alice->id, $bob->id]);

        $this->actingAs($teacher)
            ->post(route('teacher.grades.store', $class), [
                'activity_id' => $activity->id,
                'scores' => [$alice->id => 80],
            ]);

        $html = $this->actingAs($alice)
            ->get(route('student.dashboard'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringNotContainsString("<script>alert('xss')</script>", $html);
    }

    /**
     * @return array{0: User, 1: SchoolClass, 2: Activity, 3: User, 4: User}
     */
    private function makeIndividualSetup(string $activityName = 'Prova'): array
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $class = $this->createClassForTeacher($teacher, ['name' => 'Turma Missões']);
        $activity = Activity::query()->create([
            'class_id' => $class->id,
            'name' => $activityName,
            'type' => 'individual',
            'max_score' => 100,
            'weight' => 1,
        ]);
        $alice = $this->enrollStudent($class, 'Alice');
        $bob = $this->enrollStudent($class, 'Bob');

        return [$teacher, $class, $activity, $alice, $bob];
    }

    private function enrollStudent(SchoolClass $class, string $name): User
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
