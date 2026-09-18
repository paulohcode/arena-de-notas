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

    public function test_student_buys_two_lots_of_auras_with_relics(): void
    {
        [$class, $student] = $this->readyStudent(relics: 40, seals: 0, auras: 0);
        $rate = $this->offer(
            payCurrency: 'relics',
            payAmount: 15,
            receiveCurrency: 'auras',
            receiveAmount: 100,
        );

        $this->actingAs($student)
            ->post(route('student.exchange.trade'), [
                'exchange_rate_id' => $rate->id,
                'lots' => 2,
            ])
            ->assertRedirect(route('student.exchange.index'))
            ->assertSessionHas('success');

        $enrollment = $student->enrollmentIn($class)->fresh();
        $this->assertSame(10, (int) $enrollment->relics);
        $this->assertSame(200, $this->auras($student, $class));

        $log = CurrencyExchange::query()->firstOrFail();
        $this->assertSame('relics', $log->pay_currency);
        $this->assertSame('auras', $log->receive_currency);
        $this->assertSame(30, (int) $log->pay_amount);
        $this->assertSame(200, (int) $log->receive_amount);
        $this->assertSame(2, (int) $log->lots);
    }

    public function test_student_can_buy_same_currency_with_alternative_payment(): void
    {
        [$class, $student] = $this->readyStudent(relics: 0, seals: 10, auras: 0);
        $this->offer('relics', 15, 'auras', 100);
        $withSeals = $this->offer('seals', 5, 'auras', 100);

        $this->actingAs($student)
            ->post(route('student.exchange.trade'), [
                'exchange_rate_id' => $withSeals->id,
                'lots' => 1,
            ])
            ->assertRedirect(route('student.exchange.index'))
            ->assertSessionHas('success');

        $this->assertSame(5, (int) $student->enrollmentIn($class)->fresh()->seals);
        $this->assertSame(100, $this->auras($student, $class));
    }

    public function test_student_buys_relics_paying_seals_or_auras(): void
    {
        [$class, $student] = $this->readyStudent(relics: 0, seals: 50, auras: 400);
        $withSeals = $this->offer('seals', 50, 'relics', 500);
        $withAuras = $this->offer('auras', 400, 'relics', 500);

        $this->actingAs($student)
            ->post(route('student.exchange.trade'), [
                'exchange_rate_id' => $withSeals->id,
                'lots' => 1,
            ])
            ->assertRedirect(route('student.exchange.index'));

        $this->assertSame(500, (int) $student->enrollmentIn($class)->fresh()->relics);
        $this->assertSame(0, (int) $student->enrollmentIn($class)->fresh()->seals);

        $this->actingAs($student)
            ->post(route('student.exchange.trade'), [
                'exchange_rate_id' => $withAuras->id,
                'lots' => 1,
            ])
            ->assertRedirect(route('student.exchange.index'));

        $this->assertSame(1000, (int) $student->enrollmentIn($class)->fresh()->relics);
        $this->assertSame(0, $this->auras($student, $class));
    }

    public function test_insufficient_balance_rejects_trade_without_changes(): void
    {
        [$class, $student] = $this->readyStudent(relics: 10, seals: 0, auras: 0);
        $rate = $this->offer('relics', 15, 'auras', 100);

        $this->actingAs($student)
            ->from(route('student.exchange.index'))
            ->post(route('student.exchange.trade'), [
                'exchange_rate_id' => $rate->id,
                'lots' => 1,
            ])
            ->assertRedirect(route('student.exchange.index'))
            ->assertSessionHasErrors('lots');

        $this->assertSame(10, (int) $student->enrollmentIn($class)->fresh()->relics);
        $this->assertSame(0, $this->auras($student, $class));
        $this->assertSame(0, CurrencyExchange::query()->count());
    }

    public function test_inactive_offer_is_rejected(): void
    {
        [$class, $student] = $this->readyStudent(relics: 50, seals: 0, auras: 0);
        $rate = $this->offer('relics', 15, 'auras', 100, active: false);

        $this->actingAs($student)
            ->from(route('student.exchange.index'))
            ->post(route('student.exchange.trade'), [
                'exchange_rate_id' => $rate->id,
                'lots' => 1,
            ])
            ->assertRedirect(route('student.exchange.index'))
            ->assertSessionHasErrors('exchange_rate_id');

        $this->assertSame(50, (int) $student->enrollmentIn($class)->fresh()->relics);
        $this->assertSame(0, CurrencyExchange::query()->count());
    }

    public function test_class_without_area_hides_and_blocks_aura_offers(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $class = $this->createClassForTeacher($teacher, ['area_id' => null]);
        $student = $this->enrollStudent($class, 'Ana Souza', relics: 100, seals: 20);

        $auraOffer = $this->offer('relics', 15, 'auras', 100);
        $this->offer('relics', 100, 'seals', 50);

        $this->actingAs($student)
            ->get(route('student.exchange.index'))
            ->assertOk()
            ->assertSee('Selos')
            ->assertDontSee('100 Aura');

        $this->actingAs($student)
            ->from(route('student.exchange.index'))
            ->post(route('student.exchange.trade'), [
                'exchange_rate_id' => $auraOffer->id,
                'lots' => 1,
            ])
            ->assertRedirect(route('student.exchange.index'))
            ->assertSessionHasErrors('exchange_rate_id');

        $this->assertSame(100, (int) $student->enrollmentIn($class)->fresh()->relics);
        $this->assertSame(0, CurrencyExchange::query()->count());
    }

    public function test_student_sees_default_offers_grouped_by_currency(): void
    {
        [$class, $student] = $this->readyStudent(relics: 100, seals: 50, auras: 50);

        $this->actingAs($student)
            ->get(route('student.exchange.index'))
            ->assertOk()
            ->assertSee('Casa de Câmbio')
            ->assertSee('100 Aura')
            ->assertSee('15 Relíquias')
            ->assertSee('5 Selos')
            ->assertSee('500 Relíquias')
            ->assertSee('50 Selos');
    }

    private function offer(
        string $payCurrency,
        int $payAmount,
        string $receiveCurrency,
        int $receiveAmount,
        bool $active = true,
    ): ExchangeRate {
        $rate = ExchangeRate::query()->updateOrCreate(
            [
                'pay_currency' => $payCurrency,
                'receive_currency' => $receiveCurrency,
            ],
            [
                'pay_amount' => $payAmount,
                'receive_amount' => $receiveAmount,
                'is_active' => $active,
            ],
        );

        return $rate->fresh();
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
