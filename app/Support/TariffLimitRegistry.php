<?php

namespace App\Support;

use Illuminate\Support\Collection;

/**
 * Справочник кодов лимитов из PHP (LimitsComposer, проверки в контроллерах).
 * Не совпадает 1:1 с записями в tariff_settings — свойство может отсутствовать в БД.
 */
class TariffLimitRegistry
{
    public const ENFORCEMENT_STRICT = 'strict';
    public const ENFORCEMENT_POSITIVE_ONLY = 'positive_only';
    public const ENFORCEMENT_DISPLAY_ONLY = 'display_only';

    /** @return array<int, array{code: string, module: string, hint: string, enforcement: string}> */
    public static function entries(): array
    {
        return [
            [
                'code' => 'RelevanceAnalysis',
                'module' => __('Relevance analysis'),
                'hint' => __('Checks per month (Relevance / HistoryRelevance).'),
                'enforcement' => self::ENFORCEMENT_STRICT,
            ],
            [
                'code' => 'TextAnalyzer',
                'module' => __('Text analyzer'),
                'hint' => __('Analyses per calendar month.'),
                'enforcement' => self::ENFORCEMENT_STRICT,
            ],
            [
                'code' => 'CompetitorAnalysisPhrases',
                'module' => __('Competitor analysis (phrases)'),
                'hint' => __('Limit applies only if value in DB is greater than 0.'),
                'enforcement' => self::ENFORCEMENT_POSITIVE_ONLY,
            ],
            [
                'code' => 'Clusters',
                'module' => __('Clustering'),
                'hint' => __('Clustering requests per month.'),
                'enforcement' => self::ENFORCEMENT_STRICT,
            ],
            [
                'code' => 'domainMonitoringProject',
                'module' => __('Domain monitoring (projects)'),
                'hint' => __('Number of monitoring projects.'),
                'enforcement' => self::ENFORCEMENT_STRICT,
            ],
            [
                'code' => 'DomainInformation',
                'module' => __('Domain information'),
                'hint' => __('Saved domain info records.'),
                'enforcement' => self::ENFORCEMENT_STRICT,
            ],
            [
                'code' => 'MetaTagsProject',
                'module' => __('Meta tags (projects)'),
                'hint' => __('Meta tag projects per user.'),
                'enforcement' => self::ENFORCEMENT_STRICT,
            ],
            [
                'code' => 'MetaTagsPages',
                'module' => __('Meta tags (pages / history)'),
                'hint' => __('Used for page/history limits in meta module.'),
                'enforcement' => self::ENFORCEMENT_STRICT,
            ],
            [
                'code' => 'monitoring',
                'module' => __('Position monitoring'),
                'hint' => __('Monthly position checks (MonitoringLimit).'),
                'enforcement' => self::ENFORCEMENT_STRICT,
            ],
            [
                'code' => 'BacklinkProject',
                'module' => __('Link tracking (projects)'),
                'hint' => __('Backlink / project tracking projects.'),
                'enforcement' => self::ENFORCEMENT_STRICT,
            ],
            [
                'code' => 'BacklinkLinks',
                'module' => __('Link tracking (links)'),
                'hint' => __('Links inside tracking projects.'),
                'enforcement' => self::ENFORCEMENT_STRICT,
            ],
            [
                'code' => 'PasswordGenerator',
                'module' => __('Password generator'),
                'hint' => __('Shown on tariff page only; usage is not blocked in code.'),
                'enforcement' => self::ENFORCEMENT_DISPLAY_ONLY,
            ],
            [
                'code' => 'TextLength',
                'module' => __('Text length counter'),
                'hint' => __('Shown on tariff page only; usage is not blocked in code.'),
                'enforcement' => self::ENFORCEMENT_DISPLAY_ONLY,
            ],
            [
                'code' => 'ListComparison',
                'module' => __('List comparison'),
                'hint' => __('Shown on tariff page only; usage is not blocked in code.'),
                'enforcement' => self::ENFORCEMENT_DISPLAY_ONLY,
            ],
            [
                'code' => 'UniqueWords',
                'module' => __('Unique words'),
                'hint' => __('Shown on tariff page only; usage is not blocked in code.'),
                'enforcement' => self::ENFORCEMENT_DISPLAY_ONLY,
            ],
            [
                'code' => 'HtmlEditor',
                'module' => __('HTML editor'),
                'hint' => __('Shown on tariff page only; usage is not blocked in code.'),
                'enforcement' => self::ENFORCEMENT_DISPLAY_ONLY,
            ],
            [
                'code' => 'RemoveDublicate',
                'module' => __('Remove duplicates'),
                'hint' => __('Shown on tariff page only; usage is not blocked in code.'),
                'enforcement' => self::ENFORCEMENT_DISPLAY_ONLY,
            ],
            [
                'code' => 'UTM',
                'module' => __('UTM generator'),
                'hint' => __('Shown on tariff page only; usage is not blocked in code.'),
                'enforcement' => self::ENFORCEMENT_DISPLAY_ONLY,
            ],
            [
                'code' => 'ROI',
                'module' => __('ROI calculator'),
                'hint' => __('Shown on tariff page only; usage is not blocked in code.'),
                'enforcement' => self::ENFORCEMENT_DISPLAY_ONLY,
            ],
            [
                'code' => 'HttpHeaders',
                'module' => __('HTTP headers'),
                'hint' => __('Shown on tariff page only; usage is not blocked in code.'),
                'enforcement' => self::ENFORCEMENT_DISPLAY_ONLY,
            ],
            [
                'code' => 'IndexCheck',
                'module' => __('Index check'),
                'hint' => __('Monthly index checks: 1 URL in one search engine = 1 unit.'),
                'enforcement' => self::ENFORCEMENT_STRICT,
            ],
            [
                'code' => 'GeneratorWords',
                'module' => __('Keyword generator'),
                'hint' => __('Shown on tariff page only; usage is not blocked in code.'),
                'enforcement' => self::ENFORCEMENT_DISPLAY_ONLY,
            ],
        ];
    }

    /** @return array<string, array{code: string, module: string, hint: string, enforcement: string}> */
    public static function byCode(): array
    {
        $map = [];
        foreach (self::entries() as $entry) {
            $map[$entry['code']] = $entry;
        }

        return $map;
    }

    /**
     * @param  \Illuminate\Support\Collection|\Illuminate\Database\Eloquent\Collection  $settings
     * @return array{missing: array, orphans: array, configured_codes: array}
     */
    public static function compareWithSettings($settings): array
    {
        $configuredCodes = $settings->pluck('code')->filter()->unique()->values()->all();
        $configuredSet = array_flip($configuredCodes);
        $registry = self::byCode();

        $missing = [];
        foreach (self::entries() as $entry) {
            if (! isset($configuredSet[$entry['code']])) {
                $missing[] = $entry;
            }
        }

        $orphans = [];
        foreach ($settings as $setting) {
            $code = (string) $setting->code;
            if ($code === '' || isset($registry[$code])) {
                continue;
            }
            $orphans[] = [
                'code' => $code,
                'name' => $setting->name,
                'id' => $setting->id,
            ];
        }

        return [
            'missing' => $missing,
            'orphans' => $orphans,
            'configured_codes' => $configuredCodes,
        ];
    }

    public static function enforcementLabel(string $enforcement): string
    {
        switch ($enforcement) {
            case self::ENFORCEMENT_POSITIVE_ONLY:
                return __('Limit if value > 0');
            case self::ENFORCEMENT_DISPLAY_ONLY:
                return __('Tariff page only');
            case self::ENFORCEMENT_STRICT:
            default:
                return __('Enforced in module');
        }
    }
}
