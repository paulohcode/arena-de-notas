<?php

namespace Tests\Feature;

use App\Models\SchoolClass;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RankingVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_does_not_see_hidden_student_on_public_ranking(): void
    {
        [$class, $hidden] = $this->makeClassWithHiddenStudent();

        $this->get(route('ranking.show', $class))
            ->assertOk()
            ->assertDontSee($hidden->name);
    }

    public function test_teacher_from_another_area_does_not_see_hidden_student(): void
    {
        [$class, $hidden] = $this->makeClassWithHiddenStudent();
        $outsider = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $this->createAreaForTeacher($outsider, ['slug' => 'outro-reino-vis']);

        $this->actingAs($outsider)
            ->get(route('ranking.show', $class))
            ->assertOk()
            ->assertDontSee($hidden->name);
    }

    public function test_teacher_from_same_area_sees_hidden_student(): void
    {
        [$class, $hidden, $owner] = $this->makeClassWithHiddenStudent();
        $colleague = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $colleague->areas()->syncWithoutDetaching([(int) $class->area_id]);

        $this->actingAs($owner)
            ->get(route('ranking.show', $class))
            ->assertOk()
            ->assertSee($hidden->name);

        $this->actingAs($colleague)
            ->get(route('ranking.show', $class))
            ->assertOk()
            ->assertSee($hidden->name);
    }

    public function test_live_ranking_hides_student_from_other_area_teacher(): void
    {
        [$class, $hidden] = $this->makeClassWithHiddenStudent();
        $outsider = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $this->createAreaForTeacher($outsider, ['slug' => 'reino-live']);

        $this->actingAs($outsider)
            ->getJson(route('ranking.live', $class))
            ->assertOk()
            ->assertJsonMissing(['name' => $hidden->name]);
    }

    public function test_guild_hides_member_name_from_other_area_teacher(): void
    {
        [$class, $hidden] = $this->makeClassWithHiddenStudent();
        $team = Team::query()->create([
            'class_id' => $class->id,
            'name' => 'Guilda Oculta',
            'emblem' => 'owl',
        ]);
        $team->members()->attach($hidden->id);

        $outsider = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $this->createAreaForTeacher($outsider, ['slug' => 'reino-guild']);

        $this->actingAs($outsider)
            ->get(route('ranking.guild', [$class, $team]))
            ->assertOk()
            ->assertDontSee($hidden->name)
            ->assertSee('Membro oculto');
    }

    /**
     * @return array{0: SchoolClass, 1: User, 2: User}
     */
    private function makeClassWithHiddenStudent(): array
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $class = $this->createClassForTeacher($teacher, ['name' => 'Turma Privada']);

        $hidden = User::factory()->create([
            'role' => 'student',
            'name' => 'Aluno Secreto Vis',
            'must_change_password' => false,
        ]);
        $visible = User::factory()->create([
            'role' => 'student',
            'name' => 'Aluno Publico Vis',
            'must_change_password' => false,
        ]);

        $class->students()->attach($hidden->id, ['ranking_visible' => false, 'xp' => 0]);
        $class->students()->attach($visible->id, ['ranking_visible' => true, 'xp' => 0]);

        return [$class, $hidden, $teacher];
    }
}
