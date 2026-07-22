<?php

namespace App\Services\SiteAudit;

use Illuminate\Support\Collection;

class SiteAuditDuplicateGrouper
{
    private const GROUPABLE = [
        'duplicate_title',
        'duplicate_description',
        'duplicate_content',
    ];

    public static function isGroupable(string $code): bool
    {
        return in_array($code, self::GROUPABLE, true);
    }

    /**
     * @param  Collection|iterable  $rows  SiteAuditFinding-like objects with meta_json
     * @return array<int, array{hash:string,size:int,label:string,severity:string,urls:array<int,array{url:string,severity:string}>}>
     */
    public static function group($rows, string $code): array
    {
        $buckets = [];

        foreach ($rows as $row) {
            $meta = is_array($row->meta_json ?? null) ? $row->meta_json : [];
            $hash = (string) ($meta['hash'] ?? '');
            if ($hash === '') {
                $hash = 'u:' . md5((string) ($row->url ?? '') . '|' . ($row->id ?? uniqid('', true)));
            }

            if (! isset($buckets[$hash])) {
                $buckets[$hash] = [
                    'hash' => $hash,
                    'size' => (int) ($meta['group_size'] ?? 0),
                    'label' => self::labelFor($code, $meta),
                    'severity' => (string) ($row->severity ?? 'other'),
                    'urls' => [],
                ];
            }

            $buckets[$hash]['urls'][] = [
                'url' => (string) $row->url,
                'severity' => (string) ($row->severity ?? 'other'),
            ];

            if ($buckets[$hash]['size'] < count($buckets[$hash]['urls'])) {
                $buckets[$hash]['size'] = count($buckets[$hash]['urls']);
            }
        }

        $groups = array_values($buckets);
        usort($groups, static function (array $a, array $b) {
            if ($a['size'] === $b['size']) {
                return strcmp($a['label'], $b['label']);
            }

            return $b['size'] <=> $a['size'];
        });

        return $groups;
    }

    private static function labelFor(string $code, array $meta): string
    {
        if ($code === 'duplicate_description' && ! empty($meta['description'])) {
            return (string) $meta['description'];
        }
        if (! empty($meta['title'])) {
            return (string) $meta['title'];
        }
        if (! empty($meta['description'])) {
            return (string) $meta['description'];
        }

        return 'Совпадение без текста';
    }
}
