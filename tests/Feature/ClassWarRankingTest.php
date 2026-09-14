<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Area;
use App\Models\Badge;
use App\Models\LedgerEntry;
use App\Models\SchoolClass;
use App\Models\Season;
use App\Models\User;
use App\Services\SeasonService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClassWarRankingTest extends TestCase
{
    use RefreshDatabase;

    public function test_class_war_score_matches_academic_average_on_a_hundred_point_scale(): void
    {
        $class = $this->classWithGradedStudent(80, xp: 0);

        $row = app(SeasonService::class)->scoreClass($class);

        $this->assertSame(90.0, $row['avg_individual']);
        $this->assertSame(60.0, $row['level_score']);
        $this->assertSame(0.0, $row['badge_score']);
        $this->assertSame(90.0, $row['score']);
    }

    public function test_missing_guilds_do_not_count_as_zero_on_the_class_score(): void
    {
        $class = $this->classWithGradedStudent(80, xp: 0);
        $class->teams()->create([
            'name' => 'Guilda Vazia',
            'color' => '#7c3aed',
            'emblem' => 'dragon',
        ]);

        $row = app(SeasonService::class)->scoreClass($class->fresh());

        $this->assertSame(90.0, $row['score']);
    }

    public function test_master_level_and_badges_do_not_cap_the_class_score_to_one_hundred(): void
    {
        $class = $this->classWithGradedStudent(80, xp: 1000);
        $student = $class->students()->first();
        for ($i = 1; $i <= 5; $i++) {
            $badge = Badge::query()->create([
                'slug' => 'medalha-'.$i,
                'name' => 'Medalha '.$i,
                'description' => 'Medalha de coleção.',
                'icon' => '🥇',
            ]);
            $student->badges()->attach($badge->id, ['class_id' => $class->id]);
        }

        $row = app(SeasonService::class)->scoreClass($class->fresh());

        $this->assertSame(100.0, $row['level_score']);
        $this->assertSame(100.0, $row['badge_score']);
        $this->assertSame(90.0, $row['score']);
    }

    public function test_one_badge_does_not_change_the_class_average(): void
    {
        $class = $this->classWithGradedStudent(80, xp: 0);
        $student = $class->students()->first();
        $badge = Badge::query()->create([
            'slug' => 'podio-guerra',
            'name' => 'Pódio',
            'description' => 'Entrou no top 3.',
            'icon' => '🏆',
        ]);
        $student->badges()->attach($badge->id, ['class_id' => $class->id]);

        $row = app(SeasonService::class)->scoreClass($class->fresh());

        $this->assertSame(20.0, $row['badge_score']);
        $this->assertSame(90.0, $row['score']);
    }

    public function test_class_war_score_never_exceeds_one_hundred(): void
    {
        $class = $this->classWithGradedStudent(100, xp: 0);

        $row = app(SeasonService::class)->scoreClass($class);

        $this->assertSame(100.0, $row['score']);
    }

    public function test_team_activity_enters_the_class_score_on_the_hundred_point_scale(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $class = $this->createClassForTeacher($teacher, ['name' => 'Turma Guildas']);
        $student = User::factory()->create(['role' => 'student', 'name' => 'Carla']);
        $class->students()->attach($student->id, [
            'ranking_visible' => true,
            'xp' => 0,
            'behavior_score' => 100,
        ]);

        $individual = Activity::query()->create([
            'class_id' => $class->id,
            'name' => 'Prova',
            'type' => 'individual',
            'max_score' => 100,
            'weight' => 1,
        ]);
        $teamActivity = Activity::query()->create([
            'class_id' => $class->id,
            'name' => 'Trabalho em equipe',
            'type' => 'team',
            'max_score' => 100,
            'weight' => 1,
        ]);
        $team = $class->teams()->create([
            'name' => 'Guilda Fênix',
            'color' => '#7c3aed',
            'emblem' => 'fire',
        ]);
        $team->members()->sync([$student->id]);

        $this->grade($class, $teacher, $individual, student: $student, score: 100);
        $this->grade($class, $teacher, $teamActivity, teamId: $team->id, score: 40);

        $row = app(SeasonService::class)->scoreClass($class->fresh(['students', 'teams.members']));

        $this->assertSame(80.0, $row['score']);
    }

    public function test_two_classes_keep_distinct_averages_instead_of_both_showing_one_hundred(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $area = $this->createAreaForTeacher($teacher, ['slug' => 'reino-duas-turmas']);
        $this->classWithGradedStudent(80, xp: 1000, teacher: $teacher, area: $area, name: 'Turma Alta');
        $this->classWithGradedStudent(70, xp: 1000, teacher: $teacher, area: $area, name: 'Turma Baixa');

        $ranking = app(SeasonService::class)->areaClassRanking($area);

        $this->assertSame('Turma Alta', $ranking[0]['class']->name);
        $this->assertSame(90.0, $ranking[0]['score']);
        $this->assertSame('Turma Baixa', $ranking[1]['class']->name);
        $this->assertSame(85.0, $ranking[1]['score']);
    }

    public function test_higher_level_breaks_a_tie_on_class_average(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $area = $this->createAreaForTeacher($teacher, ['slug' => 'reino-empate']);
        $this->classWithGradedStudent(80, xp: 0, teacher: $teacher, area: $area, name: 'Turma Iniciante');
        $this->classWithGradedStudent(80, xp: 1000, teacher: $teacher, area: $area, name: 'Turma Mestre');

        $ranking = app(SeasonService::class)->areaClassRanking($area);

        $this->assertSame('Turma Mestre', $ranking[0]['class']->name);
        $this->assertSame(90.0, $ranking[0]['score']);
        $this->assertSame('Turma Iniciante', $ranking[1]['class']->name);
        $this->assertSame(90.0, $ranking[1]['score']);
    }

    public function test_area_page_shows_the_class_score_out_of_one_hundred(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $area = $this->createAreaForTeacher($teacher, ['name' => 'Reino Cem', 'slug' => 'reino-cem']);
        $class = $this->classWithGradedStudent(80, xp: 0, teacher: $teacher, area: $area, name: 'Turma Nota Cem');

        $this->get(route('areas.show', $area))
            ->assertOk()
            ->assertSee('Ranking das turmas')
            ->assertSee('Turma Nota Cem')
            ->assertSee('90.0', false)
            ->assertSee('/ 100', false)
            ->assertSee('nota da turma');
    }

    public function test_season_page_uses_the_same_hundred_point_class_score(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $area = $this->createAreaForTeacher($teacher, ['slug' => 'reino-temp']);
        $class = $this->classWithGradedStudent(80, xp: 0, teacher: $teacher, area: $area, name: 'Turma da Temporada');
        $season = Season::query()->create([
            'area_id' => $area->id,
            'name' => 'Trimestre 1',
            'created_by' => $teacher->id,
        ]);
        $season->classes()->attach($class->id);

        $this->get(route('areas.seasons.show', [$area, $season]))
            ->assertOk()
            ->assertSee('Turma da Temporada')
            ->assertSee('90.0', false)
            ->assertSee('/ 100', false);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function classWithGradedStudent(
        float $activityScore,
        int $xp,
        ?User $teacher = null,
        ?Area $area = null,
        string $name = 'Turma Guerra',
    ): SchoolClass {
        $teacher ??= User::factory()->create(['role' => 'teacher']);
        $overrides = ['name' => $name];
        if ($area !== null) {
            $overrides['area'] = $area;
        }
        $class = $this->createClassForTeacher($teacher, $overrides);
        $student = User::factory()->create(['role' => 'student', 'name' => 'Aluno Guerra']);
        $class->students()->attach($student->id, [
            'ranking_visible' => true,
            'xp' => $xp,
            'behavior_score' => 100,
        ]);

        $activity = Activity::query()->create([
            'class_id' => $class->id,
            'name' => 'Prova',
            'type' => 'individual',
            'max_score' => 100,
            'weight' => 1,
        ]);
        $this->grade($class, $teacher, $activity, student: $student, score: $activityScore);

        return $class->fresh(['students', 'teams.members']);
    }

    private function grade(
        SchoolClass $class,
        User $teacher,
        Activity $activity,
        float $score,
        ?User $student = null,
        ?int $teamId = null,
    ): void {
        LedgerEntry::query()->create([
            'class_id' => $class->id,
            'activity_id' => $activity->id,
            'student_id' => $student?->id,
            'team_id' => $teamId,
            'created_by' => $teacher->id,
            'type' => 'activity',
            'raw_score' => $score,
            'delta' => $score,
            'reason' => $activity->name,
        ]);
    }
}
