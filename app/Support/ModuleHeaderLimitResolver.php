<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Код тарифного лимита для блока «Осталось» в глобальной шапке на страницах модулей.
 */
class ModuleHeaderLimitResolver
{
    /** @var list<array{prefix: string, code: string}> — более длинные префиксы выше */
    private const PATH_RULES = [
        ['prefix' => 'meta-tags/history', 'code' => 'MetaTagsPages'],
        ['prefix' => 'meta-tags/histories', 'code' => 'MetaTagsPages'],
        ['prefix' => 'meta-tags', 'code' => 'MetaTagsProject'],
        ['prefix' => 'competitor-analysis', 'code' => 'CompetitorAnalysisPhrases'],
        ['prefix' => 'competitors-config', 'code' => 'CompetitorAnalysisPhrases'],
        ['prefix' => 'monitoring-v2', 'code' => 'monitoring'],
        ['prefix' => 'monitoring/', 'code' => 'monitoring'],
        ['prefix' => 'monitoring', 'code' => 'monitoring'],
        ['prefix' => 'site-monitoring', 'code' => 'domainMonitoringProject'],
        ['prefix' => 'domain-information', 'code' => 'DomainInformation'],
        ['prefix' => 'text-analyzer', 'code' => 'TextAnalyzer'],
        ['prefix' => 'analyze-relevance', 'code' => 'RelevanceAnalysis'],
        ['prefix' => 'relevance-config', 'code' => 'RelevanceAnalysis'],
        ['prefix' => 'relevance/', 'code' => 'RelevanceAnalysis'],
        ['prefix' => 'history', 'code' => 'RelevanceAnalysis'],
        ['prefix' => 'show-cluster-result', 'code' => 'Clusters'],
        ['prefix' => 'edit-clusters', 'code' => 'Clusters'],
        ['prefix' => 'cluster', 'code' => 'Clusters'],
        ['prefix' => 'show-backlink', 'code' => 'BacklinkLinks'],
        ['prefix' => 'add-link', 'code' => 'BacklinkLinks'],
        ['prefix' => 'backlink', 'code' => 'BacklinkProject'],
        ['prefix' => 'password-generator', 'code' => 'PasswordGenerator'],
        ['prefix' => 'counting-text-length', 'code' => 'TextLength'],
        ['prefix' => 'list-comparison', 'code' => 'ListComparison'],
        ['prefix' => 'unique', 'code' => 'UniqueWords'],
        ['prefix' => 'html-editor', 'code' => 'HtmlEditor'],
        ['prefix' => 'create-project', 'code' => 'HtmlEditor'],
        ['prefix' => 'edit-project', 'code' => 'HtmlEditor'],
        ['prefix' => 'create-description', 'code' => 'HtmlEditor'],
        ['prefix' => 'edit-description', 'code' => 'HtmlEditor'],
        ['prefix' => 'duplicates', 'code' => 'RemoveDublicate'],
        ['prefix' => 'keyword-generator', 'code' => 'GeneratorWords'],
        ['prefix' => 'utm-marks', 'code' => 'UTM'],
        ['prefix' => 'roi-calculator', 'code' => 'ROI'],
        ['prefix' => 'http-headers', 'code' => 'HttpHeaders'],
        ['prefix' => 'index-check', 'code' => 'IndexCheck'],
        ['prefix' => 'site-audit', 'code' => 'SiteAuditCrawls'],
        ['prefix' => 'esenin-text-check', 'code' => 'EseninTextCheck'],
        ['prefix' => 'search-suggestions', 'code' => 'SearchSuggestions'],
        ['prefix' => 'domain-records', 'code' => 'DomainRecords'],
        ['prefix' => 'site-types', 'code' => 'SiteTypes'],
        ['prefix' => 'phrase-commerce', 'code' => 'PhraseCommerce'],
        ['prefix' => 'text-uniqueness', 'code' => 'TextUniqueness'],
    ];

    /** @var list<array{prefix: string, code: string}> */
    private const ROUTE_NAME_RULES = [
        ['prefix' => 'competitor.', 'code' => 'CompetitorAnalysisPhrases'],
        ['prefix' => 'text.analyzer', 'code' => 'TextAnalyzer'],
        ['prefix' => 'relevance', 'code' => 'RelevanceAnalysis'],
        ['prefix' => 'meta.history', 'code' => 'MetaTagsPages'],
        ['prefix' => 'meta-tags', 'code' => 'MetaTagsProject'],
        ['prefix' => 'meta.', 'code' => 'MetaTagsProject'],
        ['prefix' => 'monitoring.v2', 'code' => 'monitoring'],
        ['prefix' => 'monitoring.', 'code' => 'monitoring'],
        ['prefix' => 'site.monitoring', 'code' => 'domainMonitoringProject'],
        ['prefix' => 'domain.information', 'code' => 'DomainInformation'],
        ['prefix' => 'cluster.', 'code' => 'Clusters'],
        ['prefix' => 'show.backlink', 'code' => 'BacklinkLinks'],
        ['prefix' => 'backlink', 'code' => 'BacklinkProject'],
        ['prefix' => 'pages.password', 'code' => 'PasswordGenerator'],
        ['prefix' => 'pages.length', 'code' => 'TextLength'],
        ['prefix' => 'list.comparison', 'code' => 'ListComparison'],
        ['prefix' => 'unique', 'code' => 'UniqueWords'],
        ['prefix' => 'HTML.editor', 'code' => 'HtmlEditor'],
        ['prefix' => 'html.editor', 'code' => 'HtmlEditor'],
        ['prefix' => 'pages.duplicates', 'code' => 'RemoveDublicate'],
        ['prefix' => 'pages.keyword', 'code' => 'GeneratorWords'],
        ['prefix' => 'pages.utm', 'code' => 'UTM'],
        ['prefix' => 'pages.roi', 'code' => 'ROI'],
        ['prefix' => 'pages.headers', 'code' => 'HttpHeaders'],
        ['prefix' => 'pages.site-audit', 'code' => 'SiteAuditCrawls'],
        ['prefix' => 'pages.esenin', 'code' => 'EseninTextCheck'],
        ['prefix' => 'pages.search-suggestions', 'code' => 'SearchSuggestions'],
        ['prefix' => 'pages.domain-records', 'code' => 'DomainRecords'],
        ['prefix' => 'pages.site-types', 'code' => 'SiteTypes'],
        ['prefix' => 'pages.phrase-commerce', 'code' => 'PhraseCommerce'],
        ['prefix' => 'pages.text-uniqueness', 'code' => 'TextUniqueness'],
    ];

    public static function resolve(?Request $request = null): ?string
    {
        $request = $request ?? request();
        if ($request === null) {
            return null;
        }

        $path = trim($request->path(), '/');
        foreach (self::PATH_RULES as $rule) {
            if ($path === $rule['prefix'] || Str::startsWith($path, $rule['prefix'] . '/')) {
                return $rule['code'];
            }
        }

        $route = $request->route();
        $routeName = $route !== null ? (string) $route->getName() : '';
        if ($routeName !== '') {
            foreach (self::ROUTE_NAME_RULES as $rule) {
                if (Str::startsWith($routeName, $rule['prefix'])) {
                    return $rule['code'];
                }
            }
        }

        return null;
    }
}
