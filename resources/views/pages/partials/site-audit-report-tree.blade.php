{{-- Дерево отчётов по приоритетам. Ожидает: $tree, $bucketLabels, $crawl, $activeCode, $treeTitle, $showGroup? --}}
<aside class="cabinet-sa-tree" data-sa-tree data-crawl-id="{{ (int) $crawl->id }}">
    <div class="px-3 py-2 border-bottom fw-semibold small">{{ $treeTitle ?? 'Отчёты' }}</div>
    @include('pages.partials.site-audit-tree-controls')
    @foreach(($bucketLabels ?? []) as $sev => $label)
        <div class="cabinet-sa-tree__group" data-severity-group="{{ $sev }}">
            <div class="cabinet-sa-tree__group-title">{{ $label }}</div>
            @foreach(($tree[$sev] ?? []) as $item)
                @include('pages.partials.site-audit-tree-item', [
                    'item' => $item,
                    'sev' => $sev,
                    'crawl' => $crawl,
                    'activeCode' => $activeCode ?? null,
                    'showGroup' => $showGroup ?? false,
                ])
            @endforeach
        </div>
    @endforeach
</aside>
