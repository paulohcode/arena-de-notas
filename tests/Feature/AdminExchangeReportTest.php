<?php

namespace Tests\Feature;

use App\Models\CurrencyExchange;
use App\Models\CurrencyTrade;
use App\Models\ExchangeRaffle;
use App\Models\GameCurrency;
use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AdminExchangeReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_report_redirects_to_login(): void
    {
        $this->get(route('admin.reports.exchange'))
            ->assertRedirectToRoute('login');
    }

    public function test_teacher_cannot_open_exchange_report(): void
    {
        $teacher = User::factory()->create([
            'role' => 'teacher',
            'must_change_password' => false,
        ]);

        $this->actingAs($teacher)
            ->get(route('admin.reports.exchange'))
            ->assertRedirect(route('teacher.dashboard'));
    }

    public function test_student_cannot_open_exchange_report(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $class = $this->createClassForTeacher($teacher);
        $student = $this->enrollStudent($class, 'Ana Souza');

        $this->actingAs($student)
            ->get(route('admin.reports.exchange'))
            ->assertRedirect(route('student.dashboard'));
    }

    public function test_admin_sees_empty_states_for_quiet_day(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'must_change_password' => false]);

        $this->actingAs($admin)
            ->get(route('admin.reports.exchange', ['date' => '2026-09-14']))
            ->assertOk()
            ->assertSee('Relatório do câmbio')
            ->assertSee('Nenhuma compra na casa neste dia.')
            ->assertSee('Nenhuma negociação entre alunos neste dia.')
            ->assertSee('Nenhum sorteio neste dia.');
    }

    public function test_admin_sees_house_purchase_peer_trade_and_raffle_on_day(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'must_change_password' => false]);
        $teacher = User::factory()->create(['role' => 'teacher', 'must_change_password' => false]);
        $class = $this->createClassForTeacher($teacher, ['name' => 'Turma Câmbio']);
        $buyer = $this->enrollStudent($class, 'Comprador Casa');
        $seller = $this->enrollStudent($class, 'Vendedor Peer');
        $peerBuyer = $this->enrollStudent($class, 'Comprador Peer');
        $winner = $this->enrollStudent($class, 'Vencedor Pote');

        $onDay = Carbon::parse('2026-09-14 15:30:00', config('app.display_timezone'))->utc();
        $otherDay = Carbon::parse('2026-09-13 15:30:00', config('app.display_timezone'))->utc();

        CurrencyExchange::query()->create([
            'student_id' => $buyer->id,
            'class_id' => $class->id,
            'area_id' => $class->area_id,
            'exchange_rate_id' => null,
            'pay_currency' => GameCurrency::KEY_RELICS,
            'receive_currency' => GameCurrency::KEY_SEALS,
            'pay_amount' => 10,
            'receive_amount' => 5,
            'lots' => 2,
            'created_at' => $onDay,
        ]);

        CurrencyExchange::query()->create([
            'student_id' => $buyer->id,
            'class_id' => $class->id,
            'area_id' => $class->area_id,
            'exchange_rate_id' => null,
            'pay_currency' => GameCurrency::KEY_AURAS,
            'receive_currency' => GameCurrency::KEY_RELICS,
            'pay_amount' => 1,
            'receive_amount' => 3,
            'lots' => 1,
            'created_at' => $otherDay,
        ]);

        CurrencyTrade::query()->create([
            'area_id' => $class->area_id,
            'vault_id' => null,
            'listing_id' => null,
            'seller_id' => $seller->id,
            'seller_class_id' => $class->id,
            'buyer_id' => $peerBuyer->id,
            'buyer_class_id' => $class->id,
            'offer_currency' => GameCurrency::KEY_RELICS,
            'offer_amount' => 20,
            'ask_currency' => GameCurrency::KEY_SEALS,
            'ask_amount' => 10,
            'fee_currency' => GameCurrency::KEY_SEALS,
            'fee_amount' => 1,
            'created_at' => $onDay,
        ]);

        ExchangeRaffle::query()->create([
            'area_id' => $class->area_id,
            'admin_id' => $admin->id,
            'winner_id' => $winner->id,
            'relics' => 0,
            'seals' => 5,
            'auras' => 0,
            'created_at' => $onDay,
        ]);

        $response = $this->actingAs($admin)
            ->get(route('admin.reports.exchange', ['date' => '2026-09-14']));

        $response->assertOk()
            ->assertSee('Comprador Casa')
            ->assertSee('Turma Câmbio')
            ->assertSee('Vendedor Peer')
            ->assertSee('Comprador Peer')
            ->assertSee('Vencedor Pote')
            ->assertSee('15:30');

        $this->actingAs($admin)
            ->get(route('admin.reports.exchange', ['date' => '2026-09-13']))
            ->assertOk()
            ->assertSee('Comprador Casa')
            ->assertDontSee('Vendedor Peer')
            ->assertDontSee('Vencedor Pote');
    }

    public function test_area_filter_hides_other_realm_activity(): void
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
        $peerA = $this->enrollStudent($classA, 'Peer Norte');
        $peerB = $this->enrollStudent($classB, 'Peer Sul');

        $onDay = Carbon::parse('2026-09-14 12:00:00', config('app.display_timezone'))->utc();

        CurrencyExchange::query()->create([
            'student_id' => $studentA->id,
            'class_id' => $classA->id,
            'area_id' => $areaA->id,
            'exchange_rate_id' => null,
            'pay_currency' => GameCurrency::KEY_RELICS,
            'receive_currency' => GameCurrency::KEY_SEALS,
            'pay_amount' => 5,
            'receive_amount' => 2,
            'lots' => 1,
            'created_at' => $onDay,
        ]);

        CurrencyExchange::query()->create([
            'student_id' => $studentB->id,
            'class_id' => $classB->id,
            'area_id' => $areaB->id,
            'exchange_rate_id' => null,
            'pay_currency' => GameCurrency::KEY_RELICS,
            'receive_currency' => GameCurrency::KEY_SEALS,
            'pay_amount' => 5,
            'receive_amount' => 2,
            'lots' => 1,
            'created_at' => $onDay,
        ]);

        CurrencyTrade::query()->create([
            'area_id' => $areaA->id,
            'vault_id' => null,
            'listing_id' => null,
            'seller_id' => $studentA->id,
            'seller_class_id' => $classA->id,
            'buyer_id' => $peerA->id,
            'buyer_class_id' => $classA->id,
            'offer_currency' => GameCurrency::KEY_RELICS,
            'offer_amount' => 10,
            'ask_currency' => GameCurrency::KEY_SEALS,
            'ask_amount' => 5,
            'fee_currency' => GameCurrency::KEY_SEALS,
            'fee_amount' => 1,
            'created_at' => $onDay,
        ]);

        CurrencyTrade::query()->create([
            'area_id' => $areaB->id,
            'vault_id' => null,
            'listing_id' => null,
            'seller_id' => $studentB->id,
            'seller_class_id' => $classB->id,
            'buyer_id' => $peerB->id,
            'buyer_class_id' => $classB->id,
            'offer_currency' => GameCurrency::KEY_RELICS,
            'offer_amount' => 10,
            'ask_currency' => GameCurrency::KEY_SEALS,
            'ask_amount' => 5,
            'fee_currency' => GameCurrency::KEY_SEALS,
            'fee_amount' => 1,
            'created_at' => $onDay,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.reports.exchange', [
                'date' => '2026-09-14',
                'area' => $areaA->id,
            ]))
            ->assertOk()
            ->assertSee('Aluno Norte')
            ->assertSee('Peer Norte')
            ->assertDontSee('Aluno Sul')
            ->assertDontSee('Peer Sul');
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
