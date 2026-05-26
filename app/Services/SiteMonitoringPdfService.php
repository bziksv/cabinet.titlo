<?php

namespace App\Services;

use App\Support\TextAnalyzerPdfBranding;
use Mpdf\Mpdf;
use Mpdf\HTMLParserMode;
use Mpdf\Output\Destination;
use Symfony\Component\HttpFoundation\Response;

/**
 * PDF-отчёт «Мониторинг сайтов» — layout по эталону Text Analyzer (cabinet-pdf-report-template.md).
 */
class SiteMonitoringPdfService
{
    /**
     * @param  array<string, mixed>  $report
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    public function buildViewData(array $report, array $meta): array
    {
        return array_merge([
            'report' => $report,
            'meta' => $meta,
            'project' => $report['project'] ?? [],
            'summary' => $report['summary'] ?? [],
            'incidents' => $report['incidents'] ?? [],
            'timeline' => $report['timeline'] ?? [],
        ], TextAnalyzerPdfBranding::viewData());
    }

    /**
     * @param  array<string, mixed>  $report
     * @param  array<string, mixed>  $meta
     */
    public function renderBinary(array $report, array $meta): string
    {
        $data = $this->buildViewData($report, $meta);
        $bodyHtml = view('site-monitoring.export.pdf-body', $data)->render();
        $margin = 14;

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'default_font' => 'dejavusans',
            'margin_left' => 0,
            'margin_right' => 0,
            'margin_top' => 0,
            'margin_bottom' => 0,
            'margin_header' => 0,
            'margin_footer' => 0,
            'tempDir' => storage_path('app/mpdf-tmp'),
        ]);

        if (!is_dir(storage_path('app/mpdf-tmp'))) {
            @mkdir(storage_path('app/mpdf-tmp'), 0755, true);
        }

        $mpdf->SetTitle((string) __('Site monitoring report'));
        $mpdf->SetAuthor(TextAnalyzerPdfBranding::BRAND_NAME);
        $mpdf->SetCreator(TextAnalyzerPdfBranding::BRAND_SITE);

        $coverMeta = array_merge($meta, [
            'cover_rev' => 'site-monitoring-1',
            'cover_kicker' => (string) __('Site monitoring report'),
            'cover_title' => (string) __('Monitored domains'),
            'cover_lead' => (string) __('Site monitoring report cover lead'),
            'cover_footer' => (string) __('Site monitoring report'),
        ]);
        $coverImage = TextAnalyzerPdfBranding::coverPageImagePath($coverMeta, false, '');
        $coverSrc = htmlspecialchars($coverImage, ENT_QUOTES, 'UTF-8');

        $mpdf->SetAutoPageBreak(false);
        $mpdf->WriteHTML(
            '<sethtmlpageheader name="" value="off" show-this-page="1" />'
            . '<sethtmlpagefooter name="" value="off" show-this-page="1" />'
            . '<style>body{margin:0;padding:0;background:#0f172a;}</style>'
            . '<img src="' . $coverSrc . '" width="210mm" height="297mm" style="display:block;margin:0;padding:0;border:0;" alt="" />'
        );

        $icon = TextAnalyzerPdfBranding::logoIconPath();
        $source = htmlspecialchars((string) ($meta['source_label'] ?? ''), ENT_QUOTES, 'UTF-8');
        $brand = htmlspecialchars(TextAnalyzerPdfBranding::BRAND_NAME, ENT_QUOTES, 'UTF-8');
        $version = htmlspecialchars((string) ($meta['version'] ?? ''), ENT_QUOTES, 'UTF-8');
        $reportTitle = htmlspecialchars((string) __('Site monitoring report'), ENT_QUOTES, 'UTF-8');

        $header = '<table width="100%" cellpadding="0" cellspacing="0" style="font-family:dejavusans;font-size:7.5pt;color:#64748b;border-bottom:0.5pt solid #e2e8f0;padding-bottom:5pt;margin:0;">
            <tr>
                <td width="8%" style="vertical-align:middle;"><img src="' . $icon . '" height="16" alt="" /></td>
                <td width="64%" style="vertical-align:middle;">' . $source . '</td>
                <td width="28%" align="right" style="vertical-align:middle;color:#0f172a;font-weight:bold;">' . $brand . ' · v' . $version . '</td>
            </tr>
        </table>';

        $footer = '<table width="100%" cellpadding="0" cellspacing="0" style="font-family:dejavusans;font-size:7.5pt;color:#94a3b8;border-top:0.5pt solid #e2e8f0;padding-top:5pt;margin:0;">
            <tr>
                <td width="55%">' . TextAnalyzerPdfBranding::BRAND_SITE . ' · ' . $reportTitle . '</td>
                <td width="45%" align="right">{PAGENO} / {nbpg}</td>
            </tr>
        </table>';

        $mpdf->WriteHTML(
            '<pagebreak margin-left="' . $margin . 'mm" margin-right="' . $margin . 'mm"'
            . ' margin-top="22mm" margin-bottom="18mm" margin-header="8mm" margin-footer="10mm" />'
        );
        $mpdf->SetHTMLHeader($header);
        $mpdf->SetHTMLFooter($footer);
        $mpdf->SetMargins($margin, $margin, 22);
        $mpdf->SetAutoPageBreak(true, 18);
        $mpdf->WriteHTML(
            '<style>body{background-color:#ffffff;margin:0;padding:0;}</style>',
            HTMLParserMode::HEADER_CSS
        );
        $mpdf->WriteHTML($bodyHtml);

        return $mpdf->Output('', Destination::STRING_RETURN);
    }

    /**
     * @param  array<string, mixed>  $report
     * @param  array<string, mixed>  $meta
     */
    public function downloadResponse(array $report, array $meta, string $fileName): Response
    {
        $binary = $this->renderBinary($report, $meta);

        return new Response($binary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
            'Content-Length' => (string) strlen($binary),
        ]);
    }
}
