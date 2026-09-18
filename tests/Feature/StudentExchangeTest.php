<?php

namespace Tests\Feature;

use App\Models\AreaBalance;
use App\Models\CurrencyExchange;
use App\Models\ExchangeRate;
use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentExchangeTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_exchange_redirects_to_login(): void
    {
        $this->get(route('student.exchange.index'))
            ->assertRedirectToRoute('login');
    }

    public function test_teacher_cannot_open_student_exchange(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);

        $this->actingAs($teacher)
            ->get(route('student.exchange.index'))
            ->assertRedirect(route('teacher.dashboard'));
    }

    public function test_admin_can_open_student_exchange_via_role_bypass(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'must_change_password' => false]);

        $this->actingAs($admin)
            ->get(route('student.exchange.index'))
            ->assertOk();
    }

    public function test_student_exchanges_two_lots_relics_to_seals(): void
    {
        [$class, $student] = $this->readyStudent(relics: 50, seals: 0);
        $rate = $this->createRelicsSealsRate();

        $this->actingAs($student)
            ->post(route('student.exchange.trade'), [
                'exchange_rate_id' => $rate->id,
                'direction' => ExchangeRate::DIRECTION_A_TO_B,
                'lots' => 2,
            ])
            ->assertRedirect(route('student.exchange.index'))
            ->assertSessionHas('success');

        $enrollment = $student->enrollmentIn($class)->fresh();
        $this->assertSame(16, (int) $enrollment->relics);
        $this->assertSame(20, (int) $enrollment->seals);

        $log = CurrencyExchange::query()->firstOrFail();
        $this->assertSame($student->id, (int) $log->student_id);
        $this->assertSame($class->id, (int) $log->class_id);
        $this->assertSame('relics', $log->pay_currency);
        $this->assertSame('seals', $log->receive_currency);
        $this->assertSame(34, (int) $log->pay_amount);
        $this->assertSame(20, (int) $log->receive_amount);
        $this->assertSame(2, (int) $log->lots);
    }

    public function test_same_rate_accepts_reverse_direction(): void
    {
        [$class, $student] = $this->readyStudent(relics: 0, seals: 20);
        $rate = $this->createRelicsSealsRate();

        $this->actingAs($student)
            ->post(route('student.exchange.trade'), [
                'exchange_rate_id' => $rate->id,
                'direction' => ExchangeRate::DIRECTION_B_TO_A,
                'lots' => 1,
            ])
            ->assertRedirect(route('student.exchange.index'))
            ->assertSessionHas('success');

        $enrollment = $student->enrollmentIn($class)->fresh();
        $this->assertSame(17, (int) $enrollment->relics);
        $this->assertSame(10, (int) $enrollment->seals);

        $log = CurrencyExchange::query()->firstOrFail();
        $this->assertSame('seals', $log->pay_currency);
        $this->assertSame('relics', $log->receive_currency);
        $this->assertSame(10, (int) $log->pay_amount);
        $this->assertSame(17, (int) $log->receive_amount);
    }

    public function test_insufficient_balance_rejects_trade_without_changes(): void
    {
        [$class, $student] = $this->readyStudent(relics: 10, seals: 0);
        $rate = $this->createRelicsSealsRate();

        $this->actingAs($student)
            ->from(route('student.exchange.index'))
            ->post(route('student.exchange.trade'), [
                'exchange_rate_id' => $rate->id,
                'direction' => ExchangeRate::DIRECTION_A_TO_B,
                'lots' => 1,
            ])
            ->assertRedirect(route('student.exchange.index'))
            ->assertSessionHasErrors('lots');

        $enrollment = $student->enrollmentIn($class)->fresh();
        $this->assertSame(10, (int) $enrollment->relics);
        $this->assertSame(0, (int) $enrollment->seals);
        $this->assertSame(0, CurrencyExchange::query()->count());
    }

    public function test_inactive_rate_rejects_both_directions(): void
    {
        [$class, $student] = $this->readyStudent(relics: 50, seals: 50);
        $rate = $this->createRelicsSealsRate(active: false);

        foreach ([ExchangeRate::DIRECTION_A_TO_B, ExchangeRate::DIRECTION_B_TO_A] as $direction) {
            $this->actingAs($student)
                ->from(route('student.exchange.index'))
                ->post(route('student.exchange.trade'), [
                    'exchange_rate_id' => $rate->id,
                    'direction' => $direction,
                    'lots' => 1,
                ])
                ->assertRedirect(route('student.exchange.index'))
                ->assertSessionHasErrors('exchange_rate_id');
        }

        $enrollment = $student->enrollmentIn($class)->fresh();
        $this->assertSame(50, (int) $enrollment->relics);
        $this->assertSame(50, (int) $enrollment->seals);
        $this->assertSame(0, CurrencyExchange::query()->count());
    }

    public function test_aura_trade_updates_area_balance(): void
    {
        [$class, $student] = $this->readyStudent(relics: 0, seals: 0, auras: 12);
        $rate = ExchangeRate::query()->create([
            'currency_a' => 'auras',
            'currency_b' => 'seals',
            'amount_a' => 6,
            'amount_b' => 5,
            'is_active' => true,
        ]);

        $this->actingAs($student)
            ->post(route('student.exchange.trade'), [
                'exchange_rate_id' => $rate->id,
                'direction' => ExchangeRate::DIRECTION_A_TO_B,
                'lots' => 1,
            ])
            ->assertRedirect(route('student.exchange.index'))
            ->assertSessionHas('success');

        $this->assertSame(6, $this->auras($student, $class));
        $this->assertSame(5, (int) $student->enrollmentIn($class)->fresh()->seals);
    }

    public function test_class_without_area_hides_and_blocks_aura_rates(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $class = $this->createClassForTeacher($teacher, ['area_id' => null]);
        $student = $this->enrollStudent($class, 'Ana Souza', relics: 0, seals: 20);

        $auraRate = ExchangeRate::query()->create([
            'currency_a' => 'auras',
            'currency_b' => 'seals',
            'amount_a' => 6,
            'amount_b' => 5,
            'is_active' => true,
        ]);
        $this->createRelicsSealsRate();

        $this->actingAs($student)
            ->get(route('student.exchange.index'))
            ->assertOk()
            ->assertSee('Relíquias')
            ->assertDontSee('6 Aura');

        $this->actingAs($student)
            ->from(route('student.exchange.index'))
            ->post(route('student.exchange.trade'), [
                'exchange_rate_id' => $auraRate->id,
                'direction' => ExchangeRate::DIRECTION_B_TO_A,
                'lots' => 1,
            ])
            ->assertRedirect(route('student.exchange.index'))
            ->assertSessionHasErrors('exchange_rate_id');

        $this->assertSame(20, (int) $student->enrollmentIn($class)->fresh()->seals);
        $this->assertSame(0, CurrencyExchange::query()->count());
    }

    public function test_student_sees_active_rates_on_exchange_page(): void
    {
        [$class, $student] = $this->readyStudent(relics: 17, seals: 10);
        $this->createRelicsSealsRate();

        $this->actingAs($student)
            ->get(route('student.exchange.index'))
            ->assertOk()
            ->assertSee('Casa de Câmbio')
            ->assertSee('17 Relíquias')
            ->assertSee('10 Selos');
    }

    private function createRelicsSealsRate(bool $active = true): ExchangeRate
    {
        return ExchangeRate::query()->create([
            'currency_a' => 'relics',
            'currency_b' => 'seals',
            'amount_a' => 17,
            'amount_b' => 10,
            'is_active' => $active,
        ]);
    }

    /**
     * @return array{0: SchoolClass, 1: User}
     */
    private function readyStudent(int $relics = 0, int $seals = 0, int $auras = 0): array
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $class = $this->createClassForTeacher($teacher);
        $student = $this->enrollStudent($class, 'Ana Souza', relics: $relics, seals: $seals);
        $this->setAuras($student, $class, $auras);

        return [$class, $student];
    }

    private function enrollStudent(
        SchoolClass $class,
        string $name,
        int $relics = 0,
        int $seals = 0,
    ): User {
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
                'relics' => $relics,
                'seals' => $seals,
                'arena_wins' => 0,
                'arena_losses' => 0,
                'behavior_score' => 100,
            ],
        ]);

        return $student->fresh();
    }

    private function setAuras(User $student, SchoolClass $class, int $auras): void
    {
        if (! $class->area_id) {
            return;
        }

        AreaBalance::query()->updateOrCreate(
            ['area_id' => $class->area_id, 'student_id' => $student->id],
            ['auras' => $auras],
        );
    }

    private function auras(User $student, SchoolClass $class): int
    {
        return (int) AreaBalance::query()
            ->where('area_id', $class->area_id)
            ->where('student_id', $student->id)
            ->value('auras');
    }
}
