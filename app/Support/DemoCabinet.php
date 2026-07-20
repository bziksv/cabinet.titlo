<?php

namespace App\Support;

use App\RelevanceHistory;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DemoCabinet
{
    public static function enabled(): bool
    {
        return (bool) config('cabinet-demo-cabinet.enabled', true);
    }

    public static function email(): string
    {
        return (string) config('cabinet-demo-cabinet.email', 'demo@cabinet.titlo.ru');
    }

    public static function findUser(): ?User
    {
        $email = self::email();
        if ($email === '') {
            return null;
        }

        return User::query()->where('email', $email)->first();
    }

    public static function isDemoUser(?User $user = null): bool
    {
        if (! self::enabled()) {
            return false;
        }
        $user = $user ?: Auth::user();
        if (! $user) {
            return false;
        }

        return strcasecmp((string) $user->email, self::email()) === 0;
    }

    public static function isCurrentUser(): bool
    {
        return self::isDemoUser(Auth::user());
    }

    /**
     * Готовый анализ релевантности для витрины (путь /show-history/{id}).
     */
    public static function relevanceShowcasePath(?User $user = null): ?string
    {
        $user = $user ?: Auth::user() ?: self::findUser();
        if (! $user) {
            return null;
        }

        $historyId = RelevanceHistory::query()
            ->where('user_id', $user->id)
            ->where('state', 1)
            ->where('calculate', 1)
            ->orderByDesc('id')
            ->value('id');

        if (! $historyId) {
            return null;
        }

        return '/show-history/' . (int) $historyId;
    }

    /**
     * Готовая сессия Есенина: /esenin-text-check?session={id}
     */
    public static function eseninShowcasePath(?User $user = null): ?string
    {
        $user = $user ?: Auth::user() ?: self::findUser();
        if (! $user) {
            return null;
        }

        $sessionId = \App\EseninTextCheckSession::query()
            ->where('user_id', $user->id)
            ->whereHas('versions', function ($q) {
                $q->whereNotNull('result_json')->where('result_json', '!=', '');
            })
            ->orderByDesc('id')
            ->value('id');

        if (! $sessionId) {
            return null;
        }

        return '/esenin-text-check?session=' . (int) $sessionId;
    }

    /**
     * Готовый результат кластеризатора: /show-cluster-result/{id}
     */
    public static function clusterShowcasePath(?User $user = null): ?string
    {
        $user = $user ?: Auth::user() ?: self::findUser();
        if (! $user) {
            return null;
        }

        $id = \App\ClusterResults::query()
            ->where('user_id', $user->id)
            ->where(function ($q) {
                $q->where('show', 1)->orWhereNull('show');
            })
            ->orderByDesc('id')
            ->value('id');

        if (! $id) {
            return null;
        }

        return '/show-cluster-result/' . (int) $id;
    }

    /**
     * Готовый проект мониторинга: /monitoring/{id}
     */
    public static function monitoringShowcasePath(?User $user = null): ?string
    {
        $user = $user ?: Auth::user() ?: self::findUser();
        if (! $user) {
            return null;
        }

        $id = \Illuminate\Support\Facades\DB::table('monitoring_projects')
            ->where('creator', $user->id)
            ->orderByDesc('id')
            ->value('id');

        if (! $id && \Illuminate\Support\Facades\Schema::hasTable('monitoring_project_user')) {
            $id = \Illuminate\Support\Facades\DB::table('monitoring_project_user')
                ->where('user_id', $user->id)
                ->orderByDesc('monitoring_project_id')
                ->value('monitoring_project_id');
        }

        if (! $id) {
            return null;
        }

        return '/monitoring/' . (int) $id;
    }

    /**
     * История модуля с AJAX-открытием: /{module}?history={id}
     */
    public static function moduleHistoryShowcasePath(string $modulePath, string $modelClass, ?User $user = null): ?string
    {
        $user = $user ?: Auth::user() ?: self::findUser();
        if (! $user || ! class_exists($modelClass)) {
            return null;
        }

        $id = $modelClass::query()
            ->where('user_id', $user->id)
            ->orderByDesc('id')
            ->value('id');

        if (! $id) {
            return null;
        }

        $modulePath = trim($modulePath, '/');

        return '/' . $modulePath . '?history=' . (int) $id;
    }

    public static function searchSuggestionsShowcasePath(?User $user = null): ?string
    {
        return self::moduleHistoryShowcasePath('search-suggestions', \App\SearchSuggestionsHistory::class, $user);
    }

    public static function siteTypesShowcasePath(?User $user = null): ?string
    {
        return self::moduleHistoryShowcasePath('site-types', \App\SiteTypesHistory::class, $user);
    }

    public static function phraseCommerceShowcasePath(?User $user = null): ?string
    {
        return self::moduleHistoryShowcasePath('phrase-commerce', \App\PhraseCommerceHistory::class, $user);
    }

    public static function domainRecordsShowcasePath(?User $user = null): ?string
    {
        return self::moduleHistoryShowcasePath('domain-records', \App\DomainRecordsHistory::class, $user);
    }

    /**
     * Готовая история мета-тегов: /meta-tags/history/{id}
     */
    public static function metaTagsShowcasePath(?User $user = null): ?string
    {
        $user = $user ?: Auth::user() ?: self::findUser();
        if (! $user) {
            return null;
        }

        $historyId = \App\MetaTagsHistory::query()
            ->whereHas('project', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            })
            ->whereNotNull('data')
            ->where('data', '!=', '')
            ->orderByDesc('id')
            ->value('id');

        if (! $historyId) {
            return null;
        }

        return '/meta-tags/history/' . (int) $historyId;
    }

    /**
     * Готовый проект бэклинков: /show-backlink/{id}
     */
    public static function backlinkShowcasePath(?User $user = null): ?string
    {
        $user = $user ?: Auth::user() ?: self::findUser();
        if (! $user) {
            return null;
        }

        $id = \App\ProjectTracking::query()
            ->where('user_id', $user->id)
            ->orderByDesc('id')
            ->value('id');

        if (! $id) {
            return null;
        }

        return '/show-backlink/' . (int) $id;
    }

    /**
     * Готовый текст HTML-редактора: /edit-description/{id}
     */
    public static function htmlEditorShowcasePath(?User $user = null): ?string
    {
        $user = $user ?: Auth::user() ?: self::findUser();
        if (! $user) {
            return null;
        }

        $id = \App\ProjectDescription::query()
            ->join('projects', 'projects.id', '=', 'project_description.project_id')
            ->where('projects.user_id', $user->id)
            ->whereNotNull('project_description.description')
            ->where('project_description.description', '!=', '')
            ->orderByDesc('project_description.id')
            ->value('project_description.id');

        if (! $id) {
            return null;
        }

        return '/edit-description/' . (int) $id;
    }

    /**
     * Готовый результат AI-генерации (последняя completed-запись пользователя).
     */
    public static function aiGenerationShowcase(?User $user = null): ?\App\AiGenerationHistory
    {
        $user = $user ?: Auth::user() ?: self::findUser();
        if (! $user || ! class_exists(\App\AiGenerationHistory::class)) {
            return null;
        }

        return \App\AiGenerationHistory::query()
            ->where('user_id', $user->id)
            ->where('status', \App\AiGenerationHistory::COMPLETED)
            ->whereNotNull('result')
            ->where('result', '!=', '')
            ->orderByDesc('id')
            ->first();
    }

    public static function aiGenerationShowcasePath(?User $user = null): ?string
    {
        if (! self::aiGenerationShowcase($user)) {
            return null;
        }

        return '/ai-generation/prompt';
    }

    /**
     * JSON-фикстура из resources/data/demo/{name}.
     *
     * @return array<string, mixed>|null
     */
    public static function loadDemoJson(string $name): ?array
    {
        $name = basename($name);
        if ($name === '' || $name === '.' || $name === '..') {
            return null;
        }
        if (substr($name, -5) !== '.json') {
            $name .= '.json';
        }

        $path = resource_path('data/demo/' . $name);
        if (! is_file($path)) {
            return null;
        }

        $raw = file_get_contents($path);
        if ($raw === false || $raw === '') {
            return null;
        }

        $data = json_decode($raw, true);

        return is_array($data) ? $data : null;
    }

    /**
     * @return array{text: string, options?: array, caseInsensitiveDedup?: bool}|null
     */
    public static function duplicatesShowcase(): ?array
    {
        $data = self::loadDemoJson('duplicates-showcase.json');
        if (! $data || empty($data['text'])) {
            return null;
        }

        return $data;
    }

    /**
     * @return array{listA: string, listB: string, mode?: string, options?: array}|null
     */
    public static function listComparisonShowcase(): ?array
    {
        $data = self::loadDemoJson('list-comparison-showcase.json');
        if (! $data || empty($data['listA']) || empty($data['listB'])) {
            return null;
        }

        return $data;
    }

    /**
     * @return array{content: string, rows: list<array>, metrics: array}|null
     */
    public static function uniqueShowcase(): ?array
    {
        $data = self::loadDemoJson('unique-showcase.json');
        if (! $data || empty($data['content']) || empty($data['rows']) || ! is_array($data['rows'])) {
            return null;
        }

        return $data;
    }

    /**
     * @return array{text: string, title?: string, description?: string, h1?: string}|null
     */
    public static function textLengthShowcase(): ?array
    {
        $data = self::loadDemoJson('text-length-showcase.json');
        if (! $data || empty($data['text'])) {
            return null;
        }

        return $data;
    }

    /**
     * @return array{url: string, response: list<array>}|null
     */
    public static function httpHeadersShowcase(): ?array
    {
        $data = self::loadDemoJson('http-headers-showcase.json');
        if (! $data || empty($data['response']) || ! is_array($data['response'])) {
            return null;
        }

        return $data;
    }

    /**
     * @return array{urls: string, items: list<array>}|null
     */
    public static function indexCheckShowcase(): ?array
    {
        $data = self::loadDemoJson('index-check-showcase.json');
        if (! $data || empty($data['items']) || ! is_array($data['items'])) {
            return null;
        }

        return $data;
    }

    /**
     * @return array{url: string, utm_source?: string, utm_medium?: string, utm_campaign?: string, utm_content?: string, utm_term?: string}|null
     */
    public static function utmMarksShowcase(): ?array
    {
        $data = self::loadDemoJson('utm-marks-showcase.json');
        if (! $data || empty($data['url'])) {
            return null;
        }

        return $data;
    }

    /**
     * @return array{lists: list<string>}|null
     */
    public static function keywordGeneratorShowcase(): ?array
    {
        $data = self::loadDemoJson('keyword-generator-showcase.json');
        if (! $data || empty($data['lists']) || ! is_array($data['lists'])) {
            return null;
        }

        return $data;
    }

    /**
     * @return array<string, int|float|string>|null
     */
    public static function roiCalculatorShowcase(): ?array
    {
        $data = self::loadDemoJson('roi-calculator-showcase.json');
        if (! $data || ! isset($data['zatrat'], $data['doxod'])) {
            return null;
        }

        return $data;
    }

    /**
     * Куда вести после входа в демо.
     */
    public static function homePath(?User $user = null): string
    {
        $showcase = self::relevanceShowcasePath($user);
        if ($showcase) {
            return $showcase;
        }

        return (string) config('cabinet-demo-cabinet.home_path', '/history');
    }

    /**
     * Готовый снимок анализа конкурентов для витрины.
     *
     * @return array{phrases: list<string>, count: int, search_engines: list<string>, regions_yandex: list<string>, result: array}|null
     */
    public static function competitorShowcase(): ?array
    {
        $path = resource_path('data/demo/competitor-analysis-showcase.json');
        if (! is_file($path)) {
            return null;
        }

        $raw = file_get_contents($path);
        if ($raw === false || $raw === '') {
            return null;
        }

        $data = json_decode($raw, true);
        if (! is_array($data) || empty($data['result']) || ! is_array($data['result'])) {
            return null;
        }

        return [
            'phrases' => array_values(array_filter(array_map('strval', $data['phrases'] ?? []))),
            'count' => (int) ($data['count'] ?? 30),
            'search_engines' => array_values($data['search_engines'] ?? ['yandex']),
            'regions_yandex' => array_values(array_map('strval', $data['regions_yandex'] ?? ['213'])),
            'result' => $data['result'],
        ];
    }

    /**
     * Полный снимок анализа текста (KPI + уникальность + Есенин) для витрины.
     *
     * @return array{request: array, response: array, history?: list<array>}|null
     */
    public static function textAnalyzerShowcase(): ?array
    {
        $path = resource_path('data/demo/text-analyzer-showcase.json');
        if (! is_file($path)) {
            return null;
        }

        $raw = file_get_contents($path);
        if ($raw === false || $raw === '') {
            return null;
        }

        $data = json_decode($raw, true);
        if (! is_array($data) || empty($data['response']) || ! is_array($data['response'])) {
            return null;
        }

        $request = is_array($data['request'] ?? null) ? $data['request'] : [];
        if (empty($request['textarea']) && ! empty($request['text'])) {
            $request['textarea'] = $request['text'];
        }
        $request['checkUniqueness'] = 1;
        $request['checkEsenin'] = 1;
        $request['type'] = $request['type'] ?? 'text';

        return [
            'request' => $request,
            'response' => $data['response'],
            'history' => is_array($data['history'] ?? null) ? $data['history'] : [],
        ];
    }

    /**
     * POST/PUT только для чтения витрины + выход.
     * Запуски анализов / сохранения сюда не входят.
     *
     * @return list<string>
     */
    public static function readonlyPostPathPrefixes(): array
    {
        return [
            'logout',
            'demo-cabinet/exit',
            // layout: учёт времени / кликов (иначе blur→alert→blur цикл на демо)
            'update-statistics',
            'click-tracking',
            'broadcasting/auth',
            // релевантность — подгрузка готового снимка
            'get-details-history',
            'get-stories',
            'get-stories-v2',
            'check-state',
            'check-queue-scan-state',
            'get-relevance-progress-percent',
            'get-slice-result',
            // конкуренты — чтение прогресса/рекомендаций по уже готовому снимку
            'get-competitor-progress',
            'get-recommendations',
            // мониторинг v2 — список/тренд на витрине
            'monitoring-v2/portfolio/top10-trend',
            'monitoring-v2/project-stats',
            // списки / таблицы модулей
            'get-count-new-news',
            'get-statistic-modules',
            'get-cluster-request',
            'ai-generation/history',
            'monitoring-v2/projects/list',
            'monitoring/projects/get-positions-for-calendars',
            'monitoring/get-top/sites',
            'monitoring/competitors/history/positions',
            'monitoring/competitors/history/estimate',
            'monitoring/competitors/check-analyse-state',
            'monitoring/competitors/check-analyse-state-batch',
            'monitoring/projects/competitors',
        ];
    }

    /**
     * Разрешённые не-GET запросы в демо (выход + чтение витрины).
     */
    public static function allowsMutatingRequest(Request $request): bool
    {
        $path = trim($request->path(), '/');
        $route = optional($request->route())->getName();

        if ($route && in_array($route, ['logout', 'demo-cabinet.exit'], true)) {
            return true;
        }

        foreach (self::readonlyPostPathPrefixes() as $prefix) {
            if ($path === $prefix || strpos($path, $prefix . '/') === 0) {
                return true;
            }
        }

        // DataTables мониторинга: POST /monitoring/{id}/table
        if (preg_match('#^monitoring/\d+/table$#', $path)) {
            return true;
        }

        return false;
    }

    public static function blockMessage(): string
    {
        return 'Демо-кабинет только для просмотра: запуски, сохранения и изменения отключены. Зарегистрируйтесь, чтобы работать со своими данными.';
    }
}
