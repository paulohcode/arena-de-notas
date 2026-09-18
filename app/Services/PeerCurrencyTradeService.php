<?php

namespace App\Services;

use App\Models\Area;
use App\Models\AreaBalance;
use App\Models\CurrencyTrade;
use App\Models\CurrencyTradeListing;
use App\Models\Enrollment;
use App\Models\ExchangeRaffle;
use App\Models\ExchangeVault;
use App\Models\GameCurrency;
use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PeerCurrencyTradeService
{
    public const FEE_RATE = 0.1;

    /**
     * @return array{
     *     available: bool,
     *     open_listings: Collection<int, CurrencyTradeListing>,
     *     my_listings: Collection<int, CurrencyTradeListing>,
     *     vault: ?ExchangeVault
     * }
     */
    public function marketData(User $student, SchoolClass $class): array
    {
        if (! $class->area_id) {
            return [
                'available' => false,
                'open_listings' => collect(),
                'my_listings' => collect(),
                'vault' => null,
            ];
        }

        $open = CurrencyTradeListing::query()
            ->with(['seller', 'sellerClass'])
            ->where('area_id', $class->area_id)
            ->open()
            ->orderByDesc('id')
            ->get();

        $mine = $open->where('seller_id', $student->id)->values();
        $others = $open->where('seller_id', '!=', $student->id)->values();

        return [
            'available' => true,
            'open_listings' => $others,
            'my_listings' => $mine,
            'vault' => ExchangeVault::forArea($class->area),
        ];
    }

    public function createListing(
        User $seller,
        SchoolClass $class,
        string $offerCurrency,
        int $offerAmount,
        string $askCurrency,
        int $askAmount,
    ): CurrencyTradeListing {
        $this->assertRealm($class);
        $this->assertShopCurrency($offerCurrency);
        $this->assertShopCurrency($askCurrency);

        if ($offerCurrency === $askCurrency) {
            throw ValidationException::withMessages([
                'ask_currency' => 'Ofereça e peça moedas diferentes.',
            ]);
        }

        if ($offerAmount < 1 || $askAmount < 1) {
            throw ValidationException::withMessages([
                'offer_amount' => 'Informe quantidades de pelo menos 1.',
            ]);
        }

        return DB::transaction(function () use ($seller, $class, $offerCurrency, $offerAmount, $askCurrency, $askAmount) {
            $enrollment = $this->lockedEnrollment($seller, $class);
            $this->debitCurrency($seller, $class, $enrollment, $offerCurrency, $offerAmount, 'offer_amount');
            $enrollment->save();

            return CurrencyTradeListing::query()->create([
                'seller_id' => $seller->id,
                'seller_class_id' => $class->id,
                'area_id' => $class->area_id,
                'offer_currency' => $offerCurrency,
                'offer_amount' => $offerAmount,
                'ask_currency' => $askCurrency,
                'ask_amount' => $askAmount,
                'status' => CurrencyTradeListing::STATUS_OPEN,
            ]);
        });
    }

    public function cancelListing(User $seller, SchoolClass $class, CurrencyTradeListing $listing): void
    {
        $this->assertRealm($class);

        DB::transaction(function () use ($seller, $class, $listing) {
            $locked = CurrencyTradeListing::query()
                ->whereKey($listing->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ((int) $locked->seller_id !== (int) $seller->id) {
                throw ValidationException::withMessages([
                    'listing' => 'Só o anunciante pode cancelar este anúncio.',
                ]);
            }

            if (! $locked->isOpen()) {
                throw ValidationException::withMessages([
                    'listing' => 'Este anúncio não está mais aberto.',
                ]);
            }

            if ((int) $locked->area_id !== (int) $class->area_id) {
                throw ValidationException::withMessages([
                    'listing' => 'Este anúncio não pertence ao seu reino.',
                ]);
            }

            $sellerClass = SchoolClass::query()->findOrFail($locked->seller_class_id);
            $enrollment = $this->lockedEnrollment($seller, $sellerClass);
            $this->creditCurrency($seller, $sellerClass, $enrollment, $locked->offer_currency, (int) $locked->offer_amount);
            $enrollment->save();

            $locked->status = CurrencyTradeListing::STATUS_CANCELLED;
            $locked->save();
        });
    }

    public function acceptListing(User $buyer, SchoolClass $buyerClass, CurrencyTradeListing $listing): CurrencyTrade
    {
        $this->assertRealm($buyerClass);

        return DB::transaction(function () use ($buyer, $buyerClass, $listing) {
            $locked = CurrencyTradeListing::query()
                ->whereKey($listing->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $locked->isOpen()) {
                throw ValidationException::withMessages([
                    'listing' => 'Este anúncio não está mais disponível.',
                ]);
            }

            if ((int) $locked->seller_id === (int) $buyer->id) {
                throw ValidationException::withMessages([
                    'listing' => 'Você não pode aceitar o próprio anúncio.',
                ]);
            }

            if ((int) $locked->area_id !== (int) $buyerClass->area_id) {
                throw ValidationException::withMessages([
                    'listing' => 'Este anúncio é de outro reino.',
                ]);
            }

            $seller = User::query()->findOrFail($locked->seller_id);
            $sellerClass = SchoolClass::query()->findOrFail($locked->seller_class_id);

            if ((int) $sellerClass->area_id !== (int) $buyerClass->area_id) {
                throw ValidationException::withMessages([
                    'listing' => 'Vendedor e comprador precisam ser do mesmo reino.',
                ]);
            }

            $fee = CurrencyTradeListing::calculateFee((int) $locked->ask_amount);
            $buyerTotal = (int) $locked->ask_amount + $fee;

            $buyerEnrollment = $this->lockedEnrollment($buyer, $buyerClass);
            $sellerEnrollment = $this->lockedEnrollment($seller, $sellerClass);

            $this->debitCurrency($buyer, $buyerClass, $buyerEnrollment, $locked->ask_currency, $buyerTotal, 'listing');
            $this->creditCurrency($seller, $sellerClass, $sellerEnrollment, $locked->ask_currency, (int) $locked->ask_amount);
            $this->creditCurrency($buyer, $buyerClass, $buyerEnrollment, $locked->offer_currency, (int) $locked->offer_amount);

            $buyerEnrollment->save();
            $sellerEnrollment->save();

            $vault = $this->lockedVault($buyerClass->area);
            $vault->deposit($locked->ask_currency, $fee);

            $trade = CurrencyTrade::query()->create([
                'area_id' => $locked->area_id,
                'vault_id' => $vault->id,
                'listing_id' => $locked->id,
                'seller_id' => $seller->id,
                'seller_class_id' => $sellerClass->id,
                'buyer_id' => $buyer->id,
                'buyer_class_id' => $buyerClass->id,
                'offer_currency' => $locked->offer_currency,
                'offer_amount' => $locked->offer_amount,
                'ask_currency' => $locked->ask_currency,
                'ask_amount' => $locked->ask_amount,
                'fee_currency' => $locked->ask_currency,
                'fee_amount' => $fee,
                'created_at' => now(),
            ]);

            $locked->status = CurrencyTradeListing::STATUS_SOLD;
            $locked->buyer_id = $buyer->id;
            $locked->buyer_class_id = $buyerClass->id;
            $locked->sold_at = now();
            $locked->save();

            return $trade;
        });
    }

    /**
     * @return Collection<int, array{area: Area, vault: ExchangeVault, eligible_count: int, raffles: Collection<int, ExchangeRaffle>}>
     */
    public function vaultsForAdmin(): Collection
    {
        return Area::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(function (Area $area): array {
                $vault = ExchangeVault::forArea($area);

                return [
                    'area' => $area,
                    'vault' => $vault,
                    'eligible_count' => $this->eligibleStudentIds($area)->count(),
                    'raffles' => ExchangeRaffle::query()
                        ->with('winner')
                        ->where('area_id', $area->id)
                        ->orderByDesc('id')
                        ->limit(5)
                        ->get(),
                ];
            });
    }

    public function raffle(Area $area, User $admin): ExchangeRaffle
    {
        return DB::transaction(function () use ($area, $admin) {
            $vault = $this->lockedVault($area);

            if ($vault->isEmpty()) {
                throw ValidationException::withMessages([
                    'raffle' => 'O cofre deste reino está vazio.',
                ]);
            }

            $eligibleIds = $this->eligibleStudentIds($area);
            if ($eligibleIds->isEmpty()) {
                throw ValidationException::withMessages([
                    'raffle' => 'Nenhum aluno elegível para o sorteio neste reino.',
                ]);
            }

            $winnerId = (int) $eligibleIds->random();
            $winner = User::query()->findOrFail($winnerId);
            $winnerClass = $this->classInArea($winner, $area);

            if (! $winnerClass) {
                throw ValidationException::withMessages([
                    'raffle' => 'O sorteado não tem turma neste reino para receber o prêmio.',
                ]);
            }

            $prize = $vault->takeAll();
            $enrollment = $this->lockedEnrollment($winner, $winnerClass);

            foreach (GameCurrency::SHOP_KEYS as $currency) {
                $amount = (int) ($prize[$currency] ?? 0);
                if ($amount > 0) {
                    $this->creditCurrency($winner, $winnerClass, $enrollment, $currency, $amount);
                }
            }
            $enrollment->save();

            return ExchangeRaffle::query()->create([
                'area_id' => $area->id,
                'admin_id' => $admin->id,
                'winner_id' => $winner->id,
                'relics' => $prize['relics'],
                'seals' => $prize['seals'],
                'auras' => $prize['auras'],
                'created_at' => now(),
            ]);
        });
    }

    /**
     * @return Collection<int, int>
     */
    public function eligibleStudentIds(Area $area): Collection
    {
        $since = ExchangeRaffle::query()
            ->where('area_id', $area->id)
            ->orderByDesc('id')
            ->value('created_at');

        $query = CurrencyTrade::query()->where('area_id', $area->id);
        if ($since) {
            $query->where('created_at', '>', $since);
        }

        $sellerIds = (clone $query)->distinct()->pluck('seller_id');
        $buyerIds = (clone $query)->distinct()->pluck('buyer_id');

        return $sellerIds->merge($buyerIds)->unique()->values();
    }

    private function assertRealm(SchoolClass $class): void
    {
        if (! $class->area_id) {
            throw ValidationException::withMessages([
                'listing' => 'A negociação entre alunos exige que a turma pertença a um reino.',
            ]);
        }
    }

    private function assertShopCurrency(string $currency): void
    {
        if (! in_array($currency, GameCurrency::SHOP_KEYS, true)) {
            throw ValidationException::withMessages([
                'offer_currency' => 'Moeda inválida para negociação.',
            ]);
        }
    }

    private function lockedEnrollment(User $student, SchoolClass $class): Enrollment
    {
        $enrollment = Enrollment::query()
            ->where('class_id', $class->id)
            ->where('student_id', $student->id)
            ->lockForUpdate()
            ->first();

        if (! $enrollment) {
            throw ValidationException::withMessages([
                'listing' => 'Aluno não matriculado na turma necessária.',
            ]);
        }

        return $enrollment;
    }

    private function lockedVault(Area $area): ExchangeVault
    {
        $vault = ExchangeVault::forArea($area);

        return ExchangeVault::query()->whereKey($vault->id)->lockForUpdate()->firstOrFail();
    }

    private function classInArea(User $student, Area $area): ?SchoolClass
    {
        return $student->classes()
            ->where('classes.area_id', $area->id)
            ->orderBy('classes.name')
            ->first();
    }

    private function debitCurrency(
        User $student,
        SchoolClass $class,
        Enrollment $enrollment,
        string $currency,
        int $amount,
        string $errorKey,
    ): void {
        if ($currency === GameCurrency::KEY_AURAS) {
            $balance = $this->lockedAreaBalance($student, $class);
            if ((int) $balance->auras < $amount) {
                throw ValidationException::withMessages([
                    $errorKey => GameCurrency::label(GameCurrency::KEY_AURAS).' insuficientes.',
                ]);
            }

            $balance->auras = (int) $balance->auras - $amount;
            $balance->save();

            return;
        }

        $current = (int) $enrollment->{$currency};
        if ($current < $amount) {
            throw ValidationException::withMessages([
                $errorKey => GameCurrency::label($currency).' insuficientes.',
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
                'listing' => 'Esta turma não pertence a um reino para usar Aura.',
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
