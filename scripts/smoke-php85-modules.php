<?php

/**
 * Smoke всех модулей кабинета под текущим PHP (ожидается 8.5).
 *
 *   /opt/homebrew/opt/php@8.5/bin/php scripts/smoke-php85-modules.php [user_id]
 */

require __DIR__ . '/../bootstrap/php85-compat.php';
require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$userId = (int) ($argv[1] ?? 4);
$user = App\User::find($userId);
if (!$user) {
    fwrite(STDERR, "User not found: {$userId}\n");
    exit(1);
}

Auth::login($user);
if (function_exists('apply_global_team_permissions')) {
    apply_global_team_permissions();
}

echo 'PHP ' . PHP_VERSION . " | user #{$user->id} {$user->email}\n";
echo str_repeat('-', 88) . "\n";
printf("%-52s %5s %7s %s\n", 'URI', 'HTTP', 'ms', 'NOTE');
echo str_repeat('-', 88) . "\n";

$pickId = static function (callable $fn): int {
    try {
        return (int) ($fn() ?: 0);
    } catch (Throwable $e) {
        return 0;
    }
};

$ids = [
    'site_audit_crawl' => $pickId(static function () {
        return App\SiteAuditCrawl::query()->where('status', 'done')->orderByDesc('id')->value('id');
    }),
    'seo_report_project' => $pickId(static function () {
        return DB::table('seo_report_projects')->orderByDesc('id')->value('id');
    }),
    'seo_report' => $pickId(static function () {
        return DB::table('seo_reports')->where('status', 'ready')->orderByDesc('id')->value('id');
    }),
    'checklist' => $pickId(static function () {
        return DB::table('seo_checklist_projects')->orderByDesc('id')->value('id');
    }),
    'monitoring' => $pickId(static function () {
        return DB::table('monitoring_projects')->orderByDesc('id')->value('id');
    }),
    'relevance' => $pickId(static function () {
        if (class_exists(App\ProjectRelevance::class)) {
            return App\ProjectRelevance::query()->orderByDesc('id')->value('id');
        }

        return DB::table('projects_relevance')->orderByDesc('id')->value('id');
    }),
    'cluster' => $pickId(static function () {
        return DB::table('clusters')->orderByDesc('id')->value('id');
    }),
    'meta_tags' => $pickId(static function () {
        return DB::table('meta_tags')->orderByDesc('id')->value('id');
    }),
];

$paths = [
    // core
    ['/', 'home'],
    ['/login', 'guest login'],
    ['/profile/', 'profile'],
    ['/balance', 'balance'],
    ['/tariff', 'tariff'],
    ['/partners', 'partners'],
    ['/history', 'history'],
    ['/news', 'news'],
    ['/configuration-menu', 'menu config'],

    // monitoring
    ['/monitoring', 'monitoring list'],
    ['/monitoring-v2', 'monitoring v2'],
    ['/site-monitoring', 'uptime'],
    ['/domain-information', 'domain info'],

    // site audit / reports / checklist
    ['/site-audit', 'site audit'],
    ['/reports', 'seo reports'],
    ['/reports/templates', 'seo report templates'],
    ['/checklist', 'seo checklist'],
    ['/checklist/my-tasks', 'checklist my tasks'],
    ['/checklist/review', 'checklist review'],
    ['/checklist/chronicle', 'checklist chronicle'],
    ['/checklist/time', 'checklist timesheet'],
    ['/checklist/team', 'checklist team'],
    ['/checklist/templates', 'checklist templates'],

    // relevance / competitor / text
    ['/analyze-relevance', 'relevance'],
    ['/competitor-analysis', 'competitors'],
    ['/text-analyzer', 'text analyzer'],
    ['/text-uniqueness', 'text uniqueness'],
    ['/esenin-text-check/sessions', 'esenin sessions'],
    ['/unique', 'unique'],
    ['/counting-text-length', 'text length'],
    ['/html-editor', 'html editor'],

    // cluster / words
    ['/cluster', 'cluster'],
    ['/duplicates', 'duplicates'],
    ['/list-comparison', 'list comparison'],
    ['/keyword-generator', 'keyword gen'],
    ['/search-suggestions', 'search suggestions'],
    ['/phrase-commerce', 'phrase commerce'],

    // tech tools
    ['/meta-tags', 'meta tags'],
    ['/backlink', 'backlinks'],
    ['/http-headers', 'http headers'],
    ['/index-check', 'index check'],
    ['/domain-records', 'domain records'],
    ['/site-types', 'site types'],
    ['/utm-marks', 'utm'],
    ['/roi-calculator', 'roi'],
    ['/password-generator', 'password'],
];

if ($ids['site_audit_crawl'] > 0) {
    $c = $ids['site_audit_crawl'];
    $paths[] = ["/site-audit/crawl/{$c}", 'audit crawl'];
    $paths[] = ["/site-audit/crawl/{$c}/status", 'audit status json'];
    $paths[] = ["/site-audit/crawl/{$c}/diff", 'audit diff'];
}
if ($ids['seo_report_project'] > 0) {
    $p = $ids['seo_report_project'];
    $paths[] = ["/reports/{$p}", 'seo report project'];
    $paths[] = ["/reports/{$p}/settings", 'seo report settings'];
    if ($ids['seo_report'] > 0) {
        $r = $ids['seo_report'];
        // report may belong to another project — resolve
        $row = DB::table('seo_reports')->where('id', $r)->first();
        if ($row) {
            $paths[] = ["/reports/{$row->project_id}/reports/{$row->id}", 'seo report body'];
        }
    }
}
if ($ids['checklist'] > 0) {
    $paths[] = ["/checklist/{$ids['checklist']}", 'checklist show'];
}
if ($ids['monitoring'] > 0) {
    $paths[] = ["/monitoring/{$ids['monitoring']}", 'monitoring project'];
}
if ($ids['relevance'] > 0) {
    $paths[] = ["/analyze-relevance/{$ids['relevance']}", 'relevance project'];
}
if ($ids['cluster'] > 0) {
    $paths[] = ["/cluster/{$ids['cluster']}", 'cluster project'];
}
if ($ids['meta_tags'] > 0) {
    $paths[] = ["/meta-tags/{$ids['meta_tags']}", 'meta tags project'];
}

$ok = 0;
$warn = 0;
$fail = 0;
$failures = [];

foreach ($paths as $item) {
    [$uri, $label] = $item;
    $t = microtime(true);
    $note = $label;
    $code = 0;
    try {
        ob_start();
        $resp = $kernel->handle(Illuminate\Http\Request::create($uri, 'GET'));
        $body = $resp->getContent();
        ob_end_clean();
        $code = (int) $resp->getStatusCode();
        $ms = (int) round((microtime(true) - $t) * 1000);

        $errHint = '';
        if (is_string($body)) {
            if (stripos($body, 'Whoops') !== false || stripos($body, 'ErrorException') !== false) {
                $errHint = 'whoops/exception in body';
            } elseif (stripos($body, 'Allowed memory size') !== false) {
                $errHint = 'OOM in body';
            } elseif (preg_match('/Fatal error|Parse error|Uncaught /i', $body)) {
                $errHint = 'fatal in body';
            }
        }

        $bad = $code >= 500 || $errHint !== '';
        $soft = in_array($code, [302, 403, 404], true);

        if ($bad) {
            $fail++;
            $note = ($errHint !== '' ? $errHint : 'HTTP ' . $code) . " · {$label}";
            $failures[] = compact('uri', 'code', 'note');
            $mark = 'FAIL';
        } elseif ($soft) {
            $warn++;
            $note = "HTTP {$code} · {$label}";
            $mark = 'WARN';
        } else {
            $ok++;
            $mark = 'OK';
        }

        printf("%-52s %5d %6dms  %s %s\n", $uri, $code, $ms, $mark, $note);
        $kernel->terminate(Illuminate\Http\Request::create($uri, 'GET'), $resp);
    } catch (Throwable $e) {
        $ms = (int) round((microtime(true) - $t) * 1000);
        $fail++;
        $note = get_class($e) . ': ' . mb_substr($e->getMessage(), 0, 120);
        $failures[] = ['uri' => $uri, 'code' => 0, 'note' => $note];
        printf("%-52s %5s %6dms  FAIL %s\n", $uri, 'EXC', $ms, $note);
        if (ob_get_level() > 0) {
            ob_end_clean();
        }
    }
}

echo str_repeat('-', 88) . "\n";
echo "IDs used: " . json_encode($ids) . "\n";
echo "OK={$ok} WARN={$warn} FAIL={$fail}\n";

if ($failures) {
    echo "\nFAILURES:\n";
    foreach ($failures as $f) {
        echo " - {$f['uri']} → {$f['code']} {$f['note']}\n";
    }
    exit(2);
}

exit(0);
