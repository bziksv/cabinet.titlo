@extends('layouts.public-module')

@section('title', 'Аудит сайта · ' . (optional($project)->domain ?? 'краул'))

@section('css')
    <link rel="stylesheet" href="{{ asset('css/cabinet-site-audit.css') }}?v={{ @filemtime(public_path('css/cabinet-site-audit.css')) ?: time() }}">
@endsection

@section('content')
    <div class="alert alert-info mb-3">
        <div class="fw-semibold mb-1">Публичный доступ</div>
        <div class="small mb-0">Только просмотр сводки аудита, без регистрации.</div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header py-2 d-flex flex-wrap align-items-center gap-2">
            <h1 class="card-title h5 mb-0">Аудит сайта · {{ optional($project)->domain }}</h1>
            <span class="badge text-bg-secondary">краул #{{ $crawl->id }}</span>
            <a href="{{ route('site-audit.public.share.xlsx', $token) }}" class="btn btn-sm btn-outline-success ms-2">XLSX</a>
            <a href="{{ route('site-audit.public.share.docx', $token) }}" class="btn btn-sm btn-outline-secondary">DOCX</a>
            <button type="button" class="btn btn-sm btn-outline-secondary cabinet-sa-print-btn" onclick="window.print()">Печать</button>
            <span class="small text-secondary ms-auto">
                {{ optional($crawl->finished_at)->format('d.m.Y H:i') }}
                · {{ $crawl->pages_total }} URL
            </span>
        </div>
        <div class="card-body cabinet-sa-page p-3">
            <ul class="nav nav-tabs mb-3" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active" data-bs-toggle="tab" href="#sa-pub-all" role="tab">Сводка</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="tab" href="#sa-pub-tech" role="tab">Тех. аудит</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="tab" href="#sa-pub-seo" role="tab">SEO-аудит</a>
                </li>
            </ul>

            <div class="tab-content">
                <div class="tab-pane fade show active" id="sa-pub-all" role="tabpanel">
                    <div class="cabinet-sa-buckets mb-4">
                        @foreach($bucketLabels as $key => $label)
                            <div class="cabinet-sa-bucket cabinet-sa-bucket--{{ $key }}">
                                <div class="cabinet-sa-bucket__label">{{ $label }}</div>
                                <div class="cabinet-sa-bucket__value">{{ (int) (($bucketsAll ?? $buckets)[$key] ?? 0) }}</div>
                            </div>
                        @endforeach
                    </div>
                    <div class="cabinet-sa-layout">
                        <aside class="cabinet-sa-tree">
                            <div class="px-3 py-2 border-bottom fw-semibold small">Все замечания</div>
                            @foreach($bucketLabels as $sev => $label)
                                <div class="cabinet-sa-tree__group-title">{{ $label }}</div>
                                @foreach(($treeAll[$sev] ?? []) as $item)
                                    <a class="cabinet-sa-tree__item {{ $item['count'] ? '' : 'is-empty' }}"
                                       href="{{ route('site-audit.public.share.report', [$token, $item['code']]) }}">
                                        <span>
                                            {{ $item['title'] }}
                                            <span class="cabinet-sa-group-tag cabinet-sa-group-tag--{{ $item['group'] ?? 'tech' }}">{{ ($item['group'] ?? '') === 'seo' ? 'SEO' : 'тех' }}</span>
                                        </span>
                                        <span class="cabinet-sa-badge cabinet-sa-badge--{{ $item['count'] > 0 ? $sev : 'zero' }}">{{ $item['count'] }}</span>
                                    </a>
                                @endforeach
                            @endforeach
                        </aside>
                        <section>
                            <h5 class="mb-3">Сводка аудита</h5>
                            @include('pages.partials.site-audit-hot-table', [
                                'counts' => $counts,
                                'findingsCatalog' => $findingsCatalog,
                                'crawl' => $crawl,
                                'group' => null,
                                'isPublic' => true,
                                'token' => $token,
                            ])
                        </section>
                    </div>
                </div>

                <div class="tab-pane fade" id="sa-pub-tech" role="tabpanel">
                    <div class="cabinet-sa-buckets mb-4">
                        @foreach($bucketLabels as $key => $label)
                            <div class="cabinet-sa-bucket cabinet-sa-bucket--{{ $key }}">
                                <div class="cabinet-sa-bucket__label">{{ $label }}</div>
                                <div class="cabinet-sa-bucket__value">{{ (int) ($buckets[$key] ?? 0) }}</div>
                            </div>
                        @endforeach
                    </div>
                    <div class="cabinet-sa-layout">
                        <aside class="cabinet-sa-tree">
                            <div class="px-3 py-2 border-bottom fw-semibold small">Тех. аудит</div>
                            @foreach($bucketLabels as $sev => $label)
                                <div class="cabinet-sa-tree__group-title">{{ $label }}</div>
                                @foreach(($tree[$sev] ?? []) as $item)
                                    <a class="cabinet-sa-tree__item {{ $item['count'] ? '' : 'is-empty' }}"
                                       href="{{ route('site-audit.public.share.report', [$token, $item['code']]) }}">
                                        <span>{{ $item['title'] }}</span>
                                        <span class="cabinet-sa-badge cabinet-sa-badge--{{ $item['count'] > 0 ? $sev : 'zero' }}">{{ $item['count'] }}</span>
                                    </a>
                                @endforeach
                            @endforeach
                        </aside>
                        <section>
                            <h5 class="mb-3">Сводный тех. аудит</h5>
                            @include('pages.partials.site-audit-hot-table', [
                                'counts' => $counts,
                                'findingsCatalog' => $findingsCatalog,
                                'crawl' => $crawl,
                                'group' => 'tech',
                                'isPublic' => true,
                                'token' => $token,
                            ])
                        </section>
                    </div>
                </div>

                <div class="tab-pane fade" id="sa-pub-seo" role="tabpanel">
                    <div class="cabinet-sa-buckets mb-4">
                        @foreach($bucketLabels as $key => $label)
                            <div class="cabinet-sa-bucket cabinet-sa-bucket--{{ $key }}">
                                <div class="cabinet-sa-bucket__label">{{ $label }}</div>
                                <div class="cabinet-sa-bucket__value">{{ (int) (($bucketsSeo ?? [])[$key] ?? 0) }}</div>
                            </div>
                        @endforeach
                    </div>
                    <div class="cabinet-sa-layout">
                        <aside class="cabinet-sa-tree">
                            <div class="px-3 py-2 border-bottom fw-semibold small">SEO-аудит</div>
                            @foreach($bucketLabels as $sev => $label)
                                <div class="cabinet-sa-tree__group-title">{{ $label }}</div>
                                @foreach(($treeSeo[$sev] ?? []) as $item)
                                    <a class="cabinet-sa-tree__item {{ $item['count'] ? '' : 'is-empty' }}"
                                       href="{{ route('site-audit.public.share.report', [$token, $item['code']]) }}">
                                        <span>{{ $item['title'] }}</span>
                                        <span class="cabinet-sa-badge cabinet-sa-badge--{{ $item['count'] > 0 ? $sev : 'zero' }}">{{ $item['count'] }}</span>
                                    </a>
                                @endforeach
                            @endforeach
                        </aside>
                        <section>
                            <h5 class="mb-3">Сводный SEO-аудит</h5>
                            @include('pages.partials.site-audit-hot-table', [
                                'counts' => $counts,
                                'findingsCatalog' => $findingsCatalog,
                                'crawl' => $crawl,
                                'group' => 'seo',
                                'isPublic' => true,
                                'token' => $token,
                            ])
                        </section>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
