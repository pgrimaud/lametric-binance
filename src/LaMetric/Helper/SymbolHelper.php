<?php

declare(strict_types=1);

namespace LaMetric\Helper;

class SymbolHelper
{
    public const SYMBOLS = [
        'USD' => '$',
        'EUR' => '€',
        'GBP' => '£',
        'JPY' => '¥',
        'SEK' => 'kr'
    ];

    /**
     * @param string $code
     *
     * @return string
     */
    public static function getSymbol(string $code): string
    {
        return self::SYMBOLS[strtoupper($code)] ?? '';
    }
}
