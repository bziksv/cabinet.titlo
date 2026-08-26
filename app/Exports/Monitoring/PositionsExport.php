<?php

namespace App\Exports\Monitoring;

use Illuminate\Contracts\View\View;
use Iterator;
use Maatwebsite\Excel\Concerns\FromIterator;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithDefaultStyles;
use Maatwebsite\Excel\Concerns\WithDrawings;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Style;
use Maatwebsite\Excel\Events\BeforeExport;
use PhpOffice\PhpSpreadsheet\Worksheet\BaseDrawing;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;

class PositionsExport implements FromView, WithDefaultStyles, WithEvents, WithStyles, WithTitle, ShouldAutoSize
{
    protected $data;
    private $green = "#99e4b9";
    private $yellow = "#fbe1df";

    public function __construct($data)
    {
        $this->data = $data;
        $this->dataFormat();
    }

    public function view(): View
    {
        $data = $this->data;

        return view('monitoring.export.index', compact('data'));
    }

    public function defaultStyles(Style $defaultStyle)
    {
        // Or return the styles array
        return [
            'fill' => [
                'fillType'   => Fill::FILL_SOLID,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['argb' => '000000'],
                ],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
            ],
        ];
    }

    /**
     * @return array
     */
    public function registerEvents(): array
    {
        return [
            BeforeExport::class => function(BeforeExport $event) {
                $properties = $event->writer->getProperties();
                //$properties->setTitle('RedBox');
            },

            AfterSheet::class => function(AfterSheet $event) {
                //$event->sheet->getDelegate()->getPageSetup()->setPaperSize(PageSetup::PAPERSIZE_A4);
            },
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            // Style the first row as bold text.
            1 => [
                'font' => ['bold' => true],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_LEFT,
                    'indent' => 1,
                ],
            ],
            'A' => [
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_LEFT,
                    'indent' => 1,
                ],
            ],
            'B' => [
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_LEFT,
                    'indent' => 1,
                ],
            ],
        ];
    }

    public function title(): string
    {
        return 'RedBox title';
    }

    private function dataFormat()
    {
        $data = $this->data['data'];
        foreach ($data as $ek => $el) {
            if (!isset($el['target'])) {
                continue;
            }

            $target = (int) trim(strip_tags((string) $el['target']));

            foreach ($el as $fk => $field) {
                if (is_array($field) && array_key_exists('p', $field)) {
                    $this->data['data'][$ek][$fk] = $this->exportCellFromPositionPayload($field, $target, $el, (string) $fk);
                    continue;
                }

                if (is_string($field) && preg_match('/data-position/', $field)) {
                    $col = $this->formatPosition($field);
                    $col['color'] = null;
                    $position = (int) ($col[0] ?? 101);

                    if ($target >= $position) {
                        $col['color'] = $this->green;
                    } else {
                        $ck = 'col_' . (filter_var($fk, FILTER_SANITIZE_NUMBER_INT) + 1);
                        if (isset($el[$ck]) && is_string($el[$ck])) {
                            $p = $this->formatPosition($el[$ck]);
                            $prevPosition = (int) ($p[0] ?? 101);
                            if ($target >= $prevPosition) {
                                $col['color'] = $this->yellow;
                            }
                        } elseif (isset($el[$ck]) && is_array($el[$ck]) && array_key_exists('p', $el[$ck])) {
                            $prevPosition = (int) $el[$ck]['p'];
                            if ($target >= $prevPosition) {
                                $col['color'] = $this->yellow;
                            }
                        }
                    }
                    $this->data['data'][$ek][$fk] = $col;
                    continue;
                }

                $this->data['data'][$ek][$fk] = $this->plainCellText($fk, $field);
            }
        }
    }

    /**
     * /table отдаёт positionCellPayload (массив), экспорт ждёт [position, diff?, color].
     *
     * @param array{p: int, d?: int, t?: string} $field
     */
    private function exportCellFromPositionPayload(array $field, int $target, $row, string $fk): array
    {
        $col = [(string) $field['p']];
        if (!empty($field['d'])) {
            $diff = (int) $field['d'];
            $col[] = ($diff > 0 ? '+' : '') . $diff;
        }
        $col['color'] = null;
        $position = (int) $field['p'];

        if ($target >= $position) {
            $col['color'] = $this->green;
        } else {
            $ck = 'col_' . (filter_var($fk, FILTER_SANITIZE_NUMBER_INT) + 1);
            if (isset($row[$ck])) {
                $prevPosition = null;
                if (is_array($row[$ck]) && array_key_exists('p', $row[$ck])) {
                    $prevPosition = (int) $row[$ck]['p'];
                } elseif (is_string($row[$ck])) {
                    $p = $this->formatPosition($row[$ck]);
                    $prevPosition = (int) ($p[0] ?? 101);
                }
                if ($prevPosition !== null && $target >= $prevPosition) {
                    $col['color'] = $this->yellow;
                }
            }
        }

        return $col;
    }

    /**
     * HTML ячейки «Запрос» содержит иконку целевого URL; strip_tags иначе оставляет «Целевой URL».
     */
    private function plainCellText($key, $field): string
    {
        if (!is_string($field)) {
            return is_scalar($field) ? (string) $field : '';
        }

        if ($key === 'query' && preg_match('/class="query-string"[^>]*>\s*(.*?)\s*</su', $field, $m)) {
            return html_entity_decode(trim($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        $text = trim(strip_tags($field));
        if ($key === 'query') {
            $text = preg_replace('/\s*(?:Целевой URL|Target URL)\s*$/u', '', $text) ?? $text;
        }

        return trim($text);
    }

    private function formatPosition(string $field): array
    {
        $parts = explode(' ', trim(strip_tags($field)));

        return array_values(array_filter($parts, static function ($part) {
            return $part !== '';
        }));
    }
}
