<?php

namespace App\Services;

use App\Models\Area;
use App\Models\CurrencyExchange;
use App\Models\CurrencyTrade;
use App\Models\ExchangeRaffle;
use App\Models\GameCurrency;
use Carbon\CarbonInterface;

class ExchangeReportService
{
    public function __construct(private DailyReportService $dailyReports) {}

    /**
     * @return array{
     *     day: CarbonInterface,
     *     day_label: string,
     *     area: ?Area,
     *     summary: array{
     *         house_purchases: int,
     *         peer_trades: int,
     *         fees_relics: int,
     *         fees_seals: int,
     *         fees_auras: int,
     *         raffles: int
     *     },
     *     house_purchases: list<array{
     *         created_at: string,
     *         student_name: string,
     *         class_name: string,
     *         area_name: string,
     *         pay_label: string,
     *         receive_label: string,
     *         lots: int
     *     }>,
     *     peer_trades: list<array{
     *         created_at: string,
     *         seller_name: string,
     *         buyer_name: string,
     *         seller_class: string,
     *         buyer_class: string,
     *         area_name: string,
     *         offer_label: string,
     *         ask_label: string,
     *         fee_label: string
     *     }>,
     *     raffles: list<array{
     *         created_at: string,
     *         area_name: string,
     *         winner_name: string,
     *         prize_label: string
     *     }>
     * }
     */
    public function forDay(CarbonInterface $day, ?Area $area = null): array
    {
        [$startUtc, $endUtc] = $this->dailyReports->utcWindowForDisplayDay($day);
        $tz = (string) config('app.display_timezone');

        $house = $this->housePurchases($startUtc, $endUtc, $area, $tz);
        $peers = $this->peerTrades($startUtc, $endUtc, $area, $tz);
        $raffles = $this->raffles($startUtc, $endUtc, $area, $tz);

        $feesRelics = 0;
        $feesSeals = 0;
        $feesAuras = 0;
        foreach ($peers as $row) {
            $feesRelics += $row['fee_relics'];
            $feesSeals += $row['fee_seals'];
            $feesAuras += $row['fee_auras'];
        }

        return [
            'day' => $day->copy()->timezone($tz)->startOfDay(),
            'day_label' => $day->copy()->timezone($tz)->format('d/m/Y'),
            'area' => $area,
            'summary' => [
                'house_purchases' => count($house),
                'peer_trades' => count($peers),
                'fees_relics' => $feesRelics,
                'fees_seals' => $feesSeals,
                'fees_auras' => $feesAuras,
                'raffles' => count($raffles),
            ],
            'house_purchases' => array_map(static fn (array $row): array => [
                'created_at' => $row['created_at'],
                'student_name' => $row['student_name'],
                'class_name' => $row['class_name'],
                'area_name' => $row['area_name'],
                'pay_label' => $row['pay_label'],
                'receive_label' => $row['receive_label'],
                'lots' => $row['lots'],
            ], $house),
            'peer_trades' => array_map(static fn (array $row): array => [
                'created_at' => $row['created_at'],
                'seller_name' => $row['seller_name'],
                'buyer_name' => $row['buyer_name'],
                'seller_class' => $row['seller_class'],
                'buyer_class' => $row['buyer_class'],
                'area_name' => $row['area_name'],
                'offer_label' => $row['offer_label'],
                'ask_label' => $row['ask_label'],
                'fee_label' => $row['fee_label'],
            ], $peers),
            'raffles' => $raffles,
        ];
    }

    /**
     * @return list<array{
     *     created_at: string,
     *     student_name: string,
     *     class_name: string,
     *     area_name: string,
     *     pay_label: string,
     *     receive_label: string,
     *     lots: int
     * }>
     */
    private function housePurchases(CarbonInterface $startUtc, CarbonInterface $endUtc, ?Area $area, string $tz): array
    {
        $query = CurrencyExchange::query()
            ->with(['student', 'schoolClass.area', 'area'])
            ->whereBetween('created_at', [$startUtc, $endUtc])
            ->orderByDesc('id');

        if ($area) {
            $query->where(function ($builder) use ($area): void {
                $builder->where('area_id', $area->id)
                    ->orWhereHas('schoolClass', fn ($q) => $q->where('area_id', $area->id));
            });
        }

        return $query->get()->map(function (CurrencyExchange $row) use ($tz): array {
            return [
                'created_at' => $row->created_at?->timezone($tz)->format('H:i') ?? '—',
                'student_name' => $row->student?->name ?? '—',
                'class_name' => $row->schoolClass?->name ?? '—',
                'area_name' => $row->schoolClass?->area?->name
                    ?? ($row->area?->name ?? '—'),
                'pay_label' => GameCurrency::format($row->pay_currency, $row->pay_amount),
                'receive_label' => GameCurrency::format($row->receive_currency, $row->receive_amount),
                'lots' => (int) $row->lots,
            ];
        })->all();
    }

    /**
     * @return list<array{
     *     created_at: string,
     *     seller_name: string,
     *     buyer_name: string,
     *     seller_class: string,
     *     buyer_class: string,
     *     area_name: string,
     *     offer_label: string,
     *     ask_label: string,
     *     fee_label: string,
     *     fee_relics: int,
     *     fee_seals: int,
     *     fee_auras: int
     * }>
     */
    private function peerTrades(CarbonInterface $startUtc, CarbonInterface $endUtc, ?Area $area, string $tz): array
    {
        $query = CurrencyTrade::query()
            ->with(['seller', 'buyer', 'sellerClass', 'buyerClass', 'area'])
            ->whereBetween('created_at', [$startUtc, $endUtc])
            ->orderByDesc('id');

        if ($area) {
            $query->where('area_id', $area->id);
        }

        return $query->get()->map(function (CurrencyTrade $row) use ($tz): array {
            return [
                'created_at' => $row->created_at?->timezone($tz)->format('H:i') ?? '—',
                'seller_name' => $row->seller?->name ?? '—',
                'buyer_name' => $row->buyer?->name ?? '—',
                'seller_class' => $row->sellerClass?->name ?? '—',
                'buyer_class' => $row->buyerClass?->name ?? '—',
                'area_name' => $row->area?->name ?? '—',
                'offer_label' => GameCurrency::format($row->offer_currency, $row->offer_amount),
                'ask_label' => GameCurrency::format($row->ask_currency, $row->ask_amount),
                'fee_label' => GameCurrency::format($row->fee_currency, $row->fee_amount),
                'fee_relics' => $row->fee_currency === GameCurrency::KEY_RELICS ? (int) $row->fee_amount : 0,
                'fee_seals' => $row->fee_currency === GameCurrency::KEY_SEALS ? (int) $row->fee_amount : 0,
                'fee_auras' => $row->fee_currency === GameCurrency::KEY_AURAS ? (int) $row->fee_amount : 0,
            ];
        })->all();
    }

    /**
     * @return list<array{created_at: string, area_name: string, winner_name: string, prize_label: string}>
     */
    private function raffles(CarbonInterface $startUtc, CarbonInterface $endUtc, ?Area $area, string $tz): array
    {
        $query = ExchangeRaffle::query()
            ->with(['area', 'winner'])
            ->whereBetween('created_at', [$startUtc, $endUtc])
            ->orderByDesc('id');

        if ($area) {
            $query->where('area_id', $area->id);
        }

        return $query->get()->map(function (ExchangeRaffle $row) use ($tz): array {
            $parts = [];
            if ((int) $row->relics > 0) {
                $parts[] = GameCurrency::format('relics', $row->relics);
            }
            if ((int) $row->seals > 0) {
                $parts[] = GameCurrency::format('seals', $row->seals);
            }
            if ((int) $row->auras > 0) {
                $parts[] = GameCurrency::format('auras', $row->auras);
            }

            return [
                'created_at' => $row->created_at?->timezone($tz)->format('H:i') ?? '—',
                'area_name' => $row->area?->name ?? '—',
                'winner_name' => $row->winner?->name ?? '—',
                'prize_label' => $parts === [] ? 'pote vazio' : implode(' + ', $parts),
            ];
        })->all();
    }
}
