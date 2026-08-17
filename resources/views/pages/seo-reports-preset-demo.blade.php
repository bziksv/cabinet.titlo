<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Demo report') }} · {{ $presetTitle }} · Titlo</title>
    <link rel="stylesheet" href="{{ asset('css/cabinet-seo-reports.css') }}?v={{ @filemtime(public_path('css/cabinet-seo-reports.css')) ?: time() }}">
    <style>
        .cabinet-sr-demo-banner {
            position: sticky;
            top: 0;
            z-index: 30;
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            align-items: center;
            justify-content: space-between;
            padding: 0.8rem 1.15rem;
            background: #0b1220;
            color: #f8fafc;
            font-size: 0.875rem;
            box-shadow: 0 8px 24px rgba(2, 6, 23, 0.28);
        }
        .cabinet-sr-demo-banner a {
            color: #93c5fd;
        }
        .cabinet-sr-demo-banner__actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.45rem;
            align-items: center;
        }
        .cabinet-sr-demo-banner__actions a {
            display: inline-block;
            padding: 0.35rem 0.75rem;
            border-radius: 999px;
            text-decoration: none;
            border: 1px solid rgba(148, 163, 184, 0.4);
            color: #e2e8f0;
            font-weight: 600;
            font-size: 0.8rem;
        }
        .cabinet-sr-demo-banner__actions a.is-active {
            background: #1d4ed8;
            border-color: #1d4ed8;
            color: #fff;
        }
        .cabinet-sr-demo-banner__note {
            opacity: 0.78;
        }
        body.cabinet-sr-public {
            padding-top: 0;
        }
        /* Demo banner height is accounted for in TOC pin JS (stickLine). */
    </style>
</head>
<body class="cabinet-sr-public">
    @php $templateId = $templateId ?? null; @endphp
    <div class="cabinet-sr-demo-banner">
        <div>
            <strong>{{ __('Demo report') }}:</strong> {{ $presetTitle }}
            <span class="cabinet-sr-demo-banner__note">
                · @if($templateId)
                    {{ __('Template demo preview note') }}
                @else
                    {{ __('Filled HTML preview, not live client data') }}
                @endif
            </span>
        </div>
        <div class="cabinet-sr-demo-banner__actions">
            @if($templateId)
                <a href="{{ route('pages.seo-reports.templates.edit', ['id' => $templateId]) }}"
                   class="is-active"
                   target="_self">{{ __('Back to template') }}</a>
            @else
                @foreach(['seo_only' => 'Только SEO', 'seo_ads' => 'SEO + реклама', 'complex' => 'Комплексный'] as $key => $label)
                    <a href="{{ route('pages.seo-reports.preset-demo', ['preset' => $key]) }}"
                       class="@if($preset === $key) is-active @endif"
                       target="_self">{{ $label }}</a>
                @endforeach
            @endif
            <a href="{{ route('pages.seo-reports') }}">{{ __('Back to SEO Reports') }}</a>
        </div>
    </div>
    <div class="cabinet-sr-public__wrap">
        @include('pages.partials.seo-reports-report-body', [
            'project' => $project,
            'report' => $report,
            'snapshot' => $snapshot,
            'sections' => $sections,
            'isPublicView' => true,
        ])
        <p class="cabinet-sr-public__footer">
            {{ __('Powered by Titlo') }} · {{ __('Demo data') }}
        </p>
    </div>
    <script src="{{ asset('plugins/chart.js/3.9.1/chart.js') }}"></script>
    <script src="{{ asset('js/cabinet-seo-reports-charts.js') }}?v={{ @filemtime(public_path('js/cabinet-seo-reports-charts.js')) ?: time() }}"></script>
    <script src="{{ asset('js/cabinet-seo-reports-toc.js') }}?v={{ @filemtime(public_path('js/cabinet-seo-reports-toc.js')) ?: time() }}"></script>
</body>
</html>
