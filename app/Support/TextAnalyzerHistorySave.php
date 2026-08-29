<?php

namespace App\Support;

use App\TextAnalyzer;
use App\TextUniquenessHistory;
use App\User;

/**
 * Сохранение результата анализа текста / Есенина в общую историю (лимит TextUniquenessHistory).
 */
class TextAnalyzerHistorySave
{
    /**
     * @param array<string, mixed> $request
     * @param array<string, mixed> $response
     * @return array{history_id: int|null, warning: string|null}
     */
    public static function maybeSave(array $request, array $response, string $plain, ?User $user = null): array
    {
        $user = $user ?? auth()->user();
        if (! $user || ! TextAnalyzer::shouldSaveUniquenessHistory($request)) {
            return ['history_id' => null, 'warning' => null];
        }
        if (! TextUniquenessLimits::canSaveHistory($user)) {
            return ['history_id' => null, 'warning' => null];
        }

        $uniq = $response['uniqueness'] ?? null;
        $esenin = $response['esenin'] ?? null;
        $general = is_array($response['general'] ?? null) ? $response['general'] : [];

        $uniquenessPct = null;
        $noSignificant = false;
        $hadUniqueness = false;
        if (is_array($uniq) && empty($uniq['error'])) {
            $hadUniqueness = true;
            $uniquenessPct = $uniq['uniqueness_pct'] ?? null;
            $noSignificant = ! empty($uniq['no_significant_matches']);
        }

        $eseninRisk = null;
        $eseninLevel = null;
        $hadEsenin = false;
        if (is_array($esenin) && empty($esenin['error'])) {
            $hadEsenin = true;
            $eseninRisk = isset($esenin['risk']) ? (int) $esenin['risk'] : null;
            $eseninLevel = (string) ($esenin['level'] ?? '');
        }

        $chars = (int) ($general['textLength'] ?? ($uniq['chars'] ?? mb_strlen($plain)));
        $words = (int) ($general['countWordsAll'] ?? ($general['countWords'] ?? 0));

        $title = TextAnalyzerUniqueness::historyTitle($request, $plain);

        // Полный снимок анализатора + то, что реально включили галками.
        $results = [
            'analysis' => self::analysisSnapshotForStorage($response),
        ];
        if ($hadUniqueness) {
            $results['uniqueness'] = $uniq;
        }
        if ($hadEsenin) {
            // В response['esenin'] уже summarize() с подсветкой — сохраняем целиком.
            $results['esenin'] = self::eseninSnapshotForStorage($esenin);
        }

        $history = TextUniquenessHistory::query()->create([
            'user_id' => $user->id,
            'title' => $title,
            'mode' => 'internet',
            'params' => [
                'source' => 'text-analyzer',
                'type' => $request['type'] ?? 'text',
                'url' => $request['url'] ?? null,
                'competitorUrl' => $request['competitorUrl'] ?? null,
                'chars' => $chars,
                'words' => $words,
                'uniqueness_pct' => $uniquenessPct,
                'no_significant_matches' => $noSignificant,
                'esenin_risk' => $eseninRisk,
                'esenin_level' => $eseninLevel,
                'had_analysis' => true,
                'had_uniqueness' => $hadUniqueness,
                'had_esenin' => $hadEsenin,
                'force_compare_urls' => TextAnalyzer::uniquenessForceCompareUrls($request),
                'exclude_hosts' => TextAnalyzer::uniquenessExcludeHosts($request),
                'plain' => self::plainForStorage($plain),
                'textarea' => self::textareaForStorage($request),
                'form' => self::formFlagsForStorage($request),
                'general' => [
                    'countWordsAll' => $general['countWordsAll'] ?? null,
                    'countStopWords' => $general['countStopWords'] ?? null,
                    'countWordsWithoutStopWords' => $general['countWordsWithoutStopWords'] ?? null,
                    'textLength' => $general['textLength'] ?? null,
                    'lengthWithOutSpaces' => $general['lengthWithOutSpaces'] ?? null,
                ],
            ],
            'results' => $results,
            'uniqueness_pct' => $uniquenessPct ?? 0,
            'cost' => (int) (($uniq['cost'] ?? 0) + ($esenin['cost'] ?? 0)),
        ]);

        TextUniquenessLimits::pruneHistory($user);

        return ['history_id' => $history->id, 'warning' => null];
    }

    /**
     * Сохранение проверки из модуля /esenin-text-check в ту же историю.
     *
     * @param array<string, mixed> $result
     * @param array<string, mixed> $meta
     * @return array{history_id: int|null, warning: string|null}
     */
    public static function maybeSaveFromEsenin(string $plain, array $result, array $meta = [], ?User $user = null, bool $wantSave = true): array
    {
        $user = $user ?? auth()->user();
        if (! $wantSave || ! $user) {
            return ['history_id' => null, 'warning' => null];
        }
        if (! TextUniquenessLimits::canSaveHistory($user)) {
            return ['history_id' => null, 'warning' => null];
        }
        if (! empty($result['error'])) {
            return ['history_id' => null, 'warning' => null];
        }

        $eseninRisk = isset($result['risk']) ? (int) $result['risk'] : null;
        $eseninLevel = (string) ($result['level'] ?? '');
        $stats = is_array($result['stats'] ?? null) ? $result['stats'] : [];
        $chars = (int) ($stats['chars'] ?? mb_strlen($plain));
        $words = (int) ($stats['words'] ?? 0);

        $name = trim((string) ($meta['name'] ?? ''));
        if ($name !== '') {
            $title = mb_strlen($name) > 80 ? mb_substr($name, 0, 80) . '…' : $name;
        } else {
            $title = TextAnalyzerUniqueness::historyTitle([
                'type' => ($meta['source'] ?? 'text') === 'url' ? 'url' : 'text',
                'url' => $meta['url'] ?? null,
            ], $plain);
        }

        $eseninSnapshot = self::eseninSnapshotForStorage($result);

        $history = TextUniquenessHistory::query()->create([
            'user_id' => $user->id,
            'title' => $title !== '' ? $title : __('Esenin text check'),
            'mode' => 'internet',
            'params' => [
                'source' => 'esenin-text-check',
                'type' => ($meta['source'] ?? 'text') === 'url' ? 'url' : 'text',
                'url' => $meta['url'] ?? null,
                'tbclass' => $meta['tbclass'] ?? null,
                'chars' => $chars,
                'words' => $words,
                'uniqueness_pct' => null,
                'esenin_risk' => $eseninRisk,
                'esenin_level' => $eseninLevel,
                'had_analysis' => false,
                'had_uniqueness' => false,
                'had_esenin' => $eseninRisk !== null,
                'plain' => self::plainForStorage($plain),
                'task_name' => $name !== '' ? $name : null,
            ],
            'results' => [
                'esenin' => $eseninSnapshot,
            ],
            'uniqueness_pct' => 0,
            'cost' => (int) ($result['cost'] ?? 0),
        ]);

        TextUniquenessLimits::pruneHistory($user);

        return ['history_id' => $history->id, 'warning' => null];
    }

    /**
     * Собрать $response для повторного показа страницы анализатора.
     *
     * @return array<string, mixed>|null
     */
    public static function responseFromHistory(TextUniquenessHistory $row): ?array
    {
        $results = is_array($row->results) ? $row->results : [];
        $params = is_array($row->params) ? $row->params : [];

        $analysis = $results['analysis'] ?? null;
        if (! is_array($analysis) || $analysis === []) {
            // Старые записи без полного анализа — только уникальность/Есенин.
            if (! empty($results['uniqueness']) || ! empty($results['esenin'])) {
                $response = [];
                if (! empty($results['uniqueness']) && is_array($results['uniqueness'])) {
                    $response['uniqueness'] = $results['uniqueness'];
                }
                if (! empty($results['esenin']) && is_array($results['esenin'])) {
                    $response['esenin'] = $results['esenin'];
                }
                if (! empty($params['general']) && is_array($params['general'])) {
                    $response['general'] = $params['general'];
                }
                $response['totalWords'] = $response['totalWords'] ?? [];
                $response['phrases'] = $response['phrases'] ?? [];
                $response['graph'] = $response['graph'] ?? [];
                $response['clouds'] = $response['clouds'] ?? ['text' => [], 'links' => [], 'both' => []];

                return $response;
            }

            return null;
        }

        $response = $analysis;
        if (! empty($results['uniqueness']) && is_array($results['uniqueness'])) {
            $response['uniqueness'] = $results['uniqueness'];
        }
        if (! empty($results['esenin']) && is_array($results['esenin'])) {
            $response['esenin'] = $results['esenin'];
        }

        return $response;
    }

    /**
     * @return array<string, mixed>
     */
    public static function requestFromHistory(TextUniquenessHistory $row): array
    {
        $params = is_array($row->params) ? $row->params : [];
        $form = is_array($params['form'] ?? null) ? $params['form'] : [];
        $type = (string) ($params['type'] ?? ($form['type'] ?? 'text'));

        $request = array_merge([
            'type' => $type,
            'url' => $params['url'] ?? ($form['url'] ?? ''),
            'textarea' => $params['textarea'] ?? ($params['plain'] ?? ''),
            'competitorUrl' => $params['competitorUrl'] ?? ($form['competitorUrl'] ?? ''),
            'saveUniqueness' => 1,
        ], $form);

        if (($request['textarea'] ?? '') === '' && ! empty($params['plain'])) {
            $request['textarea'] = $params['plain'];
        }

        return $request;
    }

    /**
     * @param array<string, mixed> $response
     * @return array<string, mixed>
     */
    private static function analysisSnapshotForStorage(array $response): array
    {
        $snap = $response;
        // Уникальность и Есенин кладём отдельно в results — в analysis не дублируем огромные куски.
        unset($snap['uniqueness'], $snap['esenin']);

        return $snap;
    }

    /**
     * @param array<string, mixed> $request
     * @return array<string, mixed>
     */
    private static function formFlagsForStorage(array $request): array
    {
        return [
            'type' => $request['type'] ?? 'text',
            'url' => $request['url'] ?? null,
            'competitorUrl' => $request['competitorUrl'] ?? null,
            'compareCompetitor' => $request['compareCompetitor'] ?? null,
            'noIndex' => $request['noIndex'] ?? null,
            'hiddenText' => $request['hiddenText'] ?? null,
            'conjunctionsPrepositionsPronouns' => $request['conjunctionsPrepositionsPronouns'] ?? null,
            'removeWords' => $request['removeWords'] ?? null,
            'listWords' => $request['listWords'] ?? null,
            'checkUniqueness' => $request['checkUniqueness'] ?? null,
            'checkEsenin' => $request['checkEsenin'] ?? null,
            'excludeOwnDomain' => $request['excludeOwnDomain'] ?? null,
            'forceCompareUrls' => $request['forceCompareUrls'] ?? null,
        ];
    }

    /**
     * @param array<string, mixed> $request
     */
    private static function textareaForStorage(array $request): string
    {
        if (($request['type'] ?? '') === 'url') {
            return '';
        }
        $raw = (string) ($request['textarea'] ?? '');
        $max = 200000;
        if (mb_strlen($raw) <= $max) {
            return $raw;
        }

        return mb_substr($raw, 0, $max);
    }

    private static function plainForStorage(string $plain): string
    {
        $plain = trim($plain);
        $max = 50000;
        if (mb_strlen($plain) <= $max) {
            return $plain;
        }

        return mb_substr($plain, 0, $max);
    }

    /**
     * @param array<string, mixed> $result
     * @return array<string, mixed>
     */
    private static function eseninSnapshotForStorage(array $result): array
    {
        unset($result['debug'], $result['raw_providers']);

        return $result;
    }
}
