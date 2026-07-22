@php
    $seoCodes = config('site_audit.seo_codes', []);
    $group = $group ?? null;
    $reportRoute = !empty($isPublic)
        ? function ($code) use ($token) {
            return route('site-audit.public.share.report', [$token, $code]);
        }
        : function ($code) use ($crawl) {
            return route('pages.site-audit.report.show', [$crawl->id, $code]);
        };

    $hot = collect($counts ?? [])->filter(function ($c, $code) use ($group, $seoCodes, $findingsCatalog) {
        if ((int) $c <= 0) {
            return false;
        }
        if (! isset($findingsCatalog[$code]) && $code !== 'pages_with_canonical') {
            return false;
        }
        if ($group === null) {
            return true;
        }
        $meta = $findingsCatalog[$code] ?? [];
        $g = $meta['group'] ?? (in_array($code, $seoCodes, true) ? 'seo' : 'tech');

        return $g === $group;
    })->sortByDesc(function ($c) {
        return (int) $c;
    });
@endphp

@if($hot->isNotEmpty())
    <div class="cabinet-sa-table-wrap">
        <table class="table table-sm mb-0">
            <thead class="thead-light">
            <tr>
                <th>Фактор</th>
                <th>Приоритет</th>
                <th class="text-right">Находок</th>
                <th class="text-right" style="width:1%"></th>
            </tr>
            </thead>
            <tbody>
            @foreach($hot as $code => $cnt)
                @php
                    $meta = $findingsCatalog[$code] ?? [];
                    $sev = (string) ($meta['severity'] ?? 'other');
                @endphp
                <tr>
                    <td>
                        <a href="{{ $reportRoute($code) }}">
                            {{ $meta['title'] ?? $code }}
                        </a>
                    </td>
                    <td class="small text-muted">
                        {{ \App\Services\SiteAudit\SiteAuditFindingPresenter::severityTag($sev) }}
                    </td>
                    <td class="text-right">
                        <span class="cabinet-sa-badge cabinet-sa-badge--{{ $sev }}">{{ $cnt }}</span>
                    </td>
                    <td class="text-right text-nowrap">
                        <a class="btn btn-sm btn-outline-primary py-0 px-2" href="{{ $reportRoute($code) }}">Показать</a>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
@elseif(($crawl->status ?? '') === 'done')
    <div class="alert alert-success mb-0">Находок в этой сводке нет.</div>
@else
    <div class="alert alert-info mb-0">Краул ещё выполняется — сводка обновится по завершении.</div>
@endif
