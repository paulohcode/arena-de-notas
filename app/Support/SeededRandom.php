<?php

namespace App\Support;

/**
 * Gerador pseudoaleatório determinístico (LCG) para combates com seed.
 */
class SeededRandom
{
    private int $state;

    public function __construct(int $seed)
    {
        $this->state = $seed > 0 ? $seed : 1;
    }

    public function nextFloat(): float
    {
        $this->state = (int) (($this->state * 1103515245 + 12345) & 0x7FFFFFFF);

        return $this->state / 0x7FFFFFFF;
    }

    public function nextInt(int $min, int $max): int
    {
        if ($max < $min) {
            [$min, $max] = [$max, $min];
        }

        return $min + (int) floor($this->nextFloat() * ($max - $min + 1));
    }
}
