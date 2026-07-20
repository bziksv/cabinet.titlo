<?php

namespace App\Console\Commands;

use App\DomainRecordsHistory;
use App\PhraseCommerceHistory;
use App\ProjectRelevanceHistory;
use App\RelevanceAnalysisConfig;
use App\RelevanceHistory;
use App\RelevanceHistoryResult;
use App\SearchSuggestionsHistory;
use App\Services\DomainRecordsService;
use App\SiteTypesHistory;
use App\Support\DemoCabinet;
use App\Support\DemoCabinetModuleSeeder;
use App\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class SeedDemoCabinet extends Command
{
    protected $signature = 'demo-cabinet:seed {--fresh : Пересоздать истории демо-пользователя}';

    protected $description = 'Создаёт демо-пользователя и готовые результаты для просмотра кабинета';

    public function handle(): int
    {
        if (! DemoCabinet::enabled()) {
            $this->error('DEMO_CABINET_ENABLED=false');

            return 1;
        }

        $user = $this->ensureUser();
        $this->ensureRoles($user);

        $modules = new DemoCabinetModuleSeeder(
            function (string $msg) {
                $this->line($msg);
            },
            function (string $msg) {
                $this->warn($msg);
            }
        );

        if ($this->option('fresh')) {
            $this->purgeHistories($user->id);
            $modules->purge($user->id);
        }

        $this->seedSearchSuggestions($user->id);
        $this->seedDomainRecords($user->id);
        $this->seedSiteTypes($user->id);
        $this->seedPhraseCommerce($user->id);
        $this->seedRelevance($user->id);
        $extra = $modules->seedAll($user->id);

        $this->info('Демо-кабинет готов: ' . $user->email . ' (id ' . $user->id . ')');
        $this->line('Вход: GET /demo-cabinet');
        $this->printChecklist($extra);

        return 0;
    }

    /**
     * @param  array<string, string>  $extra
     */
    private function printChecklist(array $extra): void
    {
        $this->line('');
        $this->line('=== Чеклист модулей демо ===');
        $rows = [
            ['Релевантность (история)', '/history', 'ok'],
            ['Анализ релевантности (форма)', '/analyze-relevance', 'ok'],
            ['Конкуренты по ключам', '/competitor-analysis', !empty(\App\Support\DemoCabinet::competitorShowcase()) ? 'ok' : 'stateless'],
            ['Анализ текста / уникальность', '/text-analyzer', !empty(\App\Support\DemoCabinet::textAnalyzerShowcase()) ? 'ok' : ($extra['text-uniqueness'] ?? '?')],
            ['Есенин', '/esenin-text-check', $extra['esenin'] ?? '?'],
            ['Кластеризатор', '/cluster', $extra['cluster'] ?? '?'],
            ['Удаление дублей', '/duplicates', !empty(\App\Support\DemoCabinet::duplicatesShowcase()) ? 'ok' : 'stateless'],
            ['Сравнение списков', '/list-comparison', !empty(\App\Support\DemoCabinet::listComparisonShowcase()) ? 'ok' : 'stateless'],
            ['Уникальные слова', '/unique', !empty(\App\Support\DemoCabinet::uniqueShowcase()) ? 'ok' : 'stateless'],
            ['Длина текста', '/counting-text-length', !empty(\App\Support\DemoCabinet::textLengthShowcase()) ? 'ok' : 'stateless'],
            ['HTML-редактор', '/html-editor', $extra['html-editor'] ?? '?'],
            ['Мониторинг позиций (legacy)', '/monitoring', 'skip-legacy'],
            ['Мониторинг позиций v2', '/monitoring-v2', $extra['monitoring-v2'] ?? '?'],
            ['Мониторинг сайтов', '/site-monitoring', $extra['site-monitoring'] ?? '?'],
            ['Срок регистрации доменов', '/domain-information', $extra['domain-information'] ?? '?'],
            ['Записи домена', '/domain-records', 'ok'],
            ['Мета-теги', '/meta-tags', $extra['meta-tags'] ?? '?'],
            ['Отслеживание ссылок', '/backlink', $extra['backlink'] ?? '?'],
            ['HTTP-заголовки', '/http-headers', !empty(\App\Support\DemoCabinet::httpHeadersShowcase()) ? 'ok' : 'stateless'],
            ['Проверка индекса', '/index-check', !empty(\App\Support\DemoCabinet::indexCheckShowcase()) ? 'ok' : 'stateless'],
            ['Поисковые подсказки', '/search-suggestions', 'ok'],
            ['Типы сайтов в выдаче', '/site-types', 'ok'],
            ['Гео / локализация / коммерция', '/phrase-commerce', 'ok'],
            ['UTM-метки', '/utm-marks', !empty(\App\Support\DemoCabinet::utmMarksShowcase()) ? 'ok' : 'stateless'],
            ['Генератор паролей', '/password-generator', $extra['password-generator'] ?? '?'],
            ['Генератор ключей', '/keyword-generator', !empty(\App\Support\DemoCabinet::keywordGeneratorShowcase()) ? 'ok' : 'stateless'],
            ['ROI-калькулятор', '/roi-calculator', !empty(\App\Support\DemoCabinet::roiCalculatorShowcase()) ? 'ok' : 'stateless'],
            ['AI-генерация', '/ai-generation/prompt', $extra['ai-generation'] ?? 'n/a'],
        ];

        foreach ($rows as [$title, $path, $status]) {
            $mark = in_array($status, ['ok', 'skip'], true) ? '[x]'
                : (in_array($status, ['stateless', 'form-only', 'skip-legacy', 'n/a'], true) ? '[-]' : '[!]');
            $this->line(sprintf('%s %-40s %s (%s)', $mark, $title, $path, $status));
        }
    }

    private function ensureUser(): User
    {
        $email = DemoCabinet::email();
        $password = (string) config('cabinet-demo-cabinet.password', 'DemoCabinet!titlo');
        $user = User::query()->where('email', $email)->first();

        if (! $user) {
            $user = new User();
            $user->email = $email;
            $user->name = (string) config('cabinet-demo-cabinet.name', 'Демо');
            $user->last_name = (string) config('cabinet-demo-cabinet.last_name', 'Кабинет');
            $user->lang = 'ru';
            $user->balance = 0;
            $user->telegram_token = bin2hex(random_bytes(8));
        }

        $user->password = Hash::make($password);
        $user->email_verified_at = $user->email_verified_at ?: now();
        $user->save();

        return $user;
    }

    private function ensureRoles(User $user): void
    {
        app(\Spatie\Permission\PermissionRegistrar::class)->setPermissionsTeamId(1);

        $tariff = (string) config('cabinet-demo-cabinet.tariff_role', 'Maximum');
        foreach (['user', $tariff] as $roleName) {
            if (Role::query()->where('name', $roleName)->exists()) {
                if (! $user->hasRole($roleName)) {
                    $user->assignRole($roleName);
                }
            } else {
                $this->warn('Роль не найдена: ' . $roleName);
            }
        }

        // На всякий случай — явные права модулей витрины
        foreach (['Search suggestions', 'Domain records', 'Site types', 'Phrase commerce'] as $permName) {
            try {
                $perm = Permission::query()->where('name', $permName)->first();
                if ($perm && ! $user->hasPermissionTo($permName)) {
                    $user->givePermissionTo($permName);
                }
            } catch (\Throwable $e) {
                $this->warn('permission ' . $permName . ': ' . $e->getMessage());
            }
        }
    }

    private function purgeHistories(int $userId): void
    {
        SearchSuggestionsHistory::query()->where('user_id', $userId)->delete();
        DomainRecordsHistory::query()->where('user_id', $userId)->delete();
        SiteTypesHistory::query()->where('user_id', $userId)->delete();
        PhraseCommerceHistory::query()->where('user_id', $userId)->delete();

        $projectIds = ProjectRelevanceHistory::query()->where('user_id', $userId)->pluck('id');
        if ($projectIds->isNotEmpty()) {
            $historyIds = RelevanceHistory::query()
                ->whereIn('project_relevance_history_id', $projectIds)
                ->pluck('id');
            if ($historyIds->isNotEmpty()) {
                RelevanceHistoryResult::query()->whereIn('project_id', $historyIds)->delete();
                RelevanceHistory::query()->whereIn('id', $historyIds)->delete();
            }
            ProjectRelevanceHistory::query()->whereIn('id', $projectIds)->delete();
        }

        $this->line('Старые истории демо (базовые) удалены.');
    }

    private function seedSearchSuggestions(int $userId): void
    {
        if (SearchSuggestionsHistory::query()->where('user_id', $userId)->exists()) {
            $this->line('search-suggestions: уже есть');

            return;
        }

        $results = [
            ['seed' => 'ремонт фасада', 'query' => 'ремонт фасада', 'suggest' => 'ремонт фасада цена', 'engine' => 'yandex', 'level' => 1, 'words' => 3, 'type' => 'phrase'],
            ['seed' => 'ремонт фасада', 'query' => 'ремонт фасада', 'suggest' => 'ремонт фасада дома', 'engine' => 'yandex', 'level' => 1, 'words' => 3, 'type' => 'phrase'],
            ['seed' => 'ремонт фасада', 'query' => 'ремонт фасада', 'suggest' => 'ремонт фасада здания', 'engine' => 'yandex', 'level' => 1, 'words' => 3, 'type' => 'phrase'],
            ['seed' => 'ремонт фасада', 'query' => 'ремонт фасада', 'suggest' => 'ремонт фасада москва', 'engine' => 'yandex', 'level' => 1, 'words' => 3, 'type' => 'phrase'],
            ['seed' => 'ремонт фасада', 'query' => 'ремонт фасада', 'suggest' => 'ремонт фасада своими руками', 'engine' => 'yandex', 'level' => 1, 'words' => 4, 'type' => 'phrase'],
            ['seed' => 'ремонт фасада', 'query' => 'ремонт фасада', 'suggest' => 'ремонт фасада стоимость работ', 'engine' => 'yandex', 'level' => 1, 'words' => 4, 'type' => 'phrase'],
            ['seed' => 'ремонт фасада', 'query' => 'ремонт фасада', 'suggest' => 'капитальный ремонт фасада', 'engine' => 'yandex', 'level' => 1, 'words' => 3, 'type' => 'phrase'],
            ['seed' => 'ремонт фасада', 'query' => 'ремонт фасада', 'suggest' => 'ремонт фасада многоквартирного дома', 'engine' => 'yandex', 'level' => 1, 'words' => 4, 'type' => 'phrase'],
        ];

        SearchSuggestionsHistory::query()->create([
            'user_id' => $userId,
            'title' => 'Демо: ремонт фасада',
            'params' => [
                'seeds' => ['ремонт фасада'],
                'engines' => ['yandex'],
                'modes' => ['phrase' => true, 'space' => false, 'en' => false, 'ru' => true, 'digits' => false],
                'presets' => ['local' => false, 'shopping' => false, 'questions' => false, 'reviews' => false],
                'depth' => 1,
                'yandex_lr' => '213',
                'stop_words' => [],
            ],
            'results' => $results,
            'seeds_count' => 1,
            'results_count' => count($results),
            'cost' => 1,
        ]);

        $this->line('search-suggestions: OK');
    }

    private function seedDomainRecords(int $userId): void
    {
        if (DomainRecordsHistory::query()->where('user_id', $userId)->exists()) {
            $this->line('domain-records: уже есть');

            return;
        }

        $snapshot = null;
        try {
            $raw = (new DomainRecordsService())->lookup('titlo.ru');
            if (! empty($raw['ok'])) {
                $snapshot = $raw;
            }
        } catch (\Throwable $e) {
            $this->warn('domain-records: live lookup failed, using static fixture');
        }

        if ($snapshot === null) {
            $snapshot = [
                'ok' => true,
                'domain' => 'titlo.ru',
                'punycode' => 'titlo.ru',
                'whois' => [
                    'ok' => true,
                    'status_key' => 'ok',
                    'broken' => false,
                    'registered_at' => '2026-04-21',
                    'expires_at' => '2027-04-21',
                    'days_until_expiry' => 276,
                    'dns_servers' => ['dns1.dns-root.ru', 'dns2.dns-root.org', 'dns3.dns-root.pro'],
                ],
                'dns' => [
                    'A' => [['type' => 'A', 'value' => '155.212.171.103', 'target' => '155.212.171.103']],
                    'AAAA' => [],
                    'MX' => [['type' => 'MX', 'value' => '10 mail.titlo.ru', 'target' => 'mail.titlo.ru', 'pri' => 10]],
                    'NS' => [
                        ['type' => 'NS', 'value' => 'dns1.dns-root.ru'],
                        ['type' => 'NS', 'value' => 'dns2.dns-root.org'],
                    ],
                    'TXT' => [],
                    'CNAME' => [],
                    'SOA' => [],
                ],
                'ips' => [[
                    'ip' => '155.212.171.103',
                    'neighbors' => ['titlo.ru', 'cabinet.titlo.ru'],
                    'neighbors_loaded' => true,
                ]],
                'summary' => [
                    'registered' => true,
                    'status_key' => 'ok',
                    'expires_at' => '2027-04-21',
                    'days_until_expiry' => 276,
                    'ns' => ['dns1.dns-root.ru', 'dns2.dns-root.org'],
                    'a_count' => 1,
                    'mx_count' => 1,
                ],
            ];
        }

        DomainRecordsHistory::query()->create([
            'user_id' => $userId,
            'domain' => 'titlo.ru',
            'snapshot' => $snapshot,
        ]);

        $this->line('domain-records: OK');
    }

    private function seedSiteTypes(int $userId): void
    {
        if (SiteTypesHistory::query()->where('user_id', $userId)->exists()) {
            $this->line('site-types: уже есть');

            return;
        }

        $categories = [];
        foreach (config('cabinet-site-types.categories', []) as $key => $cat) {
            $categories[$key] = [
                'label' => $cat['label'] ?? $key,
                'short' => $cat['short'] ?? $key,
                'color' => $cat['color'] ?? '#64748b',
                'hint' => $cat['hint'] ?? '',
            ];
        }
        $categories['unknown'] = [
            'label' => 'Не определён',
            'short' => '?',
            'color' => '#94a3b8',
            'hint' => '',
        ];

        $rows = [
            ['position' => 1, 'url' => 'https://www.divan.ru/...', 'domain' => 'divan.ru', 'type' => 'ecommerce', 'type_source' => 'catalog', 'in_catalog' => true],
            ['position' => 2, 'url' => 'https://www.mvideo.ru/...', 'domain' => 'mvideo.ru', 'type' => 'ecommerce', 'type_source' => 'catalog', 'in_catalog' => true],
            ['position' => 3, 'url' => 'https://www.wildberries.ru/...', 'domain' => 'wildberries.ru', 'type' => 'aggregators', 'type_source' => 'catalog', 'in_catalog' => true],
            ['position' => 4, 'url' => 'https://www.ozon.ru/...', 'domain' => 'ozon.ru', 'type' => 'aggregators', 'type_source' => 'catalog', 'in_catalog' => true],
            ['position' => 5, 'url' => 'https://market.yandex.ru/...', 'domain' => 'market.yandex.ru', 'type' => 'aggregators', 'type_source' => 'catalog', 'in_catalog' => true],
            ['position' => 6, 'url' => 'https://hoff.ru/...', 'domain' => 'hoff.ru', 'type' => 'ecommerce', 'type_source' => 'catalog', 'in_catalog' => true],
            ['position' => 7, 'url' => 'https://www.askona.ru/...', 'domain' => 'askona.ru', 'type' => 'ecommerce', 'type_source' => 'catalog', 'in_catalog' => true],
            ['position' => 8, 'url' => 'https://example-shop.ru/...', 'domain' => 'example-shop.ru', 'type' => 'ecommerce', 'type_source' => 'html', 'in_catalog' => false],
            ['position' => 9, 'url' => 'https://mebel-blog.ru/...', 'domain' => 'mebel-blog.ru', 'type' => 'content', 'type_source' => 'html', 'in_catalog' => false],
            ['position' => 10, 'url' => 'https://unknown-site.ru/...', 'domain' => 'unknown-site.ru', 'type' => 'unknown', 'type_source' => 'url', 'in_catalog' => false],
        ];

        $counts = [
            'aggregators' => 3,
            'ecommerce' => 5,
            'organizations' => 0,
            'content' => 1,
            'social' => 0,
            'reviews' => 0,
            'news' => 0,
            'games' => 0,
            'unknown' => 1,
        ];
        $mix = [];
        foreach ($counts as $k => $n) {
            $mix[$k] = round(($n / 10) * 100, 1);
        }

        $results = [
            'summary' => [
                'total_positions' => 10,
                'counts' => $counts,
                'mix' => $mix,
                'verdict' => [
                    'code' => 'commercial',
                    'label' => 'Коммерческая выдача',
                    'hint' => 'Доминируют агрегаторы, магазины и сайты организаций.',
                ],
                'phrases' => 1,
                'engines' => 1,
            ],
            'phrase_matrix' => [],
            'frequent_hosts' => [
                ['host' => 'divan.ru', 'count' => 1, 'type' => 'ecommerce', 'in_catalog' => true],
                ['host' => 'wildberries.ru', 'count' => 1, 'type' => 'aggregators', 'in_catalog' => true],
            ],
            'queries' => [[
                'phrase' => 'купить диван',
                'engine' => 'yandex',
                'region' => '213',
                'rows' => $rows,
                'counts' => $counts,
                'total' => 10,
                'mix' => $mix,
                'verdict' => [
                    'code' => 'commercial',
                    'label' => 'Коммерческая выдача',
                    'hint' => 'Доминируют агрегаторы, магазины и сайты организаций.',
                ],
                'error' => false,
            ]],
            'categories' => $categories,
            'depth' => 10,
        ];

        SiteTypesHistory::query()->create([
            'user_id' => $userId,
            'title' => 'Демо: купить диван',
            'params' => [
                'phrases' => ['купить диван'],
                'engines' => ['yandex'],
                'depth' => 10,
                'yandex_lr' => '213',
                'google_lr' => '1011969',
                'custom_domains' => [],
            ],
            'results' => $results,
            'phrases_count' => 1,
            'results_count' => 10,
            'cost' => 1,
        ]);

        $this->line('site-types: OK');
    }

    private function seedPhraseCommerce(int $userId): void
    {
        if (PhraseCommerceHistory::query()->where('user_id', $userId)->exists()) {
            $this->line('phrase-commerce: уже есть');

            return;
        }

        $results = [
            'summary' => [
                'total' => 1,
                'geo_dependent' => 1,
                'geo_independent' => 0,
                'commercial' => 1,
                'informational' => 0,
                'avg_localization' => 20.0,
                'avg_commerce' => 70.0,
            ],
            'depth' => 10,
            'rows' => [[
                'phrase' => 'купить диван москва',
                'engine' => 'yandex',
                'region' => '213',
                'region_contrast' => '2',
                'region_name' => 'Москва',
                'region_contrast_name' => 'Санкт-Петербург',
                'geo' => [
                    'code' => 'geo_dependent',
                    'label' => 'Геозависимый',
                    'overlap' => 0.2,
                    'overlap_pct' => 20,
                    'shared' => 2,
                    'incomplete' => false,
                ],
                'localization' => [
                    'code' => 'medium',
                    'label' => 'Средняя',
                    'pct' => 20.0,
                    'local' => 2,
                    'total' => 10,
                ],
                'commerce' => [
                    'code' => 'commercial',
                    'label' => 'Коммерческий',
                    'pct' => 70.0,
                    'commercial' => 7,
                    'total' => 10,
                ],
                'serp_count' => 10,
                'serp_contrast_count' => 10,
                'serp_primary' => [],
                'serp_contrast' => [],
                'types' => ['ecommerce' => 4, 'aggregators' => 3, 'content' => 2, 'unknown' => 1],
                'error' => false,
            ]],
        ];

        PhraseCommerceHistory::query()->create([
            'user_id' => $userId,
            'title' => 'Демо: купить диван москва',
            'params' => [
                'phrases' => ['купить диван москва'],
                'engines' => ['yandex'],
                'yandex_lr' => '213',
                'google_lr' => '1011969',
            ],
            'results' => $results,
            'phrases_count' => 1,
            'results_count' => 1,
            'cost' => 2,
        ]);

        $this->line('phrase-commerce: OK');
    }

    /**
     * Клонируем завершённый анализ только с аккаунта-источника (sv6).
     * Берём снимок не старше cleaning_interval (иначе крон уже обнуляет деталку).
     */
    private function seedRelevance(int $userId): void
    {
        if (ProjectRelevanceHistory::query()->where('user_id', $userId)->exists()) {
            $this->line('relevance: уже есть');

            return;
        }

        $sourceEmail = (string) config('cabinet-demo-cabinet.source_email', 'sv6@list.ru');
        $sourceUser = User::query()->where('email', $sourceEmail)->first();
        if (! $sourceUser) {
            $this->warn('relevance: нет пользователя-источника ' . $sourceEmail);

            return;
        }

        $maxAgeDays = $this->relevanceSourceMaxAgeDays();
        $since = Carbon::now()->subDays($maxAgeDays);
        $preferredProject = (string) config('cabinet-demo-cabinet.relevance_source_project', 'lormag.ru');

        $source = $this->findFreshRelevanceSource($sourceUser->id, $since, $preferredProject);
        if (! $source && $preferredProject !== '') {
            $this->warn('relevance: в «' . $preferredProject . '» нет свежего снимка (≤' . $maxAgeDays . ' дн.) — ищем любой проект ' . $sourceEmail);
            $source = $this->findFreshRelevanceSource($sourceUser->id, $since, '');
        }

        if (! $source) {
            $this->warn(
                'relevance: у ' . $sourceEmail
                . ' нет свежего несжатого снимка за последние ' . $maxAgeDays
                . ' дн. (cleaning_interval / DEMO_CABINET_RELEVANCE_MAX_AGE_DAYS)'
            );

            return;
        }

        /** @var RelevanceHistoryResult|null $sourceResult */
        $sourceResult = RelevanceHistoryResult::query()
            ->where('project_id', $source->id)
            ->where('cleaning', 0)
            ->where(function (Builder $q) {
                $q->whereNotNull('unigram_table')->where('unigram_table', '!=', '');
            })
            ->first();

        if (! $sourceResult) {
            $this->warn('relevance: у исходной истории #' . $source->id . ' нет живого result');

            return;
        }

        $sourceProject = ProjectRelevanceHistory::query()->find($source->project_relevance_history_id);
        $projectName = $sourceProject && $sourceProject->name
            ? (string) $sourceProject->name
            : 'demo-project.ru';

        $now = now();
        $project = ProjectRelevanceHistory::query()->create([
            'name' => $projectName,
            'user_id' => $userId,
            'last_check' => $now,
            'total_points' => (int) $source->points,
            'count_sites' => 1,
            'count_checks' => 1,
            'avg_position' => (int) $source->position,
            'group_name' => '',
        ]);

        $history = RelevanceHistory::query()->create([
            'phrase' => $source->phrase ?: 'демо фраза',
            'region' => $source->region ?: '213',
            'main_link' => $source->main_link ?: 'https://example.com/',
            'html_main_page' => '',
            'last_check' => $now,
            'points' => $source->points,
            'position' => $source->position,
            'coverage' => $source->coverage,
            'coverage_tf' => $source->coverage_tf,
            'width' => $source->width,
            'density' => $source->density,
            'calculate' => 1,
            'project_relevance_history_id' => $project->id,
            'comment' => 'Демо-снимок (' . $sourceEmail . ', ≤' . $maxAgeDays . ' дн.)',
            'state' => 1,
            'request' => $source->request,
            'sites' => '',
            'user_id' => $userId,
        ]);

        $copyCols = [
            'clouds_competitors',
            'clouds_main_page',
            'avg',
            'main_page',
            'unigram_table',
            'sites',
            'tf_comp_clouds',
            'phrases',
            'avg_coverage_percent',
            'recommendations',
            'compressed',
            'cleaning',
            'average_values',
            'hash',
        ];
        $payload = ['project_id' => $history->id];
        foreach ($copyCols as $col) {
            // Берём сырые значения из БД — без кастов/мутаторов.
            $payload[$col] = $sourceResult->getAttributes()[$col] ?? $sourceResult->{$col};
        }
        // Блобы уже в формате base64(gzcompress(...)). compressed=0 заставит
        // getDetailsInfo сжать их повторно → пустая деталка.
        $payload['cleaning'] = 0;
        $payload['compressed'] = 1;

        RelevanceHistoryResult::query()->create($payload);

        // Smoke: однослойная распаковка должна давать массив.
        $cloned = RelevanceHistoryResult::query()->where('project_id', $history->id)->first();
        $probe = Relevance::uncompressItem($cloned->getAttributes()['unigram_table'] ?? '');
        $ageDays = (int) Carbon::parse($sourceResult->created_at ?: $source->created_at)->diffInDays($now);
        if (! is_array($probe) || $probe === []) {
            $this->warn('relevance: клон #' . $history->id . ' не распаковывается — удалите и пересоздайте с --fresh');
        } else {
            $this->line(
                'relevance: OK (клон ' . $sourceEmail
                . ' history #' . $source->id
                . ' → #' . $history->id
                . ', проект ' . $projectName
                . ', unigram=' . count($probe)
                . ', возраст ~' . $ageDays . ' дн., лимит ' . $maxAgeDays . ')'
            );
        }
    }

    /**
     * Окно свежести: cleaning_interval из конфига модуля (после него деталка обнуляется кроном).
     */
    private function relevanceSourceMaxAgeDays(): int
    {
        $configured = config('cabinet-demo-cabinet.relevance_source_max_age_days');
        if ($configured !== null && $configured !== '') {
            return max(1, (int) $configured);
        }

        $fromModule = (int) optional(RelevanceAnalysisConfig::query()->first())->cleaning_interval;

        return max(1, $fromModule > 0 ? $fromModule : 180);
    }

    private function findFreshRelevanceSource(int $sourceUserId, Carbon $since, string $preferredProjectName): ?RelevanceHistory
    {
        $base = RelevanceHistory::query()
            ->where('user_id', $sourceUserId)
            ->where('state', 1)
            ->where('calculate', 1)
            ->whereHas('results', function (Builder $q) use ($since) {
                $q->where('cleaning', 0)
                    ->where('created_at', '>=', $since)
                    ->whereNotNull('unigram_table')
                    ->where('unigram_table', '!=', '');
            })
            ->orderByRaw('CASE WHEN position > 0 AND position <= 30 THEN 0 WHEN position > 0 THEN 1 ELSE 2 END')
            ->orderBy('position')
            ->orderByDesc('id');

        if ($preferredProjectName !== '') {
            $projectId = ProjectRelevanceHistory::query()
                ->where('user_id', $sourceUserId)
                ->where('name', $preferredProjectName)
                ->orderByDesc('id')
                ->value('id');
            if (! $projectId) {
                return null;
            }
            $base->where('project_relevance_history_id', $projectId);
        }

        return $base->first();
    }
}
