<?php

namespace App\Support;

use App\DomainInformation;
use Carbon\Carbon;

class DomainInformationDisplay
{
    /**
     * Дней до окончания регистрации из сохранённого блока WHOIS (null — неизвестно).
     */
    public static function daysUntilExpiry(DomainInformation $project): ?int
    {
        $block = self::registrationBlock($project);
        if ($block === '—' || trim($block) === '') {
            return null;
        }

        if (preg_match('/' . preg_quote(__('through'), '/') . '\s+(\d+)\s+' . preg_quote(__('days'), '/') . '/u', $block, $m)) {
            return (int) $m[1];
        }

        if (preg_match('/(\d{4}-\d{2}-\d{2})/', $block, $dateMatch)) {
            try {
                $expiry = Carbon::parse($dateMatch[1])->startOfDay();

                return (int) $expiry->diffInDays(Carbon::now()->startOfDay());
            } catch (\Exception $e) {
                return null;
            }
        }

        return null;
    }

    public static function dnsBlock(DomainInformation $project): string
    {
        if ($project->dns !== null && trim((string) $project->dns) !== '') {
            return (string) $project->dns;
        }

        return self::extractDnsFromLegacy((string) $project->domain_information) ?: '—';
    }

    public static function registrationBlock(DomainInformation $project): string
    {
        $info = trim((string) ($project->domain_information ?? ''));

        if ($info === '') {
            return '—';
        }

        if (self::looksLikeLegacyCombined($info)) {
            $parts = preg_split("/\n\n+/", $info, 2);

            return trim($parts[1] ?? $info);
        }

        return $info;
    }

    protected static function looksLikeLegacyCombined(string $info): bool
    {
        return stripos($info, 'DNS:') === 0;
    }

    protected static function extractDnsFromLegacy(string $info): ?string
    {
        if (!self::looksLikeLegacyCombined($info)) {
            return null;
        }

        $parts = preg_split("/\n\n+/", $info, 2);
        $dnsPart = trim($parts[0] ?? '');

        return $dnsPart !== '' ? $dnsPart : null;
    }
}
