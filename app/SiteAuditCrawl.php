<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class SiteAuditCrawl extends Model
{
    public const STATUS_QUEUED = 'queued';
    public const STATUS_DISCOVERING = 'discovering';
    public const STATUS_FETCHING = 'fetching';
    public const STATUS_AGGREGATING = 'aggregating';
    public const STATUS_DONE = 'done';
    public const STATUS_FAILED = 'failed';
    public const STATUS_QUEUED_WAIT = 'queued_wait';
    public const STATUS_CANCELLED = 'cancelled';

    protected $table = 'site_audit_crawls';

    protected $fillable = [
        'project_id',
        'user_id',
        'status',
        'pages_total',
        'pages_fetched',
        'pages_limit',
        'buckets_json',
        'counts_json',
        'progress_json',
        'error',
        'save_html',
        'share_token',
        'share_enabled_at',
        'share_white_label',
        'share_brand_name',
        'share_brand_url',
        'share_brand_logo',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'buckets_json' => 'array',
        'counts_json' => 'array',
        'progress_json' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'share_enabled_at' => 'datetime',
        'share_white_label' => 'boolean',
    ];

    public function project()
    {
        return $this->belongsTo(SiteAuditProject::class, 'project_id');
    }

    public function pages()
    {
        return $this->hasMany(SiteAuditPage::class, 'crawl_id');
    }

    /**
     * Масштаб проверки для карточек сводки: страницы + сумма img на HTML.
     *
     * @return array{pages:int,images:int}
     */
    public function scaleStats(): array
    {
        $pages = (int) $this->pages_fetched;
        $progress = is_array($this->progress_json) ? $this->progress_json : [];
        if (array_key_exists('images_total', $progress) && $progress['images_total'] !== null) {
            $images = (int) $progress['images_total'];
        } else {
            $images = (int) SiteAuditPage::query()
                ->where('crawl_id', $this->id)
                ->sum('img_count');
        }

        return [
            'pages' => $pages,
            'images' => $images,
        ];
    }

    public function findings()
    {
        return $this->hasMany(SiteAuditFinding::class, 'crawl_id');
    }

    public function stats()
    {
        return $this->hasMany(SiteAuditCrawlStat::class, 'crawl_id');
    }

    public function isFinished(): bool
    {
        return in_array($this->status, [
            self::STATUS_DONE,
            self::STATUS_FAILED,
            self::STATUS_CANCELLED,
        ], true);
    }

    public function isActive(): bool
    {
        return ! $this->isFinished();
    }

    /**
     * Знаменатель прогресса для UI: без артефакта «весь sitemap» после stop/resume-rebuild.
     */
    public function displayPagesTotal(): int
    {
        $fetched = max(0, (int) $this->pages_fetched);
        $total = max($fetched, (int) $this->pages_total);
        $limit = max(0, (int) $this->pages_limit);
        if ($limit > 0) {
            $total = min($total, $limit);
        }

        $seed = 0;
        $urlCount = 0;
        if (is_array($this->progress_json)) {
            $seed = (int) ($this->progress_json['sitemap']['seed_count'] ?? 0);
            $urlCount = (int) ($this->progress_json['sitemap']['url_count'] ?? 0);
        }
        if ($seed <= 0 && isset($this->sitemap_seed_count_raw)) {
            $seed = (int) $this->sitemap_seed_count_raw;
        }
        if ($urlCount <= 0 && isset($this->sitemap_url_count_raw)) {
            $urlCount = (int) $this->sitemap_url_count_raw;
        }

        // Остановленные/готовые: total = весь sitemap при меньшем seed → показываем seed/fetched.
        if ($this->isFinished() && $seed > 0 && $urlCount > 0 && $total >= $urlCount) {
            $total = max($fetched, $seed);
        }

        return max($fetched, $total);
    }

    public static function statusLabel(?string $status): string
    {
        $map = [
            self::STATUS_QUEUED => 'Запуск',
            self::STATUS_DISCOVERING => 'Сбор URL',
            self::STATUS_FETCHING => 'Сканирование',
            self::STATUS_AGGREGATING => 'Агрегация',
            self::STATUS_DONE => 'Готово',
            self::STATUS_FAILED => 'Ошибка',
            self::STATUS_QUEUED_WAIT => 'Ждёт слот',
            self::STATUS_CANCELLED => 'Остановлен',
        ];

        return $map[$status] ?? (string) $status;
    }

    /** Полная подпись статуса для title/tooltip. */
    public static function statusLabelFull(?string $status): string
    {
        if ($status === self::STATUS_QUEUED_WAIT) {
            return 'В очереди — ждёт свободный слот (лимит одновременных проверок)';
        }

        return self::statusLabel($status);
    }

    public function statusLabelRu(): string
    {
        if ($this->status === self::STATUS_AGGREGATING) {
            $detail = $this->aggregateStageLabel();
            if ($detail !== null) {
                return $detail;
            }
        }

        return self::statusLabel($this->status);
    }

    public function statusLabelFullRu(): string
    {
        if ($this->status === self::STATUS_AGGREGATING) {
            $detail = $this->aggregateStageLabel();
            if ($detail !== null) {
                return 'Агрегация: ' . $detail . ' (финальный этап после сканирования)';
            }
        }

        return self::statusLabelFull($this->status);
    }

    /**
     * Короткая подпись текущего этапа агрегации (чтобы не казалось, что «Агрегация» зависла).
     */
    public function aggregateStageLabel(): ?string
    {
        $progress = is_array($this->progress_json) ? $this->progress_json : [];
        $stage = (string) (($progress['aggregate']['stage'] ?? '') ?: '');
        if ($stage === '' || $stage === 'done') {
            return null;
        }

        if ($stage === 'psi') {
            $psi = is_array($progress['psi'] ?? null) ? $progress['psi'] : [];
            if (! empty($psi['skipped'])) {
                return 'PSI пропуск';
            }
            $cursor = (int) ($psi['cursor'] ?? 0);
            $sampled = (int) ($psi['sampled'] ?? 0);
            if ($sampled > 0) {
                return 'PSI ' . min($cursor, $sampled) . '/' . $sampled;
            }

            return 'PageSpeed';
        }

        $map = [
            'serp_snippets' => 'Сниппеты',
            'serp_index' => 'Индекс',
            'serp_cannibalization' => 'Каннибал.',
            'availability' => 'Доступность',
            'cannibalization' => 'Каннибал.',
            'finalize' => 'Финиш',
            'click_depth' => 'Глубина',
            'sitemap_coverage' => 'Sitemap',
            'landing_coverage' => 'Посадочные',
            'broken_links' => 'Битые',
            'from_pages' => 'Страницы',
        ];

        return $map[$stage] ?? ('Этап ' . $stage);
    }

    public function statusCssClass(): string
    {
        if ($this->status === self::STATUS_DONE) {
            return 'done';
        }
        if ($this->status === self::STATUS_FAILED || $this->status === self::STATUS_CANCELLED) {
            return 'failed';
        }

        return 'run';
    }

    /**
     * Пересчитать buckets_json из site_audit_findings (для cancelled/failed без finalize).
     * Иначе в истории краулов всегда 0 / 0 / 0 — бакеты пишутся только в aggregate finalize.
     *
     * @return array{critical:int,other:int,important:int,warning:int,info:int}
     */
    public function refreshBucketsFromFindings(bool $save = true): array
    {
        $buckets = [
            'critical' => 0,
            'other' => 0,
            'important' => 0,
            'warning' => 0,
            'info' => 0,
        ];
        try {
            $counts = SiteAuditFinding::query()
                ->where('crawl_id', $this->id)
                ->select('severity', DB::raw('count(*) as c'))
                ->groupBy('severity')
                ->pluck('c', 'severity')
                ->all();
            foreach ($buckets as $sev => $_) {
                $buckets[$sev] = (int) ($counts[$sev] ?? 0);
            }
        } catch (\Throwable $e) {
            // таблица/связь — оставляем нули
        }
        $this->buckets_json = $buckets;
        if ($save) {
            // Не трогаем updated_at прогресса active-краула лишний раз при backfill —
            // но для cancel/fail уже пишем finished_at в том же save.
            $this->save();
        }

        return $buckets;
    }

    /**
     * Промежуточный снимок корзин + counts во время скана.
     * Severity GROUP BY — для истории; code GROUP BY — для сводки/дерева отчётов,
     * когда live counts на каждый poll отключены.
     */
    public function maybeRefreshBucketSnapshot(bool $force = false): bool
    {
        if ($this->isFinished()) {
            return false;
        }
        $fetched = (int) $this->pages_fetched;
        if ($fetched < 1) {
            return false;
        }

        $every = max(50, (int) config('site_audit.bucket_snapshot_every_pages', 400));
        $minSec = max(15, (int) config('site_audit.bucket_snapshot_min_seconds', 60));
        $progress = is_array($this->progress_json) ? $this->progress_json : [];
        $snap = isset($progress['bucket_snapshot']) && is_array($progress['bucket_snapshot'])
            ? $progress['bucket_snapshot']
            : [];
        $lastPages = (int) ($snap['pages'] ?? 0);
        $lastAt = ! empty($snap['at']) ? strtotime((string) $snap['at']) : 0;

        if (! $force) {
            $pagesDelta = $fetched - $lastPages;
            $age = $lastAt > 0 ? (time() - $lastAt) : PHP_INT_MAX;
            // Ждём либо пачку страниц, либо мин. интервал (и хотя бы 1 новая страница).
            if ($pagesDelta < 1) {
                return false;
            }
            if ($pagesDelta < $every && $age < $minSec) {
                return false;
            }
        }

        $this->refreshBucketsFromFindings(false);
        try {
            $byCode = SiteAuditFinding::query()
                ->where('crawl_id', $this->id)
                ->selectRaw('code, count(*) as c')
                ->groupBy('code')
                ->pluck('c', 'code')
                ->all();
            $counts = [];
            foreach ($byCode as $code => $c) {
                $counts[(string) $code] = (int) $c;
            }
            $this->counts_json = $counts;
        } catch (\Throwable $e) {
            // корзины уже есть — counts подтянутся следующим снимком
        }
        $progress['bucket_snapshot'] = [
            'pages' => $fetched,
            'at' => now()->toIso8601String(),
            'has_counts' => is_array($this->counts_json) && $this->counts_json !== [],
        ];
        $this->progress_json = $progress;
        $this->save();

        return true;
    }

    /** buckets_json ещё не заполняли (типично cancelled/failed до finalize). */
    public function bucketsAreEmpty(): bool
    {
        $b = $this->buckets_json;

        return ! is_array($b) || $b === [];
    }

    /**
     * Оценка окончания:
     * — во время скана: по скорости обхода страниц;
     * — в агрегации (в т.ч. PSI): по оставшимся URL PageSpeed и финалу.
     * null если ещё рано считать или проверка завершена.
     */
    public function estimateFinishedAt(): ?\Carbon\Carbon
    {
        if ($this->isFinished() || $this->finished_at) {
            return null;
        }

        $fetched = (int) $this->pages_fetched;
        $total = (int) $this->pages_total;
        $pagesDone = $total > 0 && $fetched >= $total;

        // Скан закончен / идёт агрегация — ETA по PSI и хвосту этапов, не по страницам.
        if ($this->status === self::STATUS_AGGREGATING || $pagesDone) {
            return $this->estimateAggregateFinishedAt();
        }

        if ($fetched < 15 || $total <= $fetched) {
            return null;
        }

        $start = $this->started_at ?: $this->created_at;
        if (! $start) {
            return null;
        }

        $elapsed = max(1, now()->getTimestamp() - $start->getTimestamp());
        $rate = $fetched / $elapsed;
        if ($rate < 0.01) {
            return null;
        }

        $secondsLeft = (int) ceil(($total - $fetched) / $rate);
        if ($secondsLeft > 14 * 24 * 3600) {
            return null;
        }

        return now()->addSeconds($secondsLeft);
    }

    /**
     * Ориентир конца агрегации (особенно долгий PSI: ~1 URL × mobile+desktop).
     */
    public function estimateAggregateFinishedAt(): ?\Carbon\Carbon
    {
        $progress = is_array($this->progress_json) ? $this->progress_json : [];
        $agg = is_array($progress['aggregate'] ?? null) ? $progress['aggregate'] : [];
        $stage = (string) ($agg['stage'] ?? '');
        $psi = is_array($progress['psi'] ?? null) ? $progress['psi'] : [];

        $seconds = 0;
        $psiDone = ! empty($psi['done']) || ! empty($psi['skipped']);
        $secPerPsiUrl = max(30, (int) config('site_audit.psi_eta_seconds_per_url', 55));

        if (! $psiDone) {
            $sampled = (int) ($psi['sampled'] ?? 0);
            $cursor = (int) ($psi['cursor'] ?? 0);
            if ($sampled < 1) {
                $sampled = max(1, (int) config('site_audit.psi_max_urls', 30));
                // этап PSI ещё не стартовал — считаем полный прогон
                if ($stage !== '' && $stage !== 'psi' && $stage !== 'finalize') {
                    // до PSI ещё есть этапы — грубый запас
                    $seconds += 90;
                }
            }
            $left = max(0, $sampled - $cursor);
            if ($stage === 'psi' || $stage === '' || $stage === 'finalize' || $sampled > 0) {
                $seconds += $left * $secPerPsiUrl;
            }
        }

        if ($stage === 'finalize') {
            $seconds = max($seconds, 20);
        } else {
            $seconds += 25; // финализация / хвост
        }

        if ($seconds < 15) {
            $seconds = 20;
        }
        if ($seconds > 14 * 24 * 3600) {
            return null;
        }

        return now()->addSeconds($seconds);
    }

    public function estimateFinishedAtFormatted(): ?string
    {
        $at = $this->estimateFinishedAt();

        return $at ? $at->format('d.m H:i') : null;
    }

    /** Подсказка к ~времени в колонке «Конец». */
    public function estimateFinishedAtTitle(): string
    {
        if ($this->status === self::STATUS_AGGREGATING
            || ((int) $this->pages_total > 0 && (int) $this->pages_fetched >= (int) $this->pages_total)
        ) {
            $detail = $this->aggregateStageLabel();
            $base = 'Ориентир конца: идёт финальный этап';
            if ($detail !== null) {
                $base .= ' («' . $detail . '»)';
            }
            $base .= '. Точная дата появится в «Готово».';

            return $base;
        }

        return 'Оценка конца по текущей скорости сканирования';
    }

    public function isShared(): bool
    {
        return $this->share_token && $this->share_enabled_at;
    }

    public function publicShareUrl(): ?string
    {
        if (! $this->isShared()) {
            return null;
        }

        return route('site-audit.public.share.view', $this->share_token);
    }

    public function isWhiteLabelShare(): bool
    {
        return (bool) $this->share_white_label;
    }

    /**
     * @return array{enabled:bool,brand_name:?string,brand_url:?string,brand_logo_url:?string}
     */
    public function whiteLabelMeta(): array
    {
        $name = is_string($this->share_brand_name) ? trim($this->share_brand_name) : '';
        $url = is_string($this->share_brand_url) ? trim($this->share_brand_url) : '';
        if ($url !== '' && ! preg_match('#^https?://#i', $url)) {
            $url = 'https://' . $url;
        }

        $logoUrl = null;
        $logo = is_string($this->share_brand_logo) ? trim($this->share_brand_logo) : '';
        if ($logo !== '' && \Illuminate\Support\Facades\Storage::disk('public')->exists($logo)) {
            $logoUrl = asset('storage/' . ltrim($logo, '/'));
        }

        return [
            'enabled' => $this->isWhiteLabelShare(),
            'brand_name' => $name !== '' ? mb_substr($name, 0, 120) : null,
            'brand_url' => $url !== '' ? mb_substr($url, 0, 255) : null,
            'brand_logo_url' => $logoUrl,
        ];
    }

    public function clearWhiteLabelLogo(): void
    {
        $logo = is_string($this->share_brand_logo) ? trim($this->share_brand_logo) : '';
        if ($logo !== '') {
            try {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($logo);
            } catch (\Throwable $e) {
                // ignore
            }
        }
        $this->share_brand_logo = null;
    }

    /**
     * Битый UTF-8 (часто из monitoring.query) ломал aggregate → проверка зависал в «Агрегация».
     */
    public function setProgressJsonAttribute($value): void
    {
        if ($value === null) {
            $this->attributes['progress_json'] = null;

            return;
        }
        if (! is_array($value)) {
            $value = (array) $value;
        }
        $value = \App\Services\SiteAudit\SiteAuditUtf8::scrub($value);
        $flags = JSON_UNESCAPED_UNICODE;
        if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
            $flags |= JSON_INVALID_UTF8_SUBSTITUTE;
        }
        $this->attributes['progress_json'] = json_encode($value, $flags);
    }
}
