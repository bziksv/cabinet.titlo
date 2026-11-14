<?php

namespace App\Services\SiteAudit;

use App\SiteAuditCrawl;
use App\SiteAuditFinding;
use Illuminate\Support\Facades\Log;

/**
 * План работ (to-do) из findings аудита.
 * База — правила + help «как исправить»; опционально полировка DeepSeek.
 */
class SiteAuditActionPlanBuilder
{
    private const SEV_ORDER = [
        'critical' => 0,
        'other' => 1,
        'warning' => 2,
        'info' => 3,
    ];

    /**
     * @return array{
     *   generated_at:string,
     *   source:string,
     *   domain:?string,
     *   crawl_id:int,
     *   items:array<int,array<string,mixed>>,
     *   ai_summary:?string,
     *   markdown:string
     * }
     */
    public function build(SiteAuditCrawl $crawl, bool $withAi = false): array
    {
        $catalog = config('site_audit.findings', []);
        $counts = is_array($crawl->counts_json) ? $crawl->counts_json : [];
        $max = max(5, (int) config('site_audit.action_plan_max_items', 25));

        $candidates = [];
        foreach ($counts as $code => $count) {
            $count = (int) $count;
            if ($count < 1 || ! is_string($code)) {
                continue;
            }
            $meta = is_array($catalog[$code] ?? null) ? $catalog[$code] : [];
            if (! empty($meta['virtual'])) {
                continue;
            }
            $sev = (string) ($meta['severity'] ?? 'info');
            $candidates[] = [
                'code' => $code,
                'count' => $count,
                'severity' => $sev,
                'title' => (string) ($meta['title'] ?? $code),
                'group' => (string) ($meta['group'] ?? 'tech'),
                'ord' => self::SEV_ORDER[$sev] ?? 9,
            ];
        }

        usort($candidates, static function ($a, $b) {
            if ($a['ord'] !== $b['ord']) {
                return $a['ord'] <=> $b['ord'];
            }
            if ($a['count'] !== $b['count']) {
                return $b['count'] <=> $a['count'];
            }

            return strcmp($a['code'], $b['code']);
        });

        $candidates = array_slice($candidates, 0, $max);
        $samplesByCode = $this->sampleUrls($crawl->id, array_column($candidates, 'code'));

        $items = [];
        $prio = 1;
        foreach ($candidates as $row) {
            $help = SiteAuditFindingHelp::forCode($row['code'], $catalog[$row['code']] ?? []);
            $items[] = [
                'id' => $row['code'],
                'priority' => $prio++,
                'code' => $row['code'],
                'severity' => $row['severity'],
                'title' => $row['title'],
                'group' => $row['group'],
                'count' => $row['count'],
                'what' => (string) ($help['what'] ?? ''),
                'why' => (string) ($help['why'] ?? ''),
                'how' => (string) ($help['how'] ?? 'Разберите URL и устраните причину.'),
                'sample_urls' => $samplesByCode[$row['code']] ?? [],
                'done' => false,
            ];
        }

        // сохранить done из предыдущего плана по code
        $prev = is_array($crawl->progress_json['action_plan']['items'] ?? null)
            ? $crawl->progress_json['action_plan']['items']
            : [];
        $prevDone = [];
        foreach ($prev as $p) {
            if (! empty($p['code']) && ! empty($p['done'])) {
                $prevDone[(string) $p['code']] = true;
            }
        }
        foreach ($items as &$it) {
            if (! empty($prevDone[$it['code']])) {
                $it['done'] = true;
            }
        }
        unset($it);

        $domain = optional($crawl->project)->domain;
        $plan = [
            'generated_at' => now()->toDateTimeString(),
            'source' => 'rules',
            'domain' => $domain,
            'crawl_id' => (int) $crawl->id,
            'items' => $items,
            'ai_summary' => null,
            'markdown' => '',
        ];
        $plan['markdown'] = $this->toMarkdown($plan);

        if ($withAi && $items !== []) {
            $ai = $this->polishWithAi($plan);
            if ($ai !== null) {
                $plan['source'] = 'rules+ai';
                $plan['ai_summary'] = $ai;
                $plan['markdown'] = $this->toMarkdown($plan);
            }
        }

        return $plan;
    }

    /**
     * @param string[] $codes
     * @return array<string,string[]>
     */
    private function sampleUrls(int $crawlId, array $codes): array
    {
        if ($codes === []) {
            return [];
        }
        $out = [];
        $rows = SiteAuditFinding::query()
            ->where('crawl_id', $crawlId)
            ->whereIn('code', $codes)
            ->orderBy('id')
            ->limit(200)
            ->get(['code', 'url']);

        foreach ($rows as $row) {
            $code = (string) $row->code;
            if (! isset($out[$code])) {
                $out[$code] = [];
            }
            if (count($out[$code]) >= 3) {
                continue;
            }
            $url = trim((string) $row->url);
            if ($url !== '' && ! in_array($url, $out[$code], true)) {
                $out[$code][] = $url;
            }
        }

        return $out;
    }

    /**
     * @param array<string,mixed> $plan
     */
    public function toMarkdown(array $plan): string
    {
        $domain = (string) ($plan['domain'] ?? '');
        $lines = [];
        $lines[] = '# План работ по аудиту сайта'
            . ($domain !== '' ? (' · ' . $domain) : '');
        $lines[] = '';
        $lines[] = 'Проверка #' . (int) ($plan['crawl_id'] ?? 0)
            . ' · ' . (string) ($plan['generated_at'] ?? '');
        $lines[] = '';

        if (! empty($plan['ai_summary']) && is_string($plan['ai_summary'])) {
            $lines[] = '## Резюме (ИИ)';
            $lines[] = trim($plan['ai_summary']);
            $lines[] = '';
        }

        $lines[] = '## Задачи';
        $lines[] = '';
        foreach ($plan['items'] as $it) {
            $box = ! empty($it['done']) ? '[x]' : '[ ]';
            $lines[] = sprintf(
                '%s **%d. %s** (%s, %d URL)',
                $box,
                (int) $it['priority'],
                (string) $it['title'],
                (string) $it['severity'],
                (int) $it['count']
            );
            $lines[] = '   - Как: ' . (string) $it['how'];
            $samples = is_array($it['sample_urls'] ?? null) ? $it['sample_urls'] : [];
            if ($samples !== []) {
                $lines[] = '   - Примеры: ' . implode(', ', array_slice($samples, 0, 3));
            }
            $lines[] = '';
        }

        return trim(implode("\n", $lines)) . "\n";
    }

    /**
     * @param array<string,mixed> $plan
     */
    private function polishWithAi(array $plan): ?string
    {
        if (! (bool) config('site_audit.action_plan_ai_enabled', true)) {
            return null;
        }

        $ai = app(\App\Services\Ai\AiChatGateway::class);
        if (! $ai->hasConfiguredProvider(\App\Services\Ai\AiChatGateway::MODULE_SITE_AUDIT_ACTION_PLAN)) {
            return null;
        }

        $brief = [];
        foreach (array_slice($plan['items'], 0, 20) as $it) {
            $brief[] = sprintf(
                '- [%s] %s (%d): %s',
                $it['severity'],
                $it['title'],
                $it['count'],
                $it['how']
            );
        }

        $prompt = "Ты SEO/техлид. По аудиту сайта "
            . ($plan['domain'] ?? '')
            . " составь краткое резюме плана работ на русском (6–12 предложений): приоритеты, что сделать в первую очередь, что можно отложить. Без воды.\n\nЗадачи:\n"
            . implode("\n", $brief);

        try {
            $text = trim($ai->request(
                $prompt,
                \App\Services\Ai\AiChatGateway::MODULE_SITE_AUDIT_ACTION_PLAN
            ));

            return $text !== '' ? mb_substr($text, 0, 4000) : null;
        } catch (\Throwable $e) {
            Log::warning('SiteAudit action plan AI failed: ' . $e->getMessage(), [
                'crawl_id' => $plan['crawl_id'] ?? null,
            ]);

            return null;
        }
    }

    /**
     * @param array<string,mixed> $plan
     * @return array<string,mixed>
     */
    public function toggleDone(array $plan, string $code, bool $done): array
    {
        foreach ($plan['items'] as &$it) {
            if ((string) ($it['code'] ?? '') === $code) {
                $it['done'] = $done;
            }
        }
        unset($it);
        $plan['markdown'] = $this->toMarkdown($plan);

        return $plan;
    }
}
