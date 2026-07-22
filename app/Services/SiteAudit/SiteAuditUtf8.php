<?php

namespace App\Services\SiteAudit;

/**
 * Чистка строк/массивов перед json_encode (monitoring queries и HTML-мусор).
 */
class SiteAuditUtf8
{
    /**
     * @param mixed $value
     * @return mixed
     */
    public static function scrub($value)
    {
        if (is_string($value)) {
            return self::scrubString($value);
        }
        if (is_array($value)) {
            $out = [];
            foreach ($value as $k => $v) {
                $key = is_string($k) ? self::scrubString($k) : $k;
                $out[$key] = self::scrub($v);
            }

            return $out;
        }

        return $value;
    }

    public static function scrubString(string $value): string
    {
        if ($value === '') {
            return '';
        }
        if (! mb_check_encoding($value, 'UTF-8')) {
            $converted = @mb_convert_encoding($value, 'UTF-8', 'UTF-8, Windows-1251, ISO-8859-1, ASCII');
            $value = is_string($converted) ? $converted : $value;
        }
        $clean = @iconv('UTF-8', 'UTF-8//IGNORE', $value);

        return is_string($clean) ? $clean : '';
    }
}
