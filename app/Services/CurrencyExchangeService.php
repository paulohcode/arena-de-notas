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
        return ExchangeRate::query()
            ->orderBy('currency_a')
            ->orderBy('currency_b')
            ->orderBy('id')
            ->get();
    }

    public function createRate(array $data): ExchangeRate
    {
        $normalized = ExchangeRate::normalizePair(
            (string) $data['currency_a'],
            (string) $data['currency_b'],
            (int) $data['amount_a'],
            (int) $data['amount_b'],
        );

        return ExchangeRate::query()->create([
            ...$normalized,
            'is_active' => true,
        ]);
    }

    public function updateRate(ExchangeRate $rate, array $data): ExchangeRate
    {
        $normalized = ExchangeRate::normalizePair(
            (string) $data['currency_a'],
            (string) $data['currency_b'],
            (int) $data['amount_a'],
            (int) $data['amount_b'],
        );

        $rate->fill([
            ...$normalized,
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
     *     rates: list<array{
     *         id: int,
     *         parity: string,
     *         directions: list<array{
     *             direction: string,
     *             pay_currency: string,
     *             receive_currency: string,
     *             pay_amount: int,
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

        $rates = ExchangeRate::query()
            ->active()
            ->orderBy('currency_a')
            ->orderBy('currency_b')
            ->orderBy('id')
            ->get()
            ->filter(fn (ExchangeRate $rate): bool => $this->rateAvailableForClass($rate, $class))
            ->map(fn (ExchangeRate $rate): array => $this->presentRate($rate))
            ->values()
            ->all();

        return [
            'enrollment' => $enrollment,
            'auras' => $auras,
            'rates' => $rates,
        ];
    }

    public function trade(User $student, SchoolClass $class, int $rateId, string $direction, int $lots): CurrencyExchange
    {
        return DB::transaction(function () use ($student, $class, $rateId, $direction, $lots) {
            $rate = ExchangeRate::query()
                ->whereKey($rateId)
                ->lockForUpdate()
                ->first();

            if (! $rate || ! $rate->is_active) {
                throw ValidationException::withMessages([
                    'exchange_rate_id' => 'Esta cotação não está disponível.',
                ]);
            }

            if (! $this->rateAvailableForClass($rate, $class)) {
                throw ValidationException::withMessages([
                    'exchange_rate_id' => 'Esta cotação precisa de um reino (Aura).',
                ]);
            }

            $resolved = $rate->resolveDirection($direction);
            if ($resolved === null) {
                throw ValidationException::withMessages([
                    'direction' => 'O sentido da troca é inválido.',
                ]);
            }

            $payTotal = $resolved['pay_amount'] * $lots;
            $receiveTotal = $resolved['receive_amount'] * $lots;

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

            $this->debitCurrency($student, $class, $enrollment, $resolved['pay_currency'], $payTotal);
            $this->creditCurrency($student, $class, $enrollment, $resolved['receive_currency'], $receiveTotal);
            $enrollment->save();

            return CurrencyExchange::query()->create([
                'student_id' => $student->id,
                'class_id' => $class->id,
                'area_id' => $class->area_id,
                'exchange_rate_id' => $rate->id,
                'pay_currency' => $resolved['pay_currency'],
                'receive_currency' => $resolved['receive_currency'],
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

    /**
     * @return array{
     *     id: int,
     *     parity: string,
     *     directions: list<array{
     *         direction: string,
     *         pay_currency: string,
     *         receive_currency: string,
     *         pay_amount: int,
     *         receive_amount: int,
     *         label: string
     *     }>
     * }
     */
    private function presentRate(ExchangeRate $rate): array
    {
        $directions = [];

        foreach ([ExchangeRate::DIRECTION_A_TO_B, ExchangeRate::DIRECTION_B_TO_A] as $direction) {
            $resolved = $rate->resolveDirection($direction);
            if ($resolved === null) {
                continue;
            }

            $directions[] = [
                ...$resolved,
                'direction' => $direction,
                'label' => 'Pagar '
                    .GameCurrency::format($resolved['pay_currency'], $resolved['pay_amount'])
                    .' → receber '
                    .GameCurrency::format($resolved['receive_currency'], $resolved['receive_amount']),
            ];
        }

        return [
            'id' => $rate->id,
            'parity' => $rate->parityLabel(),
            'directions' => $directions,
        ];
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
                    'lots' => GameCurrency::label(GameCurrency::KEY_AURAS).' insuficientes para esta troca.',
                ]);
            }

            $balance->auras = (int) $balance->auras - $amount;
            $balance->save();

            return;
        }

        $current = (int) $enrollment->{$currency};
        if ($current < $amount) {
            throw ValidationException::withMessages([
                'lots' => GameCurrency::label($currency).' insuficientes para esta troca.',
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
