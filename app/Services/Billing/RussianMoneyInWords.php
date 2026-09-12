<?php

namespace App\Services\Billing;

/**
 * Сумма прописью (рубли), без НДС.
 */
class RussianMoneyInWords
{
    public static function rubles(int $amount): string
    {
        $amount = max(0, $amount);
        $rub = self::number($amount);
        $rubUnit = self::morph($amount, 'рубль', 'рубля', 'рублей');

        return mb_strtoupper(mb_substr($rub, 0, 1)) . mb_substr($rub, 1) . ' ' . $rubUnit . ' 00 копеек';
    }

    private static function number(int $n): string
    {
        if ($n === 0) {
            return 'ноль';
        }

        $units = ['', 'один', 'два', 'три', 'четыре', 'пять', 'шесть', 'семь', 'восемь', 'девять'];
        $unitsFem = ['', 'одна', 'две', 'три', 'четыре', 'пять', 'шесть', 'семь', 'восемь', 'девять'];
        $teens = [
            10 => 'десять', 11 => 'одиннадцать', 12 => 'двенадцать', 13 => 'тринадцать', 14 => 'четырнадцать',
            15 => 'пятнадцать', 16 => 'шестнадцать', 17 => 'семнадцать', 18 => 'восемнадцать', 19 => 'девятнадцать',
        ];
        $tens = [
            '', '', 'двадцать', 'тридцать', 'сорок', 'пятьдесят',
            'шестьдесят', 'семьдесят', 'восемьдесят', 'девяносто',
        ];
        $hundreds = [
            '', 'сто', 'двести', 'триста', 'четыреста', 'пятьсот',
            'шестьсот', 'семьсот', 'восемьсот', 'девятьсот',
        ];

        $parts = [];
        $groups = [
            [1000000000, 'миллиард', 'миллиарда', 'миллиардов', false],
            [1000000, 'миллион', 'миллиона', 'миллионов', false],
            [1000, 'тысяча', 'тысячи', 'тысяч', true],
            [1, '', '', '', false],
        ];

        foreach ($groups as [$div, $f1, $f2, $f5, $fem]) {
            $chunk = (int) floor($n / $div) % 1000;
            if ($chunk === 0) {
                continue;
            }
            $h = (int) floor($chunk / 100);
            $t = (int) floor(($chunk % 100) / 10);
            $u = $chunk % 10;
            $words = [];
            if ($h > 0) {
                $words[] = $hundreds[$h];
            }
            if ($t === 1) {
                $words[] = $teens[10 + $u];
            } else {
                if ($t > 1) {
                    $words[] = $tens[$t];
                }
                if ($u > 0) {
                    $words[] = $fem ? $unitsFem[$u] : $units[$u];
                }
            }
            if ($f1 !== '') {
                $words[] = self::morph($chunk, $f1, $f2, $f5);
            }
            $parts[] = implode(' ', $words);
        }

        return implode(' ', $parts);
    }

    private static function morph(int $n, string $f1, string $f2, string $f5): string
    {
        $n = abs($n) % 100;
        if ($n > 10 && $n < 20) {
            return $f5;
        }
        $n %= 10;
        if ($n === 1) {
            return $f1;
        }
        if ($n >= 2 && $n <= 4) {
            return $f2;
        }

        return $f5;
    }
}
