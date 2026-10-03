<?php

namespace App\Support\Esenin\Providers;

use App\Jobs\Esenin\FetchTurgenevReportJob;
use App\Support\Esenin\EseninAnalyzer;
use App\Support\Esenin\EseninHtmlHighlighter;
use App\Support\Esenin\EseninMarkMerger;
use App\Support\Esenin\EseninStyleLearning;
use App\Support\EseninTextCheckSettingsRegistry;

final class EseninExternalAnalyzer
{
    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public static function enrich(string $plain, array $words, array $localResult, array $options = []): array
    {
        $providers = [
            'languagetool' => ['ok' => false, 'error' => 'skipped'],
            'turgenev' => ['ok' => false, 'error' => 'skipped'],
            'opencorpora' => ['ok' => false, 'error' => 'skipped'],
            'learning' => ['recorded' => 0],
        ];

        $extraMarks = [];

        $lt = LanguageToolClient::check($plain);
        $providers['languagetool'] = [
            'ok' => (bool) ($lt['ok'] ?? false),
            'error' => $lt['error'] ?? null,
            'matches' => count($lt['marks'] ?? []),
            // Не дергать /v2/languages на каждый check — только факт ответа check().
            'available' => (bool) ($lt['ok'] ?? false) || (($lt['error'] ?? null) !== 'disabled'),
        ];
        if (! empty($lt['marks'])) {
            $extraMarks = array_merge($extraMarks, $lt['marks']);
        }

        // Всегда отдаём уже выделенный текст. Параметр url заставляет API
        // качать страницу повторно — запрос в кабинете висит на «Проверяем…».
        $turgenev = TurgenevClient::checkText($plain);
        $providers['turgenev'] = [
            'ok' => (bool) ($turgenev['ok'] ?? false),
            'error' => $turgenev['error'] ?? null,
            'risk' => isset($turgenev['data']['risk']) ? (int) $turgenev['data']['risk'] : null,
            'report_url' => (string) ($turgenev['data']['report_url'] ?? ''),
        ];

        $learning = ['recorded' => 0, 'candidates' => []];
        if (! empty($turgenev['ok']) && is_array($turgenev['data'] ?? null)) {
            $learning = EseninStyleLearning::recordComparison($localResult, $turgenev['data']);
            $localResult = self::blendTurgenevScores($localResult, $turgenev['data']);
            // HTML-отчёты — только в очередь: синхронный fetch держал кнопку «Проверяем» на десятки секунд.
            self::queueReportLearning($turgenev['data']);
        }
        $providers['learning'] = $learning;

        $opencorpora = OpenCorporaClient::findUnknownWords($words);
        $providers['opencorpora'] = [
            'ok' => (bool) ($opencorpora['ok'] ?? false),
            'error' => $opencorpora['error'] ?? null,
            'unknown' => count($opencorpora['unknown'] ?? []),
        ];
        if (! empty($opencorpora['unknown'])) {
            $localResult['metrics']['opencorpora_unknown'] = $opencorpora['unknown'];
        }

        $localResult['providers'] = $providers;
        $localResult['providers_raw'] = [
            'languagetool' => $lt['raw'] ?? [],
            'turgenev' => $turgenev['data'] ?? [],
        ];

        if ($extraMarks !== []) {
            $localResult['marks'] = EseninMarkMerger::merge($localResult['marks'] ?? [], $extraMarks);
            $localResult = self::rebuildHighlights(
                $localResult,
                $plain,
                (string) ($options['source_html'] ?? ''),
                (string) ($options['active_block'] ?? 'risk')
            );
        }

        return $localResult;
    }

    /**
     * @param array<string, mixed> $result
     * @param array<string, mixed> $turgenevData
     * @return array<string, mixed>
     */
    private static function blendTurgenevScores(array $result, array $turgenevData): array
    {
        $cfg = EseninTextCheckSettingsRegistry::provider('turgenev');
        // 100% = брать баллы внешнего отчёта как есть (не разбавлять «высокий риск»).
        $blend = max(0, min(100, (int) ($cfg['score_blend_percent'] ?? 100)));

        if ($blend <= 0) {
            $result['turgenev'] = $turgenevData;

            return $result;
        }

        $remoteDetails = [];
        foreach ($turgenevData['details'] ?? [] as $row) {
            if (! is_array($row)) {
                continue;
            }
            $remoteDetails[(string) ($row['block'] ?? '')] = $row;
        }

        $details = [];
        $totalRisk = 0;
        foreach ($result['details'] ?? [] as $row) {
            if (! is_array($row)) {
                continue;
            }

            $block = (string) ($row['block'] ?? '');
            $localSum = (int) ($row['sum'] ?? 0);
            $remoteSum = (int) ($remoteDetails[$block]['sum'] ?? 0);
            $blended = (int) round($localSum * (1 - $blend / 100) + $remoteSum * ($blend / 100));

            $merged = $row;
            $merged['sum'] = $blended;
            $merged['local_sum'] = $localSum;
            $merged['turgenev_sum'] = $remoteSum;
            if (! empty($remoteDetails[$block]['link'])) {
                $merged['turgenev_link'] = (string) $remoteDetails[$block]['link'];
            }
            if (! empty($remoteDetails[$block]['params'])) {
                $merged['turgenev_params'] = $remoteDetails[$block]['params'];
            }

            $details[] = $merged;
            $totalRisk += $blended;
        }

        $result['details'] = $details;
        // Общий риск = сумма вкладок (не отдельный mix итогов — иначе 6 при сумме 7).
        $result['risk'] = $totalRisk;
        $result['level'] = EseninAnalyzer::levelFromScore((int) $result['risk']);
        $result['turgenev'] = $turgenevData;

        $blocks = $result['blocks'] ?? [];
        foreach ($details as $detail) {
            $block = (string) ($detail['block'] ?? '');
            if ($block === '' || ! isset($blocks[$block])) {
                continue;
            }
            $blocks[$block]['score'] = (int) ($detail['sum'] ?? 0);
        }
        $result['blocks'] = $blocks;

        return $result;
    }

    /**
     * @param array<string, mixed> $turgenevData
     */
    private static function queueReportLearning(array $turgenevData): void
    {
        $cfg = EseninTextCheckSettingsRegistry::learningConfig();
        if (empty($cfg['enabled']) || empty($cfg['report_fetch_enabled'])) {
            return;
        }

        $blocks = is_array($cfg['report_blocks'] ?? null) ? $cfg['report_blocks'] : ['style', 'readability'];
        $tokens = TurgenevReportParser::reportTokensFromData($turgenevData, $blocks);
        if ($tokens === []) {
            return;
        }

        FetchTurgenevReportJob::dispatch($tokens);
    }

    /**
     * @param array<string, mixed> $result
     * @return array<string, mixed>
     */
    private static function rebuildHighlights(array $result, string $plain, string $sourceHtml, string $activeBlock = 'risk'): array
    {
        $marks = $result['marks'] ?? [];
        $highlights = [];
        foreach (array_merge(['risk'], array_keys(EseninAnalyzer::BLOCK_LABELS)) as $blockKey) {
            if ($sourceHtml !== '' && EseninHtmlHighlighter::isHtml($sourceHtml)) {
                $highlights[$blockKey] = EseninHtmlHighlighter::apply($sourceHtml, $plain, $marks, $blockKey);
            } else {
                $highlights[$blockKey] = EseninAnalyzer::renderHighlightedPlainHtml($plain, $marks, $blockKey);
            }
        }

        $result['highlights'] = $highlights;
        $result['highlighted_html'] = $highlights[$activeBlock] ?? ($highlights['risk'] ?? '');

        return $result;
    }
}
