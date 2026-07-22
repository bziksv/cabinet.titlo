<?php

namespace App\Services\SiteAudit;

/**
 * 64-bit SimHash по токенам текста (для «похожих страниц»).
 * Хранится как 16 hex-символов.
 */
class SiteAuditSimhash
{
    public static function fromText(string $text): ?string
    {
        $text = mb_strtolower(trim(preg_replace('/\s+/u', ' ', $text)));
        if ($text === '' || mb_strlen($text) < 40) {
            return null;
        }

        $tokens = preg_split('/[^\p{L}\p{N}]+/u', $text, -1, PREG_SPLIT_NO_EMPTY);
        if (! is_array($tokens) || count($tokens) < 8) {
            return null;
        }

        $bits = array_fill(0, 64, 0);
        foreach ($tokens as $token) {
            if (mb_strlen($token) < 2) {
                continue;
            }
            list($hi, $lo) = self::hash64Parts($token);
            for ($i = 0; $i < 32; $i++) {
                $bits[$i] += (($lo >> $i) & 1) ? 1 : -1;
                $bits[$i + 32] += (($hi >> $i) & 1) ? 1 : -1;
            }
        }

        $loOut = 0;
        $hiOut = 0;
        for ($i = 0; $i < 32; $i++) {
            if ($bits[$i] > 0) {
                $loOut |= (1 << $i);
            }
            if ($bits[$i + 32] > 0) {
                $hiOut |= (1 << $i);
            }
        }

        return sprintf('%08x%08x', $hiOut & 0xffffffff, $loOut & 0xffffffff);
    }

    public static function hamming(?string $a, ?string $b): int
    {
        if ($a === null || $b === null || strlen($a) !== 16 || strlen($b) !== 16) {
            return 64;
        }
        if (! ctype_xdigit($a) || ! ctype_xdigit($b)) {
            return 64;
        }

        $binA = @hex2bin($a);
        $binB = @hex2bin($b);
        if ($binA === false || $binB === false || strlen($binA) !== 8) {
            return 64;
        }

        $pa = unpack('N2', $binA);
        $pb = unpack('N2', $binB);
        $x1 = ((int) $pa[1]) ^ ((int) $pb[1]);
        $x2 = ((int) $pa[2]) ^ ((int) $pb[2]);

        return self::popcount32($x1) + self::popcount32($x2);
    }

    private static function popcount32(int $x): int
    {
        $x = $x & 0xffffffff;
        $c = 0;
        while ($x !== 0) {
            $x &= ($x - 1);
            $c++;
            // safety
            if ($c > 32) {
                break;
            }
        }

        return $c;
    }

    /**
     * @return int[] [hi, lo] unsigned 32-bit
     */
    private static function hash64Parts(string $token): array
    {
        $h1 = crc32($token);
        $h2 = crc32(strrev($token) . '#sa');
        // crc32 returns signed on some platforms
        return [$h1 & 0xffffffff, $h2 & 0xffffffff];
    }
}
