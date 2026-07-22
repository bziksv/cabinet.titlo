@extends('layouts.public-module')

@section('title', ($meta['title'] ?? $code) . ' · аудит')

@section('css')
    <link rel="stylesheet" href="{{ asset('css/cabinet-site-audit.css') }}?v={{ @filemtime(public_path('css/cabinet-site-audit.css')) ?: time() }}">
@endsection

@section('content')
    <div class="mb-3">
        <a href="{{ route('site-audit.public.share.view', $token) }}" class="btn btn-sm btn-outline-secondary">← К сводке</a>
        <a href="{{ route('site-audit.public.share.csv', [$token, $code]) }}{{ !empty($filterParams) ? ('?' . http_build_query($filterParams)) : '' }}" class="btn btn-sm btn-outline-primary">CSV</a>
        <a href="{{ route('site-audit.public.share.report.xlsx', [$token, $code]) }}{{ !empty($filterParams) ? ('?' . http_build_query($filterParams)) : '' }}" class="btn btn-sm btn-outline-success">XLSX</a>
        <button type="button" class="btn btn-sm btn-outline-secondary cabinet-sa-print-btn" onclick="window.print()">Печать</button>
    </div>

    <div class="card shadow-sm">
        <div class="card-header py-2">
            <h1 class="card-title h5 mb-0">{{ $meta['title'] ?? $code }} · краул #{{ $crawl->id }}</h1>
        </div>
        <div class="card-body cabinet-sa-page p-3">
            <div class="mb-2 text-muted small">
                {{ optional($project)->domain }} ·
                приоритет: <strong>{{ \App\Services\SiteAudit\SiteAuditFindingPresenter::severityLabel($meta['severity'] ?? '') }}</strong>
                · находок: <strong>{{ $total }}</strong>
                @if(!empty($filtersActive))
                    <span class="text-primary">(с фильтром)</span>
                @endif
            </div>

            <div class="cabinet-sa-desc mb-3">
                @include('pages.partials.site-audit-report-help')
            </div>

            @include('pages.partials.site-audit-report-filters')

            <div class="cabinet-sa-table-wrap">
                <table class="table table-sm table-hover mb-0">
                    <thead class="thead-light">
                    <tr>
                        <th style="width:50%">URL</th>
                        <th>Приоритет</th>
                        <th>Детали</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($rows as $row)
                        <tr>
                            <td class="cabinet-sa-url">
                                <a href="{{ $row->url }}" target="_blank" rel="noopener noreferrer">{{ $row->url }}</a>
                            </td>
                            <td>{{ \App\Services\SiteAudit\SiteAuditFindingPresenter::severityLabel($row->severity) }}</td>
                            <td class="small">
                                {{ \App\Services\SiteAudit\SiteAuditFindingPresenter::metaLine($row->code ?? $code, $row->meta_json) }}
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="text-muted px-3 py-3">Находок нет</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

            @if($pages > 1)
                <nav class="mt-3">
                    <ul class="pagination pagination-sm mb-0">
                        @for($p = 1; $p <= min($pages, 30); $p++)
                            <li class="page-item {{ $p === $page ? 'active' : '' }}">
                                <a class="page-link" href="{{ request()->fullUrlWithQuery(['page' => $p]) }}">{{ $p }}</a>
                            </li>
                        @endfor
                    </ul>
                </nav>
            @endif
        </div>
    </div>
@endsection
