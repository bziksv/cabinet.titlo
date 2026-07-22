<?php

namespace App\Services\SiteAudit;

/**
 * Извлечение ссылок и внешних ресурсов из HTML.
 */
class SiteAuditLinkExtractor
{
    /**
     * @param array $opts normalize options
     * @return array{
     *   internal:string[],
     *   external:string[],
     *   nofollow_links:int,
     *   external_assets:string[],
     *   meta_nofollow:bool
     * }
     */
    public function extract(string $html, string $baseUrl, string $projectHost, array $opts = []): array
    {
        $internal = [];
        $internalCounts = [];
        $external = [];
        $nofollowLinks = 0;
        $externalAssets = [];

        $robots = [];
        if (preg_match_all('/<meta\b[^>]*\bname\s*=\s*["\']robots["\'][^>]*>/i', $html, $mt)) {
            foreach ($mt[0] as $tag) {
                if (preg_match('/\bcontent\s*=\s*("([^"]*)"|\'([^\']*)\')/i', $tag, $m)) {
                    $robots[] = (isset($m[2]) && $m[2] !== '') ? $m[2] : ($m[3] ?? '');
                }
            }
        }
        $metaNofollow = false;
        foreach ($robots as $r) {
            if (preg_match('/\bnofollow\b/i', $r)) {
                $metaNofollow = true;
                break;
            }
        }

        if (preg_match_all('/<a\b([^>]*)>/i', $html, $anchors)) {
            foreach ($anchors[1] as $attrs) {
                if (! preg_match('/\bhref\s*=\s*("([^"]*)"|\'([^\']*)\')/i', $attrs, $hm)) {
                    continue;
                }
                $hrefRaw = (isset($hm[2]) && $hm[2] !== '') ? $hm[2] : ($hm[3] ?? '');
                $href = html_entity_decode(trim($hrefRaw), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                if ($href === '' || $href[0] === '#' || stripos($href, 'javascript:') === 0 || stripos($href, 'mailto:') === 0) {
                    continue;
                }

                $isNofollow = (bool) preg_match('/\brel\s*=\s*["\'][^"\']*\bnofollow\b/i', $attrs);
                if ($isNofollow) {
                    $nofollowLinks++;
                }

                $abs = SiteAuditUrlNormalizer::resolve($href, $baseUrl, $projectHost, $opts);
                if ($abs) {
                    $internal[$abs] = true;
                    $internalCounts[$abs] = ($internalCounts[$abs] ?? 0) + 1;
                } else {
                    $any = SiteAuditUrlNormalizer::resolve($href, $baseUrl, null, $opts);
                    if ($any) {
                        $external[$any] = true;
                    }
                }
            }
        }

        $patterns = [
            '/<script\b[^>]*\bsrc\s*=\s*["\']([^"\']+)["\']/i',
            '/<link\b[^>]*\bhref\s*=\s*["\']([^"\']+)["\']/i',
            '/<img\b[^>]*\bsrc\s*=\s*["\']([^"\']+)["\']/i',
        ];
        foreach ($patterns as $re) {
            if (! preg_match_all($re, $html, $mm)) {
                continue;
            }
            foreach ($mm[1] as $src) {
                $src = html_entity_decode(trim($src), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                if ($src === '' || strpos($src, 'data:') === 0) {
                    continue;
                }
                $abs = SiteAuditUrlNormalizer::resolve($src, $baseUrl, null, $opts);
                if (! $abs) {
                    continue;
                }
                $h = SiteAuditUrlNormalizer::hostOf($abs);
                if (! $h) {
                    continue;
                }
                $bare = preg_replace('/^www\./', '', $h);
                $baseBare = preg_replace('/^www\./', '', strtolower($projectHost));
                if ($bare !== $baseBare) {
                    $externalAssets[$abs] = true;
                    if (count($externalAssets) >= 20) {
                        break 2;
                    }
                }
            }
        }

        $dupLinks = [];
        foreach ($internalCounts as $u => $c) {
            if ($c > 1) {
                $dupLinks[] = ['url' => $u, 'count' => $c];
                if (count($dupLinks) >= 10) {
                    break;
                }
            }
        }

        return [
            'internal' => array_keys($internal),
            'external' => array_keys($external),
            'nofollow_links' => $nofollowLinks,
            'external_assets' => array_keys($externalAssets),
            'meta_nofollow' => $metaNofollow,
            'duplicate_links' => $dupLinks,
            'duplicate_links_count' => count(array_filter($internalCounts, function ($c) {
                return $c > 1;
            })),
        ];
    }
}
