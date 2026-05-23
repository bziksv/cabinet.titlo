<?php

namespace App\Services;

/**
 * PDF-отчёт «Анализ текста» — эталонная реализация layout (v6.9s).
 *
 * Новые модули: копировать renderBinary() / pagebreak / header-footer как есть;
 * менять только buildViewData() и pdf-body.blade.php.
 *
 * Документация: datagon.ru/docs/cabinet-pdf-report-template.md
 */
use App\Support\TextAnalyzerPdfBranding;
use App\TextAnalyzer;
use Mpdf\Mpdf;
use Mpdf\HTMLParserMode;
use Mpdf\Output\Destination;
use Symfony\Component\HttpFoundation\Response;

class TextAnalyzerPdfService
{
    /**
     * @return array<string, mixed>
     */
    public function buildViewData(array $response, array $request, array $meta): array
    {
        $hasCompare = !empty($response['comparison']);
        $graph = $response['graph'] ?? [];
        $compGraph = $hasCompare ? ($response['competitor']['graph'] ?? []) : [];
        $clouds = $response['clouds'] ?? ['text' => [], 'links' => [], 'both' => []];

        $zipfChartPath = TextAnalyzerPdfBranding::zipfChartImagePath(
            $graph,
            $hasCompare ? $compGraph : null
        );

        return array_merge([
            'response' => $response,
            'request' => $request,
            'meta' => $meta,
            'hasCompare' => $hasCompare,
            'general' => $response['general'] ?? [],
            'competitorGeneral' => $hasCompare ? ($response['competitor']['general'] ?? []) : [],
            'competitorUrl' => $hasCompare
                ? ($response['comparison']['competitor_url'] ?? ($response['competitor']['url'] ?? ''))
                : '',
            'competitorHost' => $hasCompare
                ? ($response['comparison']['competitor_host'] ?? TextAnalyzer::urlHost(
                    $response['comparison']['competitor_url'] ?? ($response['competitor']['url'] ?? '')
                ))
                : '',
            'competitorLabel' => $hasCompare
                ? TextAnalyzer::competitorLabel(
                    $response['comparison']['competitor_url'] ?? ($response['competitor']['url'] ?? '')
                )
                : '',
            'compareWords' => $hasCompare ? ($response['comparison']['totalWords'] ?? []) : [],
            'comparePhrases' => $hasCompare ? ($response['comparison']['phrases'] ?? []) : [],
            'words' => array_slice($response['totalWords'] ?? [], 0, 60),
            'phrases' => array_slice($response['phrases'] ?? [], 0, 40),
            'zipfRows' => TextAnalyzerPdfBranding::zipfTableRows($graph),
            'zipfRowsCompetitor' => $hasCompare
                ? TextAnalyzerPdfBranding::zipfTableRows($compGraph)
                : [],
            'zipfChartPath' => $zipfChartPath,
            'cloudText' => TextAnalyzerPdfBranding::cloudRowsForPdf($clouds['text'] ?? []),
            'cloudLinks' => TextAnalyzerPdfBranding::cloudRowsForPdf($clouds['links'] ?? []),
            'cloudBoth' => TextAnalyzerPdfBranding::cloudRowsForPdf($clouds['both'] ?? []),
            'cloudTextCompetitor' => $hasCompare
                ? TextAnalyzerPdfBranding::cloudRowsForPdf($response['competitor']['clouds']['text'] ?? [])
                : [],
            'cloudLinksCompetitor' => $hasCompare
                ? TextAnalyzerPdfBranding::cloudRowsForPdf($response['competitor']['clouds']['links'] ?? [])
                : [],
            'cloudBothCompetitor' => $hasCompare
                ? TextAnalyzerPdfBranding::cloudRowsForPdf($response['competitor']['clouds']['both'] ?? [])
                : [],
            'yes' => __('Yes'),
            'no' => __('No'),
        ], TextAnalyzerPdfBranding::viewData());
    }

    public function renderBinary(array $response, array $request, array $meta): string
    {
        $data = $this->buildViewData($response, $request, $meta);
        $bodyHtml = view('text-analyse.export.pdf-body', $data)->render();
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

        $mpdf->SetTitle((string) __('Text analyzer report'));
        $mpdf->SetAuthor(TextAnalyzerPdfBranding::BRAND_NAME);
        $mpdf->SetCreator(TextAnalyzerPdfBranding::BRAND_SITE);

        $coverImage = TextAnalyzerPdfBranding::coverPageImagePath(
            $meta,
            !empty($data['hasCompare']),
            (string) ($data['competitorLabel'] ?? $data['competitorHost'] ?? $data['competitorUrl'] ?? '')
        );
        $coverSrc = htmlspecialchars($coverImage, ENT_QUOTES, 'UTF-8');

        // Стр. 1 — GD-PNG на весь лист. Шапку/футер включаем только после pagebreak.
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

        $header = '<table width="100%" cellpadding="0" cellspacing="0" style="font-family:dejavusans;font-size:7.5pt;color:#64748b;border-bottom:0.5pt solid #e2e8f0;padding-bottom:5pt;margin:0;">
            <tr>
                <td width="8%" style="vertical-align:middle;"><img src="' . $icon . '" height="16" alt="" /></td>
                <td width="64%" style="vertical-align:middle;">' . $source . '</td>
                <td width="28%" align="right" style="vertical-align:middle;color:#0f172a;font-weight:bold;">' . $brand . ' · v' . $version . '</td>
            </tr>
        </table>';

        $footer = '<table width="100%" cellpadding="0" cellspacing="0" style="font-family:dejavusans;font-size:7.5pt;color:#94a3b8;border-top:0.5pt solid #e2e8f0;padding-top:5pt;margin:0;">
            <tr>
                <td width="55%">' . TextAnalyzerPdfBranding::BRAND_SITE . ' · ' . __('Text analyzer report') . '</td>
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

    public function downloadResponse(array $response, array $request, array $meta, string $fileName): Response
    {
        $binary = $this->renderBinary($response, $request, $meta);

        return new Response($binary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
            'Content-Length' => (string) strlen($binary),
        ]);
    }
}
