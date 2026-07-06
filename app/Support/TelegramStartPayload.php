<?php

namespace App\Support;

class TelegramStartPayload
{
    /**
     * Telegram deep-link start: only A-Z, a-z, 0-9, _ (no trailing =).
     */
    public static function encodeEmail(string $email): string
    {
        return rtrim(strtr(base64_encode($email), '+/', '-_'), '=');
    }

    public static function decodeEmail(string $payload): ?string
    {
        $payload = trim($payload);
        if ($payload === '') {
            return null;
        }

        $b64 = strpos($payload, '-') !== false || strpos($payload, '_') !== false
            ? strtr($payload, '-_', '+/')
            : $payload;

        $pad = strlen($b64) % 4;
        if ($pad !== 0) {
            $b64 .= str_repeat('=', 4 - $pad);
        }

        $decoded = base64_decode($b64, true);

        return is_string($decoded) && $decoded !== '' ? $decoded : null;
    }
}
