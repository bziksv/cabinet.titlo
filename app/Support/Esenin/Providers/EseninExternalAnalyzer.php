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
            'available' => LanguageToolClient::isAvailable(),
        ];
        if (! empty($lt['marks'])) {
            $extraMarks = array_merge($extraMarks, $lt['marks']);
        }

        $turgenevOptions = [];
        if (! empty($options['url'])) {
            $turgenevOptions['url'] = (string) $options['url'];
            if (! empty($options['tbclass'])) {
                $turgenevOptions['tbclass'] = (string) $options['tbclass'];
            }
        }

        $turgenev = TurgenevClient::checkText($plain, $turgenevOptions);
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
            self::queueReportLearning($turgenev['data']);

            $learningCfg = EseninTextCheckSettingsRegistry::learningConfig();
            $reportBlocks = is_array($learningCfg['report_blocks'] ?? null)
                ? $learningCfg['report_blocks']
                : ['style', 'readability'];
            if (! empty($learningCfg['report_fetch_enabled'])) {
                $reportMarks = TurgenevReportParser::marksFromTurgenevData($plain, $turgenev['data'], $reportBlocks);
                if ($reportMarks !== []) {
                    $extraMarks = array_merge($extraMarks, $reportMarks);
                }
            }
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
        $blend = max(0, min(100, (int) ($cfg['score_blend_percent'] ?? 50)));

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
        $result['risk'] = isset($turgenevData['risk'])
            ? (int) round(((int) $result['risk']) * (1 - $blend / 100) + ((int) $turgenevData['risk']) * ($blend / 100))
            : $totalRisk;
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
