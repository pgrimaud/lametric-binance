<?php

declare(strict_types=1);

namespace LaMetric\Helper;

class PriceHelper
{
    public static function round(float|int $price): float|int
    {
        if ($price < 10) {
            $fractional = 4;
        } elseif ($price >= 10 && $price < 100) {
            $fractional = 3;
        } elseif ($price >= 100 && $price < 1000) {
            $fractional = 2;
        } elseif ($price >= 1000 && $price < 10000) {
            $fractional = 1;
        } else {
            $fractional = 0;
        }

        return round($price, $fractional);
    }
}