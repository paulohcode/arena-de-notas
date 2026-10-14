<?php

namespace App\Support;

class ForgeCatalog
{
    public const FEE_RATE = 0.15;

    public const MIN_INPUTS = 2;

    /**
     * @var list<string>
     */
    public const COSMETIC_TONES = [
        'bronze',
        'silver',
        'gold',
        'rune',
        'aurora',
        'ember',
        'frost',
        'storm',
        'vigil',
    ];

    /**
     * @var list<string>
     */
    public const PET_AURA_TONES = [
        'ember',
        'frost',
        'storm',
        'vigil',
        'aurora',
        'shadow',
        'bloom',
    ];

    public static function calculateFee(int $catalogPriceSum): int
    {
        return max(1, (int) ceil($catalogPriceSum * self::FEE_RATE));
    }

    public static function levelLabel(int $level): string
    {
        return 'Nível '.$level;
    }

    public static function toneForCosmeticLevel(int $level, ?string $baseTone = null): string
    {
        $tones = self::COSMETIC_TONES;
        $baseIndex = 0;

        if ($baseTone !== null) {
            $found = array_search($baseTone, $tones, true);
            if ($found !== false) {
                $baseIndex = (int) $found;
            }
        }

        $offset = max(0, $level - 1);
        $index = ($baseIndex + $offset) % count($tones);

        return $tones[$index];
    }

    public static function auraForPetLevel(int $level, ?string $baseAura = null): string
    {
        $tones = self::PET_AURA_TONES;
        $baseIndex = 0;

        if ($baseAura !== null) {
            $found = array_search($baseAura, $tones, true);
            if ($found !== false) {
                $baseIndex = (int) $found;
            }
        }

        $offset = max(0, $level - 1);
        $index = ($baseIndex + $offset) % count($tones);

        return $tones[$index];
    }
}
