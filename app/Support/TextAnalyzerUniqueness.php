<?php

namespace App\Support;

use App\Services\TextUniquenessService;
use App\TextAnalyzer;
use App\User;

/**
 * Проверка уникальности из модуля «Анализ текста».
 */
class TextAnalyzerUniqueness
{
    /**
     * @param array<string, mixed> $request
     * @param array<string, mixed> $response
     * @return array{response: array<string, mixed>, plain: string, history_id: int|null, history_warning: string|null, uniqueness_error: string|null}
     */
    public static function attach(array $request, array $response, ?User $user = null): array
    {
        $user = $user ?? auth()->user();
        $uniquenessError = null;

        $plain = (string) ($response['general']['plainForUniqueness'] ?? '');
        unset($response['general']['plainForUniqueness']);
        $sourceHtml = TextAnalyzer::sourceHtmlFromRequest($request);
        if ($sourceHtml !== '') {
            // Plain должен совпадать с картой подсветки HTML
            $plain = TextAnalyzer::normalizePlainForUniqueness($sourceHtml);
        }

        if (! TextAnalyzer::shouldCheckUniqueness($request)) {
            return [
                'response' => $response,
                'plain' => $plain,
                'source_html' => $sourceHtml,
                'history_id' => null,
                'history_warning' => null,
                'uniqueness_error' => null,
            ];
        }

        $forceUrls = TextAnalyzer::uniquenessForceCompareUrls($request);
        $params = [
            'mode' => 'internet',
            'text' => $sourceHtml !== '' ? $sourceHtml : $plain,
            'source_html' => $sourceHtml,
            // Поиск фрагментов — служебный канал; ПС/регион не показываем в UI
            'engine' => (string) config('cabinet-text-uniqueness.default_engine', 'yandex'),
            'yandex_lr' => (string) config('cabinet-text-uniqueness.default_yandex_lr', '213'),
            'exclude_hosts' => TextAnalyzer::uniquenessExcludeHosts($request),
            'force_compare_urls' => $forceUrls,
        ];

        $cost = TextUniquenessService::estimateCost($params);
        if (! TextUniquenessLimits::canSpend($cost, $user)) {
            $response['uniqueness'] = [
                'error' => true,
                'message' => TextUniquenessLimits::limitMessage($user) ?: __('Text uniqueness limit exhausted'),
            ];

            return [
                'response' => $response,
                'plain' => $plain,
                'source_html' => $sourceHtml,
                'history_id' => null,
                'history_warning' => null,
                'uniqueness_error' => $response['uniqueness']['message'],
            ];
        }

        try {
            @set_time_limit(600);
            $result = app(TextUniquenessService::class)->analyze($params);
            TextUniquenessLimits::spend((int) $result['cost'], $user);
            $response['uniqueness'] = $result;
        } catch (\InvalidArgumentException $e) {
            $uniquenessError = $e->getMessage();
            $response['uniqueness'] = ['error' => true, 'message' => $uniquenessError];
        } catch (\Throwable $e) {
            report($e);
            $uniquenessError = __('Text uniqueness fetch failed');
            $response['uniqueness'] = ['error' => true, 'message' => $uniquenessError];
        }

        return [
            'response' => $response,
            'plain' => $plain,
            'source_html' => $sourceHtml,
            'history_id' => null,
            'history_warning' => null,
            'uniqueness_error' => $uniquenessError,
        ];
    }

    /**
     * @param array<string, mixed> $request
     */
    public static function historyTitle(array $request, string $plain): string
    {
        // Пакет: у каждого элемента свой label
        if (! empty($request['batchLabel'])) {
            return mb_substr((string) $request['batchLabel'], 0, 120);
        }
        $taskName = trim((string) ($request['taskName'] ?? $request['task_name'] ?? ''));
        if ($taskName !== '') {
            return mb_strlen($taskName) > 120 ? mb_substr($taskName, 0, 120) . '…' : $taskName;
        }
        if (($request['type'] ?? '') === 'url' && ! empty($request['url'])) {
            return mb_substr((string) $request['url'], 0, 120);
        }
        $title = mb_substr($plain, 0, 60);
        if (mb_strlen($plain) > 60) {
            $title .= '…';
        }

        return $title !== '' ? $title : (string) __('Text uniqueness');
    }
}
