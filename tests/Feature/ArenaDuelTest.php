<?php

namespace Tests\Feature;

use App\Models\Duel;
use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Models\User;
use App\Notifications\GameAlert;
use App\Services\ArenaCombatService;
use App\Services\DuelService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ArenaDuelTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_challenge_redirects_to_login(): void
    {
        $this->post(route('student.arena.challenge'), ['opponent_id' => 1])
            ->assertRedirectToRoute('login');
    }

    public function test_teacher_can_open_and_close_arena(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $class = $this->createClassForTeacher($teacher);

        $this->actingAs($teacher)
            ->post(route('teacher.arena.open', $class))
            ->assertRedirect(route('teacher.classes.show', ['schoolClass' => $class, 'tab' => 'arena']));

        $this->assertTrue($class->fresh()->isArenaOpen());

        $this->actingAs($teacher)
            ->post(route('teacher.arena.close', $class))
            ->assertRedirect(route('teacher.classes.show', ['schoolClass' => $class, 'tab' => 'arena']));

        $this->assertFalse($class->fresh()->isArenaOpen());
    }

    public function test_student_cannot_challenge_while_arena_is_closed(): void
    {
        [$class, $challenger, $opponent] = $this->readyPair();

        $this->actingAs($challenger)
            ->from(route('student.arena.index'))
            ->post(route('student.arena.challenge'), ['opponent_id' => $opponent->id])
            ->assertRedirect()
            ->assertSessionHasErrors(['arena']);
    }

    public function test_full_duel_flow_awards_glory_without_changing_xp_or_average(): void
    {
        Notification::fake();

        [$class, $challenger, $opponent] = $this->readyPair(arenaOpen: true);

        $challengerEnrollment = $challenger->enrollmentIn($class);
        $challengerEnrollment->update(['xp' => 250]);
        $opponentEnrollment = $opponent->enrollmentIn($class);
        $opponentEnrollment->update(['xp' => 100]);

        $xpBefore = [
            $challenger->id => 250,
            $opponent->id => 100,
        ];

        $this->actingAs($challenger)
            ->post(route('student.arena.challenge'), ['opponent_id' => $opponent->id])
            ->assertRedirect(route('student.arena.show', Duel::query()->firstOrFail()))
            ->assertSessionHas('success');

        $duel = Duel::query()->firstOrFail();
        $this->assertSame(Duel::STATUS_PENDING, $duel->status);

        Notification::assertSentTo($opponent, GameAlert::class);

        $this->actingAs($opponent)
            ->post(route('student.arena.accept', $duel))
            ->assertRedirect(route('student.arena.show', $duel).'?replay=1');

        $duel->refresh();
        $this->assertSame(Duel::STATUS_RESOLVED, $duel->status);
        $this->assertNotNull($duel->winner_id);
        $this->assertNotNull($duel->seed);
        $this->assertIsArray($duel->log);
        $this->assertArrayHasKey('turns', $duel->log);

        $winnerEnrollment = Enrollment::query()
            ->where('class_id', $class->id)
            ->where('student_id', $duel->winner_id)
            ->firstOrFail();
        $loserId = $duel->winner_id === $challenger->id ? $opponent->id : $challenger->id;
        $loserEnrollment = Enrollment::query()
            ->where('class_id', $class->id)
            ->where('student_id', $loserId)
            ->firstOrFail();

        $this->assertSame(Duel::GLORY_WIN, $winnerEnrollment->glory);
        $this->assertSame(Duel::GLORY_WIN, $winnerEnrollment->relics);
        $this->assertSame(1, $winnerEnrollment->arena_wins);
        $this->assertSame(Duel::GLORY_LOSS, $loserEnrollment->glory);
        $this->assertSame(Duel::GLORY_LOSS, $loserEnrollment->relics);
        $this->assertSame(1, $loserEnrollment->arena_losses);

        foreach ([$challenger->id, $opponent->id] as $studentId) {
            $enrollment = Enrollment::query()
                ->where('class_id', $class->id)
                ->where('student_id', $studentId)
                ->firstOrFail();
            $this->assertSame($xpBefore[$studentId], $enrollment->xp);
        }
    }

    public function test_decline_does_not_award_glory(): void
    {
        Notification::fake();

        [$class, $challenger, $opponent] = $this->readyPair(arenaOpen: true);

        $this->actingAs($challenger)
            ->post(route('student.arena.challenge'), ['opponent_id' => $opponent->id]);

        $duel = Duel::query()->firstOrFail();

        $this->actingAs($opponent)
            ->post(route('student.arena.decline', $duel))
            ->assertRedirect(route('student.arena.index'));

        $duel->refresh();
        $this->assertSame(Duel::STATUS_DECLINED, $duel->status);

        $this->assertSame(0, (int) $challenger->enrollmentIn($class)->fresh()->glory);
        $this->assertSame(0, (int) $opponent->enrollmentIn($class)->fresh()->glory);
        $this->assertSame(0, (int) $challenger->enrollmentIn($class)->fresh()->relics);
        $this->assertSame(0, (int) $opponent->enrollmentIn($class)->fresh()->relics);

        Notification::assertSentTo($challenger, GameAlert::class);
    }

    public function test_challenger_waiting_room_polls_until_resolved(): void
    {
        [$class, $challenger, $opponent] = $this->readyPair(arenaOpen: true);

        $this->actingAs($challenger)
            ->post(route('student.arena.challenge'), ['opponent_id' => $opponent->id])
            ->assertRedirect();

        $duel = Duel::query()->firstOrFail();

        $this->actingAs($challenger)
            ->get(route('student.arena.show', $duel))
            ->assertOk()
            ->assertSee('Aguardando o oponente')
            ->assertSee('Escutando a arena');

        $this->actingAs($challenger)
            ->getJson(route('student.arena.status', $duel))
            ->assertOk()
            ->assertJson([
                'status' => Duel::STATUS_PENDING,
                'redirect' => null,
            ]);

        $this->actingAs($opponent)
            ->post(route('student.arena.accept', $duel))
            ->assertRedirect(route('student.arena.show', $duel).'?replay=1');

        $this->actingAs($challenger)
            ->getJson(route('student.arena.status', $duel))
            ->assertOk()
            ->assertJson([
                'status' => Duel::STATUS_RESOLVED,
                'redirect' => route('student.arena.show', $duel, absolute: false).'?replay=1',
            ]);

        $this->actingAs($challenger)
            ->get(route('student.arena.show', $duel))
            ->assertOk()
            ->assertSee('Campo de combate')
            ->assertSee('Histórico de danos')
            ->assertSee('Vencedor da arena')
            ->assertSee('Som da arena')
            ->assertSee('#ea580c')
            ->assertSee('#7c3aed')
            ->assertDontSee('Poder do desafiante')
            ->assertDontSee('até +30%');
    }

    public function test_pending_challenges_endpoint_lists_incoming_duels(): void
    {
        [$class, $challenger, $opponent] = $this->readyPair(arenaOpen: true);

        $this->actingAs($challenger)
            ->post(route('student.arena.challenge'), ['opponent_id' => $opponent->id]);

        $duel = Duel::query()->firstOrFail();

        $this->actingAs($opponent)
            ->getJson(route('student.arena.pending'))
            ->assertOk()
            ->assertJsonPath('challenges.0.duel_id', $duel->id)
            ->assertJsonPath('challenges.0.accept_url', route('student.arena.accept', $duel, absolute: false))
            ->assertJsonPath('challenges.0.decline_url', route('student.arena.decline', $duel, absolute: false));
    }

    public function test_json_accept_from_modal_goes_to_battle(): void
    {
        [$class, $challenger, $opponent] = $this->readyPair(arenaOpen: true);

        $this->actingAs($challenger)
            ->post(route('student.arena.challenge'), ['opponent_id' => $opponent->id]);

        $duel = Duel::query()->firstOrFail();

        $this->actingAs($opponent)
            ->postJson(route('student.arena.accept', $duel))
            ->assertOk()
            ->assertJson([
                'ok' => true,
                'redirect' => route('student.arena.show', $duel, absolute: false).'?replay=1',
            ]);

        $this->assertSame(Duel::STATUS_RESOLVED, $duel->fresh()->status);
    }

    public function test_json_decline_from_modal_keeps_user_on_page(): void
    {
        [$class, $challenger, $opponent] = $this->readyPair(arenaOpen: true);

        $this->actingAs($challenger)
            ->post(route('student.arena.challenge'), ['opponent_id' => $opponent->id]);

        $duel = Duel::query()->firstOrFail();

        $this->actingAs($opponent)
            ->postJson(route('student.arena.decline', $duel))
            ->assertOk()
            ->assertJson([
                'ok' => true,
                'redirect' => null,
            ]);

        $this->assertSame(Duel::STATUS_DECLINED, $duel->fresh()->status);
    }

    public function test_cannot_challenge_same_opponent_twice_in_one_day(): void
    {
        [$class, $challenger, $opponent] = $this->readyPair(arenaOpen: true);

        $this->actingAs($challenger)
            ->post(route('student.arena.challenge'), ['opponent_id' => $opponent->id])
            ->assertRedirect();

        $this->actingAs($challenger)
            ->get(route('student.arena.index'))
            ->assertOk()
            ->assertSee('Já existe um desafio pendente com Heroi Bruno Lima');

        $this->actingAs($challenger)
            ->from(route('student.arena.index'))
            ->post(route('student.arena.challenge'), ['opponent_id' => $opponent->id])
            ->assertRedirect(route('student.arena.index'))
            ->assertSessionHasErrors([
                'opponent_id' => 'Já existe um desafio pendente com Heroi Bruno Lima. Aguarde a resposta.',
            ]);

        $this->actingAs($challenger)
            ->get(route('student.arena.index'))
            ->assertOk()
            ->assertSee('Já existe um desafio pendente com Heroi Bruno Lima. Aguarde a resposta.');
    }

    public function test_cannot_challenge_same_opponent_again_after_dueling_today(): void
    {
        [$class, $challenger, $opponent] = $this->readyPair(arenaOpen: true);

        $this->actingAs($challenger)
            ->post(route('student.arena.challenge'), ['opponent_id' => $opponent->id]);

        $duel = Duel::query()->firstOrFail();

        $this->actingAs($opponent)
            ->post(route('student.arena.accept', $duel))
            ->assertRedirect();

        $this->travel(Duel::CHALLENGE_COOLDOWN_HOURS + 1)->hours();

        $this->actingAs($challenger)
            ->from(route('student.arena.index'))
            ->post(route('student.arena.challenge'), ['opponent_id' => $opponent->id])
            ->assertRedirect(route('student.arena.index'))
            ->assertSessionHasErrors([
                'opponent_id' => 'Você já desafiou Heroi Bruno Lima hoje. Só pode duelar de novo amanhã.',
            ]);

        $this->actingAs($challenger)
            ->get(route('student.arena.index'))
            ->assertOk()
            ->assertSee('Você já desafiou Heroi Bruno Lima hoje. Só pode duelar de novo amanhã.')
            ->assertDontSee('>Desafiar</button>', false);
    }

    public function test_challenge_cooldown_blocks_a_second_challenge_too_soon(): void
    {
        [$class, $challenger, $opponent] = $this->readyPair(arenaOpen: true);
        $third = $this->enrollStudent($class, 'Carla Dias', approvedPersona: true, characterClass: 'arqueiro');

        $this->actingAs($challenger)
            ->post(route('student.arena.challenge'), ['opponent_id' => $opponent->id])
            ->assertRedirect();

        $this->actingAs($challenger)
            ->from(route('student.arena.index'))
            ->post(route('student.arena.challenge'), ['opponent_id' => $third->id])
            ->assertRedirect(route('student.arena.index'))
            ->assertSessionHasErrors([
                'opponent_id' => 'Aguarde '.Duel::CHALLENGE_COOLDOWN_HOURS.' horas entre um desafio e outro.',
            ]);
    }

    public function test_daily_resolved_limit_blocks_further_accepts(): void
    {
        [$class, $challenger, $opponent] = $this->readyPair(arenaOpen: true);
        $third = $this->enrollStudent($class, 'Carla Dias', approvedPersona: true, characterClass: 'arqueiro');
        $fourth = $this->enrollStudent($class, 'Diego Rocha', approvedPersona: true, characterClass: 'paladino');

        foreach ([$opponent, $third, $fourth] as $index => $peer) {
            Duel::query()->create([
                'class_id' => $class->id,
                'challenger_id' => $peer->id,
                'opponent_id' => $challenger->id,
                'status' => Duel::STATUS_RESOLVED,
                'seed' => 1000 + $index,
                'log' => ['turns' => [], 'fighters' => [], 'winner_id' => $challenger->id],
                'winner_id' => $challenger->id,
                'glory_winner' => Duel::GLORY_WIN,
                'glory_loser' => Duel::GLORY_LOSS,
                'resolved_at' => now(),
            ]);
        }

        $this->assertSame(3, app(DuelService::class)->resolvedTodayCount($class, $challenger));

        $extra = $this->enrollStudent($class, 'Eva Nunes', approvedPersona: true, characterClass: 'bardo');

        $this->actingAs($extra)
            ->post(route('student.arena.challenge'), ['opponent_id' => $challenger->id])
            ->assertRedirect();

        $duel = Duel::query()->where('status', Duel::STATUS_PENDING)->firstOrFail();

        $this->actingAs($challenger)
            ->from(route('student.arena.index'))
            ->post(route('student.arena.accept', $duel))
            ->assertRedirect(route('student.arena.index'))
            ->assertSessionHasErrors([
                'opponent_id' => 'Você já fez '.Duel::DAILY_RESOLVED_LIMIT.' duelos hoje. Só pode duelar de novo amanhã.',
            ]);
    }

    public function test_combat_with_same_seed_is_reproducible(): void
    {
        [$class, $challenger, $opponent] = $this->readyPair(arenaOpen: true);
        $combat = app(ArenaCombatService::class);
        $seed = 42_424_242;

        $first = $combat->resolve($challenger, $opponent, $class, $seed);
        $second = $combat->resolve($challenger, $opponent, $class, $seed);

        $this->assertSame($first['winner_id'], $second['winner_id']);
        $this->assertSame($first['turns'], $second['turns']);
        $this->assertSame($first['fighters'], $second['fighters']);
    }

    public function test_requires_approved_persona_to_challenge(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $class = $this->createClassForTeacher($teacher, ['arena_open' => true]);
        $challenger = $this->enrollStudent($class, 'Ana', approvedPersona: false);
        $opponent = $this->enrollStudent($class, 'Bruno', approvedPersona: true);

        $this->actingAs($challenger)
            ->from(route('student.arena.index'))
            ->post(route('student.arena.challenge'), ['opponent_id' => $opponent->id])
            ->assertRedirect()
            ->assertSessionHasErrors(['opponent_id']);
    }

    public function test_student_from_other_class_cannot_accept_duel(): void
    {
        [$class, $challenger, $opponent] = $this->readyPair(arenaOpen: true);
        $otherTeacher = User::factory()->create(['role' => 'teacher']);
        $otherClass = $this->createClassForTeacher($otherTeacher);
        $stranger = $this->enrollStudent($otherClass, 'Estranho', approvedPersona: true);

        $this->actingAs($challenger)
            ->post(route('student.arena.challenge'), ['opponent_id' => $opponent->id]);

        $duel = Duel::query()->firstOrFail();

        $this->actingAs($stranger)
            ->post(route('student.arena.accept', $duel))
            ->assertForbidden();
    }

    public function test_teacher_arena_tab_shows_open_controls(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $class = $this->createClassForTeacher($teacher);

        $this->actingAs($teacher)
            ->get(route('teacher.classes.show', ['schoolClass' => $class, 'tab' => 'arena']))
            ->assertOk()
            ->assertSee('Arena de batalha')
            ->assertSee('Abrir arena')
            ->assertSee('Configurações da arena')
            ->assertSee('Espera entre batalhas (minutos)')
            ->assertSee('Batalhas permitidas no dia')
            ->assertSee('Como o vencedor é definido')
            ->assertSee('até +30%')
            ->assertSee('Sorte da arena');
    }

    public function test_unauthenticated_arena_settings_update_redirects_to_login(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $class = $this->createClassForTeacher($teacher);

        $this->put(route('teacher.arena.update', $class), $this->arenaSettingsPayload())
            ->assertRedirectToRoute('login');
    }

    public function test_student_cannot_update_arena_settings(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $class = $this->createClassForTeacher($teacher);
        $student = User::factory()->create(['role' => 'student']);

        $this->actingAs($student)
            ->put(route('teacher.arena.update', $class), $this->arenaSettingsPayload())
            ->assertRedirect(route('student.dashboard'));

        $this->assertFalse($class->fresh()->isArenaOpen());
        $this->assertSame(Duel::CHALLENGE_COOLDOWN_MINUTES, $class->fresh()->arenaCooldownMinutes());
        $this->assertSame(Duel::DAILY_RESOLVED_LIMIT, $class->fresh()->arenaDailyLimit());
    }

    public function test_another_teacher_cannot_update_arena_settings(): void
    {
        $owner = User::factory()->create(['role' => 'teacher']);
        $class = $this->createClassForTeacher($owner);
        $otherTeacher = User::factory()->create(['role' => 'teacher']);

        $this->actingAs($otherTeacher)
            ->put(route('teacher.arena.update', $class), $this->arenaSettingsPayload())
            ->assertForbidden();

        $this->assertFalse($class->fresh()->isArenaOpen());
    }

    public function test_teacher_saves_arena_settings_and_opens_the_arena(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $class = $this->createClassForTeacher($teacher);

        $this->actingAs($teacher)
            ->put(route('teacher.arena.update', $class), $this->arenaSettingsPayload([
                'arena_open' => '1',
                'arena_cooldown_minutes' => 15,
                'arena_daily_limit' => 5,
            ]))
            ->assertRedirect(route('teacher.classes.show', ['schoolClass' => $class, 'tab' => 'arena']))
            ->assertSessionHas('success', 'Configurações da arena salvas.');

        $class->refresh();
        $this->assertTrue($class->isArenaOpen());
        $this->assertSame(15, $class->arenaCooldownMinutes());
        $this->assertSame(5, $class->arenaDailyLimit());
        $this->assertSame('15 minutos', $class->arenaCooldownLabel());
    }

    public function test_empty_arena_settings_payload_returns_required_messages(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $class = $this->createClassForTeacher($teacher);

        $this->actingAs($teacher)
            ->from(route('teacher.classes.show', ['schoolClass' => $class, 'tab' => 'arena']))
            ->put(route('teacher.arena.update', $class), [])
            ->assertRedirect()
            ->assertSessionHasErrors([
                'arena_open' => 'Informe se a arena está aberta ou fechada.',
                'arena_cooldown_minutes' => 'Informe o tempo de espera entre batalhas.',
                'arena_daily_limit' => 'Informe quantas batalhas são permitidas no dia.',
            ]);

        $this->assertFalse($class->fresh()->isArenaOpen());
    }

    public function test_arena_settings_reject_zero_daily_battles(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $class = $this->createClassForTeacher($teacher);

        $this->actingAs($teacher)
            ->from(route('teacher.classes.show', ['schoolClass' => $class, 'tab' => 'arena']))
            ->put(route('teacher.arena.update', $class), $this->arenaSettingsPayload([
                'arena_daily_limit' => 0,
            ]))
            ->assertRedirect()
            ->assertSessionHasErrors([
                'arena_daily_limit' => 'É preciso permitir pelo menos 1 batalha por dia.',
            ]);

        $this->assertSame(Duel::DAILY_RESOLVED_LIMIT, $class->fresh()->arenaDailyLimit());
    }

    public function test_closing_arena_via_settings_blocks_challenges(): void
    {
        [$class, $challenger, $opponent] = $this->readyPair(arenaOpen: true);

        $this->actingAs($class->teacher)
            ->put(route('teacher.arena.update', $class), $this->arenaSettingsPayload([
                'arena_open' => '0',
            ]))
            ->assertRedirect();

        $this->assertFalse($class->fresh()->isArenaOpen());

        $this->actingAs($challenger)
            ->from(route('student.arena.index'))
            ->post(route('student.arena.challenge'), ['opponent_id' => $opponent->id])
            ->assertRedirect()
            ->assertSessionHasErrors(['arena']);
    }

    public function test_configured_cooldown_blocks_a_second_challenge_too_soon(): void
    {
        [$class, $challenger, $opponent] = $this->readyPair(arenaOpen: true);
        $third = $this->enrollStudent($class, 'Carla Dias', approvedPersona: true, characterClass: 'arqueiro');
        $class->update(['arena_cooldown_minutes' => 10]);

        $this->actingAs($challenger)
            ->post(route('student.arena.challenge'), ['opponent_id' => $opponent->id])
            ->assertRedirect();

        $this->actingAs($challenger)
            ->from(route('student.arena.index'))
            ->post(route('student.arena.challenge'), ['opponent_id' => $third->id])
            ->assertRedirect(route('student.arena.index'))
            ->assertSessionHasErrors([
                'opponent_id' => 'Aguarde 10 minutos entre um desafio e outro.',
            ]);

        $this->travel(11)->minutes();

        $this->actingAs($challenger)
            ->post(route('student.arena.challenge'), ['opponent_id' => $third->id])
            ->assertRedirect();

        $this->assertSame(2, Duel::query()->where('challenger_id', $challenger->id)->count());
    }

    public function test_zero_cooldown_allows_immediate_second_challenge(): void
    {
        [$class, $challenger, $opponent] = $this->readyPair(arenaOpen: true);
        $third = $this->enrollStudent($class, 'Carla Dias', approvedPersona: true, characterClass: 'arqueiro');
        $class->update(['arena_cooldown_minutes' => 0]);

        $this->actingAs($challenger)
            ->post(route('student.arena.challenge'), ['opponent_id' => $opponent->id])
            ->assertRedirect();

        $this->actingAs($challenger)
            ->post(route('student.arena.challenge'), ['opponent_id' => $third->id])
            ->assertRedirect();

        $this->assertSame(2, Duel::query()->where('challenger_id', $challenger->id)->count());
    }

    public function test_configured_daily_limit_blocks_further_accepts(): void
    {
        [$class, $challenger, $opponent] = $this->readyPair(arenaOpen: true);
        $class->update(['arena_daily_limit' => 1]);

        Duel::query()->create([
            'class_id' => $class->id,
            'challenger_id' => $opponent->id,
            'opponent_id' => $challenger->id,
            'status' => Duel::STATUS_RESOLVED,
            'seed' => 1001,
            'log' => ['turns' => [], 'fighters' => [], 'winner_id' => $challenger->id],
            'winner_id' => $challenger->id,
            'glory_winner' => Duel::GLORY_WIN,
            'glory_loser' => Duel::GLORY_LOSS,
            'resolved_at' => now(),
        ]);

        $extra = $this->enrollStudent($class, 'Eva Nunes', approvedPersona: true, characterClass: 'bardo');

        $this->actingAs($extra)
            ->post(route('student.arena.challenge'), ['opponent_id' => $challenger->id])
            ->assertRedirect();

        $duel = Duel::query()->where('status', Duel::STATUS_PENDING)->firstOrFail();

        $this->actingAs($challenger)
            ->from(route('student.arena.index'))
            ->post(route('student.arena.accept', $duel))
            ->assertRedirect(route('student.arena.index'))
            ->assertSessionHasErrors([
                'opponent_id' => 'Você já fez 1 duelo hoje. Só pode duelar de novo amanhã.',
            ]);
    }

    public function test_student_arena_shows_configured_limits(): void
    {
        [$class, $challenger] = $this->readyPair(arenaOpen: true);
        $class->update([
            'arena_cooldown_minutes' => 30,
            'arena_daily_limit' => 4,
        ]);

        $this->actingAs($challenger)
            ->get(route('student.arena.index'))
            ->assertOk()
            ->assertSee('Espere')
            ->assertSee('30 minutos')
            ->assertSee('4 duelos resolvidos');
    }

    /**
     * @return array{0: SchoolClass, 1: User, 2: User}
     */
    private function readyPair(bool $arenaOpen = false): array
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $class = $this->createClassForTeacher($teacher, ['arena_open' => $arenaOpen]);
        $challenger = $this->enrollStudent($class, 'Ana Souza', approvedPersona: true, characterClass: 'guerreiro');
        $opponent = $this->enrollStudent($class, 'Bruno Lima', approvedPersona: true, characterClass: 'mago');

        return [$class, $challenger, $opponent];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function arenaSettingsPayload(array $overrides = []): array
    {
        return array_merge([
            'arena_open' => '1',
            'arena_cooldown_minutes' => Duel::CHALLENGE_COOLDOWN_MINUTES,
            'arena_daily_limit' => Duel::DAILY_RESOLVED_LIMIT,
        ], $overrides);
    }

    private function enrollStudent(
        SchoolClass $class,
        string $name,
        bool $approvedPersona = true,
        string $characterClass = 'guerreiro',
    ): User {
        $student = User::factory()->create([
            'name' => $name,
            'role' => 'student',
            'character_class' => $characterClass,
            'must_change_password' => false,
            'character_name' => $approvedPersona ? 'Heroi '.$name : null,
            'character_avatar' => $approvedPersona ? 'lobo' : null,
            'character_approval_status' => $approvedPersona ? 'approved' : 'none',
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
}
