@php
    $source = $meta['source_label'] ?? '';
    if (mb_strlen($source) > 90) {
        $source = mb_substr($source, 0, 87) . '…';
    }
    $generated = $meta['generated_at'] ?? '';
    $version = $meta['version'] ?? '';
@endphp
<style>
    .cover-page {
        width: 210mm;
        height: 297mm;
        font-family: dejavusans, sans-serif;
        color: #f8fafc;
        background-image: url('{{ $coverBackgroundPath }}');
        background-image-resize: 6;
    }
    .cover-top { padding: 20mm 18mm 0 18mm; vertical-align: top; }
    .cover-bottom { padding: 0 18mm 14mm 18mm; vertical-align: bottom; }
    .cover-kicker {
        font-size: 8.5pt;
        text-transform: uppercase;
        letter-spacing: 1.4pt;
        color: #8fd3ff;
        margin-bottom: 7pt;
    }
    .cover-title {
        font-size: 24pt;
        font-weight: bold;
        color: #f8fafc;
        line-height: 1.12;
    }
    .cover-lead {
        font-size: 9.5pt;
        color: #cbd5e1;
        line-height: 1.45;
        margin-top: 8pt;
    }
    .cover-tagline {
        font-size: 9.5pt;
        color: #94a3b8;
        margin-top: 6pt;
        line-height: 1.35;
    }
    .cover-meta-label {
        font-size: 7pt;
        text-transform: uppercase;
        letter-spacing: 0.8pt;
        color: #94a3b8;
    }
    .cover-meta-value {
        font-size: 10.5pt;
        font-weight: bold;
        color: #f1f5f9;
        margin-top: 3pt;
    }
    .cover-meta-value-sm {
        font-size: 9pt;
        color: #e2e8f0;
        margin-top: 3pt;
        line-height: 1.35;
    }
    .cover-footer td {
        font-size: 7.5pt;
        color: #64748b;
        padding-top: 7pt;
        border-top: 0.5pt solid #475569;
    }
</style>
<table class="cover-page" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">
    <tr>
        <td class="cover-top">
            <img src="{{ $coverLogoPath }}" height="40" alt="{{ $brandName }}" style="display:block;border:0;" />
            <div class="cover-tagline">{{ $brandTagline }}</div>
            <div style="margin-top:11mm;">
                <div class="cover-kicker">{{ __('Text analyzer report') }}</div>
                <div class="cover-title">{{ __('Text Analyse') }}</div>
                <div class="cover-lead">{{ __('Word statistics, Zipf distribution, phrase analysis and word clouds for page text or URL.') }}</div>
            </div>
            <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;margin-top:9mm;">
                <tr>
                    <td width="34%" style="padding:11pt 12pt;background-color:#1e293b;border-left:3pt solid #2f5de0;">
                        <div class="cover-meta-label">{{ __('Generated at') }}</div>
                        <div class="cover-meta-value">{{ $generated }}</div>
                    </td>
                    <td width="3%"></td>
                    <td width="63%" style="padding:11pt 12pt;background-color:#1e293b;border-left:3pt solid #64748b;">
                        <div class="cover-meta-label">{{ __('Source') }}</div>
                        <div class="cover-meta-value-sm">{{ $source }}</div>
                    </td>
                </tr>
            </table>
            @if($hasCompare)
            <div style="margin-top:7mm;padding:9pt 12pt;background-color:#422006;border-left:3pt solid #d97706;font-size:8.5pt;color:#fde68a;line-height:1.4;">
                <strong style="color:#fcd34d;">{{ __('Comparison mode active') }}</strong><br/>
                {{ $competitorLabel ?? $competitorHost ?? $competitorUrl }}
            </div>
            @endif
        </td>
    </tr>
    <tr>
        <td class="cover-bottom">
            <table class="cover-footer" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">
                <tr>
                    <td>{{ $brandSiteHost }}</td>
                    <td align="center" style="color:#475569;">{{ __('Text analyzer report') }} · v{{ $version }}</td>
                    <td align="right">PDF</td>
                </tr>
            </table>
        </td>
    </tr>
</table>
