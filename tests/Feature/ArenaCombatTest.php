<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\EnrollmentCosmetic;
use App\Models\SchoolClass;
use App\Models\ShopItem;
use App\Models\Team;
use App\Models\User;
use App\Services\ArenaCombatService;
use App\Services\GameLoopService;
use App\Services\GradeCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArenaCombatTest extends TestCase
{
    use RefreshDatabase;

    public function test_higher_academic_grade_raises_combat_attack(): void
    {
        [$class, $strong, $weak] = $this->readyPair();
        $this->gradeStudent($class, $strong, 90);
        $this->gradeStudent($class, $weak, 40);

        $combat = app(ArenaCombatService::class);
        $strongFighter = $combat->buildFighter($strong, $class);
        $weakFighter = $combat->buildFighter($weak, $class);

        $this->assertGreaterThan($weakFighter['atk'], $strongFighter['atk']);
        $this->assertGreaterThan($weakFighter['breakdown']['grade'], $strongFighter['breakdown']['grade']);
        $this->assertSame(0.0, $strongFighter['breakdown']['attendance_bonus']);
        $this->assertSame(0.0, $strongFighter['breakdown']['team_bonus']);
    }

    public function test_character_class_does_not_change_combat_stats(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $class = $this->createClassForTeacher($teacher, ['arena_open' => true]);
        $warrior = $this->enrollFighter($class, 'Ana Souza', 'guerreiro');
        $mage = $this->enrollFighter($class, 'Bruno Lima', 'mago');

        $this->gradeStudent($class, $warrior, 70);
        $this->gradeStudent($class, $mage, 70);

        $combat = app(ArenaCombatService::class);
        $warriorFighter = $combat->buildFighter($warrior, $class);
        $mageFighter = $combat->buildFighter($mage, $class);

        $this->assertSame($warriorFighter['max_hp'], $mageFighter['max_hp']);
        $this->assertSame($warriorFighter['atk'], $mageFighter['atk']);
        $this->assertSame($warriorFighter['def'], $mageFighter['def']);
        $this->assertSame($warriorFighter['spd'], $mageFighter['spd']);
        $this->assertSame($warriorFighter['heal_chance'], $mageFighter['heal_chance']);
        $this->assertSame($warriorFighter['power'], $mageFighter['power']);
        $this->assertSame('Guerreiro', $warriorFighter['class']);
        $this->assertSame('Mago', $mageFighter['class']);
    }

    public function test_guild_grade_raises_combat_power_without_entering_academic_average(): void
    {
        [$class, $strongGuild, $weakGuild] = $this->readyPair();
        $this->gradeStudent($class, $strongGuild, 70);
        $this->gradeStudent($class, $weakGuild, 70);
        $this->gradeTeam($class, $this->assignToTeam($class, $strongGuild, 'Dragões'), 90);
        $this->gradeTeam($class, $this->assignToTeam($class, $weakGuild, 'Lobos'), 40);

        $grades = app(GradeCalculator::class);
        $academicStrong = $grades->combatAcademicAverage($strongGuild, $class);
        $academicWeak = $grades->combatAcademicAverage($weakGuild, $class);

        $this->assertSame($academicStrong, $academicWeak);
        $this->assertSame(90.0, $grades->combatTeamScore($strongGuild, $class));
        $this->assertSame(40.0, $grades->combatTeamScore($weakGuild, $class));

        $combat = app(ArenaCombatService::class);
        $strongFighter = $combat->buildFighter($strongGuild, $class);
        $weakFighter = $combat->buildFighter($weakGuild, $class);

        $this->assertGreaterThan($weakFighter['power'], $strongFighter['power']);
        $this->assertGreaterThan($weakFighter['atk'], $strongFighter['atk']);
        $this->assertSame(90.0, $strongFighter['breakdown']['team']);
        $this->assertSame(40.0, $weakFighter['breakdown']['team']);
        $this->assertGreaterThan($weakFighter['breakdown']['team_bonus'], $strongFighter['breakdown']['team_bonus']);
    }

    public function test_guild_without_team_activity_does_not_raise_combat_power(): void
    {
        [$class, $guilded, $solo] = $this->readyPair();
        $this->gradeStudent($class, $guilded, 70);
        $this->gradeStudent($class, $solo, 70);
        $this->assignToTeam($class, $guilded, 'Dragões');

        $combat = app(ArenaCombatService::class);
        $guildedFighter = $combat->buildFighter($guilded, $class);
        $soloFighter = $combat->buildFighter($solo, $class);

        $this->assertSame($soloFighter['power'], $guildedFighter['power']);
        $this->assertSame(0.0, $guildedFighter['breakdown']['team_bonus']);
    }

    public function test_attendance_raises_combat_power_without_entering_academic_average(): void
    {
        [$class, $present, $absent] = $this->readyPair();
        $this->gradeStudent($class, $present, 70);
        $this->gradeStudent($class, $absent, 70);
        $this->recordAttendance($class, [
            $present->id => AttendanceRecord::STATUS_PRESENT,
            $absent->id => AttendanceRecord::STATUS_ABSENT,
        ]);

        $grades = app(GradeCalculator::class);
        $academicPresent = $grades->combatAcademicAverage($present, $class);
        $academicAbsent = $grades->combatAcademicAverage($absent, $class);

        $this->assertSame($academicPresent, $academicAbsent);
        $this->assertGreaterThan($academicPresent, $grades->studentAverage($present, $class));
        $this->assertLessThan($academicAbsent, $grades->studentAverage($absent, $class));

        $combat = app(ArenaCombatService::class);
        $presentFighter = $combat->buildFighter($present, $class);
        $absentFighter = $combat->buildFighter($absent, $class);

        $this->assertGreaterThan($absentFighter['power'], $presentFighter['power']);
        $this->assertSame(100.0, $presentFighter['breakdown']['attendance']);
        $this->assertSame(0.0, $absentFighter['breakdown']['attendance']);
        $this->assertGreaterThan(0.0, $presentFighter['breakdown']['attendance_bonus']);
        $this->assertSame(0.0, $absentFighter['breakdown']['attendance_bonus']);
    }

    public function test_equipped_cosmetic_raises_combat_power(): void
    {
        [$class, $student] = $this->readyStudent();
        $combat = app(ArenaCombatService::class);

        $before = $combat->buildFighter($student, $class);

        $enrollment = $student->enrollmentIn($class);
        EnrollmentCosmetic::query()->create([
            'enrollment_id' => $enrollment->id,
            'item_key' => 'frame_gold',
        ]);
        $enrollment->update(['equipped_frame' => 'frame_gold']);

        $after = $combat->buildFighter($student->fresh(), $class);

        $this->assertGreaterThan($before['power'], $after['power']);
        $this->assertGreaterThan($before['atk'], $after['atk']);
        $this->assertSame('Anel de Ouro', $after['breakdown']['gear_items'][0]['name']);
        $this->assertSame(0.03, $after['breakdown']['gear_bonus']);
    }

    public function test_equipped_custom_item_uses_configured_combat_bonus(): void
    {
        [$class, $student] = $this->readyStudent();
        $item = ShopItem::factory()->forClass($class->id)->create([
            'name' => 'Capa da Turma',
            'rarity' => 'common',
            'combat_bonus' => 0.05,
        ]);
        $enrollment = $student->enrollmentIn($class);
        EnrollmentCosmetic::query()->create([
            'enrollment_id' => $enrollment->id,
            'item_key' => $item->item_key,
        ]);
        $enrollment->update(['equipped_accessory' => $item->item_key]);

        $fighter = app(ArenaCombatService::class)->buildFighter($student->fresh(), $class);

        $this->assertSame(0.05, $fighter['breakdown']['gear_bonus']);
        $this->assertSame('Capa da Turma', $fighter['breakdown']['gear_items'][0]['name']);
    }

    public function test_owned_cosmetic_without_equip_does_not_raise_combat_power(): void
    {
        [$class, $student] = $this->readyStudent();
        $combat = app(ArenaCombatService::class);
        $before = $combat->buildFighter($student, $class);

        EnrollmentCosmetic::query()->create([
            'enrollment_id' => $student->enrollmentIn($class)->id,
            'item_key' => 'frame_gold',
        ]);

        $after = $combat->buildFighter($student->fresh(), $class);

        $this->assertSame($before['power'], $after['power']);
        $this->assertSame(0.0, $after['breakdown']['gear_bonus']);
        $this->assertSame([], $after['breakdown']['gear_items']);
    }

    public function test_equipped_gear_bonus_is_capped_at_ten_percent(): void
    {
        [$class, $student] = $this->readyStudent();
        $enrollment = $student->enrollmentIn($class);

        foreach (['frame_rune', 'acc_wings', 'title_legend', 'aura_storm'] as $itemKey) {
            EnrollmentCosmetic::query()->create([
                'enrollment_id' => $enrollment->id,
                'item_key' => $itemKey,
            ]);
        }

        $enrollment->update([
            'equipped_frame' => 'frame_rune',
            'equipped_accessory' => 'acc_wings',
            'equipped_title' => 'title_legend',
            'equipped_aura' => 'aura_storm',
        ]);

        $fighter = app(ArenaCombatService::class)->buildFighter($student->fresh(), $class);

        $this->assertSame(0.1, $fighter['breakdown']['gear_bonus']);
        $this->assertCount(4, $fighter['breakdown']['gear_items']);
    }

    public function test_resolve_includes_winner_reason_and_breakdown(): void
    {
        [$class, $challenger, $opponent] = $this->readyPair();

        $result = app(ArenaCombatService::class)->resolve($challenger, $opponent, $class, 99_001);

        $this->assertContains($result['winner_reason'], ['ko', 'hp', 'spd', 'spd_tie']);
        $this->assertArrayHasKey('breakdown', $result['fighters']['challenger']);
        $this->assertArrayHasKey('grade', $result['fighters']['challenger']['breakdown']);
        $this->assertArrayHasKey('luck', $result['fighters']['challenger']['breakdown']);
        $this->assertGreaterThanOrEqual(-0.12, $result['fighters']['challenger']['breakdown']['luck']);
        $this->assertLessThanOrEqual(0.12, $result['fighters']['challenger']['breakdown']['luck']);
    }

    public function test_arena_page_explains_how_the_winner_is_decided_without_revealing_the_math(): void
    {
        [$class, $student] = $this->readyStudent();
        $class->update(['arena_open' => true]);

        $this->actingAs($student)
            ->get(route('student.arena.index'))
            ->assertOk()
            ->assertSee('Como o vencedor é definido')
            ->assertSee('fator de sorte')
            ->assertSee('ninguém chega sabendo quem vai ganhar')
            ->assertDontSee('até +30%')
            ->assertDontSee('HP 100');
    }

    /**
     * @return array{0: SchoolClass, 1: User, 2: User}
     */
    private function readyPair(): array
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $class = $this->createClassForTeacher($teacher, ['arena_open' => true]);
        $challenger = $this->enrollFighter($class, 'Ana Souza', 'guerreiro');
        $opponent = $this->enrollFighter($class, 'Bruno Lima', 'guerreiro');

        return [$class, $challenger, $opponent];
    }

    /**
     * @return array{0: SchoolClass, 1: User}
     */
    private function readyStudent(): array
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $class = $this->createClassForTeacher($teacher);
        $student = $this->enrollFighter($class, 'Ana Souza');

        return [$class, $student];
    }

    private function enrollFighter(SchoolClass $class, string $name, string $characterClass = 'guerreiro'): User
    {
        $student = User::factory()->create([
            'name' => $name,
            'role' => 'student',
            'character_class' => $characterClass,
            'must_change_password' => false,
            'character_name' => 'Heroi '.$name,
            'character_avatar' => 'lobo',
            'character_approval_status' => 'approved',
        ]);

        $class->students()->attach($student->id, [
            'ranking_visible' => true,
            'xp' => 0,
            'glory' => 0,
            'relics' => 0,
            'arena_wins' => 0,
            'arena_losses' => 0,
            'behavior_score' => 100,
        ]);

        return $student->fresh();
    }

    private function assignToTeam(SchoolClass $class, User $student, string $name): Team
    {
        $team = $class->teams()->create([
            'name' => $name,
            'color' => '#7c3aed',
            'emblem' => 'dragon',
        ]);
        $team->members()->sync([$student->id]);

        return $team;
    }

    private function gradeTeam(SchoolClass $class, Team $team, float $score): void
    {
        $activity = Activity::query()->firstOrCreate(
            ['class_id' => $class->id, 'name' => 'Missão da Guilda'],
            [
                'type' => 'team',
                'max_score' => 100,
                'weight' => 1,
            ],
        );

        app(GameLoopService::class)->recordActivityGrade(
            $class,
            $activity,
            $score,
            $class->teacher,
            team: $team,
        );
    }

    private function gradeStudent(SchoolClass $class, User $student, float $score): void
    {
        $activity = Activity::query()->firstOrCreate(
            ['class_id' => $class->id, 'name' => 'Prova Arena'],
            [
                'type' => 'individual',
                'max_score' => 100,
                'weight' => 1,
            ],
        );

        $teacher = $class->teacher;
        app(GameLoopService::class)->recordActivityGrade(
            $class,
            $activity,
            $score,
            $teacher,
            student: $student,
        );
    }

    /**
     * @param  array<int, string>  $statuses
     */
    private function recordAttendance(SchoolClass $class, array $statuses): void
    {
        $session = AttendanceSession::query()->create([
            'class_id' => $class->id,
            'held_on' => '2026-09-08',
            'created_by' => $class->teacher_id,
        ]);

        foreach ($statuses as $studentId => $status) {
            AttendanceRecord::query()->create([
                'attendance_session_id' => $session->id,
                'student_id' => $studentId,
                'status' => $status,
            ]);
        }
    }
}
