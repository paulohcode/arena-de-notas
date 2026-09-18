<?php

namespace App\Services;

use App\Models\Area;
use App\Models\AreaBalance;
use App\Models\CurrencyExchange;
use App\Models\Enrollment;
use App\Models\ExchangeRate;
use App\Models\GameCurrency;
use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CurrencyExchangeService
{
    /**
     * @return Collection<int, ExchangeRate>
     */
    public function ratesForAdmin(): Collection
    {
        ExchangeRate::ensureShopOffers();

        return ExchangeRate::query()
            ->orderBy('receive_currency')
            ->orderBy('pay_currency')
            ->orderBy('id')
            ->get();
    }

    public function createRate(array $data): ExchangeRate
    {
        return ExchangeRate::query()->create([
            'pay_currency' => (string) $data['pay_currency'],
            'pay_amount' => (int) $data['pay_amount'],
            'receive_currency' => (string) $data['receive_currency'],
            'receive_amount' => (int) $data['receive_amount'],
            'is_active' => true,
        ]);
    }

    public function updateRate(ExchangeRate $rate, array $data): ExchangeRate
    {
        $rate->fill([
            'pay_currency' => (string) $data['pay_currency'],
            'pay_amount' => (int) $data['pay_amount'],
            'receive_currency' => (string) $data['receive_currency'],
            'receive_amount' => (int) $data['receive_amount'],
            'is_active' => (bool) ($data['is_active'] ?? $rate->is_active),
        ]);
        $rate->save();

        return $rate->fresh();
    }

    public function deleteRate(ExchangeRate $rate): void
    {
        $rate->delete();
    }

    /**
     * @return array{
     *     enrollment: Enrollment,
     *     auras: int,
     *     offers: list<array{
     *         receive_currency: string,
     *         receive_label: string,
     *         options: list<array{
     *             id: int,
     *             pay_currency: string,
     *             pay_amount: int,
     *             receive_currency: string,
     *             receive_amount: int,
     *             label: string
     *         }>
     *     }>
     * }
     */
    public function shopData(User $student, SchoolClass $class): array
    {
        $enrollment = $student->enrollmentIn($class);

        if (! $enrollment) {
            throw ValidationException::withMessages([
                'exchange' => 'Você não está matriculado nesta turma.',
            ]);
        }

        $auras = 0;
        if ($class->area_id) {
            $auras = (int) AreaBalance::query()
                ->where('area_id', $class->area_id)
                ->where('student_id', $student->id)
                ->value('auras');
        }

        ExchangeRate::ensureShopOffers();

        $grouped = [];
        foreach (GameCurrency::SHOP_KEYS as $receiveCurrency) {
            $grouped[$receiveCurrency] = [
                'receive_currency' => $receiveCurrency,
                'receive_label' => GameCurrency::format($receiveCurrency),
                'options' => [],
            ];
        }

        ExchangeRate::query()
            ->active()
            ->orderBy('receive_currency')
            ->orderBy('pay_currency')
            ->orderBy('id')
            ->get()
            ->filter(fn (ExchangeRate $rate): bool => $this->rateAvailableForClass($rate, $class))
            ->each(function (ExchangeRate $rate) use (&$grouped): void {
                $grouped[$rate->receive_currency]['options'][] = [
                    'id' => $rate->id,
                    'pay_currency' => $rate->pay_currency,
                    'pay_amount' => (int) $rate->pay_amount,
                    'receive_currency' => $rate->receive_currency,
                    'receive_amount' => (int) $rate->receive_amount,
                    'label' => $rate->offerLabel(),
                ];
            });

        $offers = collect($grouped)
            ->filter(fn (array $group): bool => count($group['options']) > 0)
            ->values()
            ->all();

        return [
            'enrollment' => $enrollment,
            'auras' => $auras,
            'offers' => $offers,
        ];
    }

    public function trade(User $student, SchoolClass $class, int $rateId, int $lots): CurrencyExchange
    {
        return DB::transaction(function () use ($student, $class, $rateId, $lots) {
            $rate = ExchangeRate::query()
                ->whereKey($rateId)
                ->lockForUpdate()
                ->first();

            if (! $rate || ! $rate->is_active) {
                throw ValidationException::withMessages([
                    'exchange_rate_id' => 'Esta oferta não está disponível.',
                ]);
            }

            if (! $this->rateAvailableForClass($rate, $class)) {
                throw ValidationException::withMessages([
                    'exchange_rate_id' => 'Esta oferta precisa de um reino (Aura).',
                ]);
            }

            $payTotal = (int) $rate->pay_amount * $lots;
            $receiveTotal = (int) $rate->receive_amount * $lots;

            $enrollment = Enrollment::query()
                ->where('class_id', $class->id)
                ->where('student_id', $student->id)
                ->lockForUpdate()
                ->first();

            if (! $enrollment) {
                throw ValidationException::withMessages([
                    'exchange' => 'Você não está matriculado nesta turma.',
                ]);
            }

            $this->debitCurrency($student, $class, $enrollment, $rate->pay_currency, $payTotal);
            $this->creditCurrency($student, $class, $enrollment, $rate->receive_currency, $receiveTotal);
            $enrollment->save();

            return CurrencyExchange::query()->create([
                'student_id' => $student->id,
                'class_id' => $class->id,
                'area_id' => $class->area_id,
                'exchange_rate_id' => $rate->id,
                'pay_currency' => $rate->pay_currency,
                'receive_currency' => $rate->receive_currency,
                'pay_amount' => $payTotal,
                'receive_amount' => $receiveTotal,
                'lots' => $lots,
                'created_at' => now(),
            ]);
        });
    }

    private function rateAvailableForClass(ExchangeRate $rate, SchoolClass $class): bool
    {
        if ($rate->usesCurrency(GameCurrency::KEY_AURAS) && ! $class->area_id) {
            return false;
        }

        return true;
    }

    private function debitCurrency(
        User $student,
        SchoolClass $class,
        Enrollment $enrollment,
        string $currency,
        int $amount,
    ): void {
        if ($currency === GameCurrency::KEY_AURAS) {
            $balance = $this->lockedAreaBalance($student, $class);
            if ((int) $balance->auras < $amount) {
                throw ValidationException::withMessages([
                    'lots' => GameCurrency::label(GameCurrency::KEY_AURAS).' insuficientes para esta compra.',
                ]);
            }

            $balance->auras = (int) $balance->auras - $amount;
            $balance->save();

            return;
        }

        $current = (int) $enrollment->{$currency};
        if ($current < $amount) {
            throw ValidationException::withMessages([
                'lots' => GameCurrency::label($currency).' insuficientes para esta compra.',
            ]);
        }

        $enrollment->{$currency} = $current - $amount;
    }

    private function creditCurrency(
        User $student,
        SchoolClass $class,
        Enrollment $enrollment,
        string $currency,
        int $amount,
    ): void {
        if ($currency === GameCurrency::KEY_AURAS) {
            $balance = $this->lockedAreaBalance($student, $class);
            $balance->auras = (int) $balance->auras + $amount;
            $balance->save();

            return;
        }

        $enrollment->{$currency} = (int) $enrollment->{$currency} + $amount;
    }

    private function lockedAreaBalance(User $student, SchoolClass $class): AreaBalance
    {
        $area = $class->area;
        if (! $area instanceof Area) {
            throw ValidationException::withMessages([
                'exchange' => 'Esta turma não pertence a um reino para usar Aura.',
            ]);
        }

        $balance = AreaBalance::query()
            ->where('area_id', $area->id)
            ->where('student_id', $student->id)
            ->lockForUpdate()
            ->first();

        if (! $balance) {
            $balance = AreaBalance::query()->create([
                'area_id' => $area->id,
                'student_id' => $student->id,
                'auras' => 0,
            ]);
            $balance = AreaBalance::query()->whereKey($balance->id)->lockForUpdate()->firstOrFail();
        }

        return $balance;
    }
}
