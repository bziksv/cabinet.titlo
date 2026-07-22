{{-- Дерево отчётов по приоритетам. Ожидает: $tree, $bucketLabels, $crawl, $activeCode, $treeTitle, $showGroup? --}}
<aside class="cabinet-sa-tree" data-sa-tree>
    <div class="px-3 py-2 border-bottom fw-semibold small">{{ $treeTitle ?? 'Отчёты' }}</div>
    @include('pages.partials.site-audit-tree-controls')
    @foreach(($bucketLabels ?? []) as $sev => $label)
        <div class="cabinet-sa-tree__group" data-severity-group="{{ $sev }}">
            <div class="cabinet-sa-tree__group-title">{{ $label }}</div>
            @foreach(($tree[$sev] ?? []) as $item)
                <a class="cabinet-sa-tree__item {{ ($activeCode ?? '') === $item['code'] ? 'is-active' : '' }} {{ $item['count'] ? '' : 'is-empty' }}"
                   href="{{ route('pages.site-audit.report.show', [$crawl->id, $item['code']]) }}"
                   data-title="{{ $item['title'] }}"
                   data-severity="{{ $sev }}"
                   data-count="{{ (int) $item['count'] }}">
                    <span>
                        {{ $item['title'] }}
                        <span class="cabinet-sa-sev">({{ \App\Services\SiteAudit\SiteAuditFindingPresenter::severityTag($sev) }})</span>
                        @if(!empty($showGroup) && !empty($item['group']))
                            <span class="cabinet-sa-group-tag cabinet-sa-group-tag--{{ $item['group'] }}">{{ $item['group'] === 'seo' ? 'SEO' : 'тех' }}</span>
                        @endif
                    </span>
                    <span class="cabinet-sa-badge cabinet-sa-badge--{{ $item['count'] > 0 ? $sev : 'zero' }}">{{ $item['count'] }}</span>
                </a>
            @endforeach
        </div>
    @endforeach
</aside>
