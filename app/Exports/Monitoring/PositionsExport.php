<?php

namespace App\Exports\Monitoring;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\WithDefaultStyles;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use Maatwebsite\Excel\Events\BeforeExport;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Shared\Drawing as XlsDrawing;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Style;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PositionsExport implements FromView, WithDefaultStyles, WithEvents, WithStyles, WithTitle
{
    protected $data;
    private $green = "#99e4b9";
    private $yellow = "#fbe1df";

    /**
     * Ширины по ключу колонки (см), не по букве Excel.
     * № всегда первая колонка (_num). Остальные (target, dynamics, даты) — auto-size.
     */
    private const WIDTH_CM_BY_KEY = [
        '_num' => 1.5,
        'query' => 12.0,
        'url' => 3.7,
        'url_links' => 10.0,
        'target_url' => 10.0,
        'group' => 10.0,
    ];

    /** @var list<string> ключи data-колонок в порядке листа (без №) */
    private $columnKeys = [];

    public function __construct($data)
    {
        $this->data = $data;
        $columns = $data['columns'] ?? [];
        if ($columns instanceof \Illuminate\Support\Collection) {
            $this->columnKeys = $columns->keys()->map(static function ($k) {
                return (string) $k;
            })->values()->all();
        } elseif (is_array($columns)) {
            $this->columnKeys = array_map('strval', array_keys($columns));
        }
        $this->dataFormat();
    }

    public function view(): View
    {
        $data = $this->data;

        return view('monitoring.export.index', compact('data'));
    }

    public function defaultStyles(Style $defaultStyle)
    {
        return [
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
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
        $columnKeys = $this->columnKeys;

        return [
            BeforeExport::class => function (BeforeExport $event) {
                $event->writer->getProperties();
            },

            AfterSheet::class => function (AfterSheet $event) use ($columnKeys) {
                $sheet = $event->sheet->getDelegate();
                $highestColumn = $sheet->getHighestColumn();
                $highestRow = (int) $sheet->getHighestRow();
                if ($highestRow < 1) {
                    return;
                }

                $font = $sheet->getParent()->getDefaultStyle()->getFont();
                $highestIdx = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn);

                // Порядок на листе: A = №, дальше ключи из экспорта.
                $keyByLetter = ['_num'];
                foreach ($columnKeys as $key) {
                    $keyByLetter[] = $key;
                }

                // Сначала auto-size для колонок без фикс. ширины.
                $fixedLetters = [];
                foreach ($keyByLetter as $idx => $key) {
                    $letter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($idx + 1);
                    if ($idx + 1 > $highestIdx) {
                        break;
                    }
                    if (isset(self::WIDTH_CM_BY_KEY[$key])) {
                        $fixedLetters[$letter] = self::WIDTH_CM_BY_KEY[$key];
                        $sheet->getColumnDimension($letter)->setAutoSize(false);
                    } else {
                        $sheet->getColumnDimension($letter)->setAutoSize(true);
                    }
                }
                $sheet->calculateColumnWidths();

                // Фикс. ширины по ключу (после auto-size, чтобы не перебило).
                foreach ($fixedLetters as $letter => $cm) {
                    $px = (int) round($cm * 37.795275591);
                    $width = max(3.0, (float) XlsDrawing::pixelsToCellDimension($px, $font));
                    $sheet->getColumnDimension($letter)->setAutoSize(false);
                    $sheet->getColumnDimension($letter)->setWidth($width);
                }

                $linksCol = null;
                foreach ($keyByLetter as $idx => $key) {
                    if ($key === 'url_links') {
                        $linksCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($idx + 1);
                        break;
                    }
                }
                if ($linksCol === null) {
                    $linksCol = $this->findUrlLinksColumn($sheet, $highestColumn);
                }
                if ($linksCol === null) {
                    return;
                }

                $sheet->getStyle($linksCol . '1:' . $linksCol . $highestRow)
                    ->getAlignment()
                    ->setWrapText(true)
                    ->setVertical(Alignment::VERTICAL_TOP)
                    ->setHorizontal(Alignment::HORIZONTAL_LEFT);

                for ($row = 2; $row <= $highestRow; $row++) {
                    $cell = $sheet->getCell($linksCol . $row);
                    $raw = (string) $cell->getValue();
                    if ($raw === '') {
                        continue;
                    }

                    $normalized = $this->normalizeUrlLinksValue($raw);
                    $cell->setValueExplicit($normalized, DataType::TYPE_STRING);

                    $lines = max(1, substr_count($normalized, "\n") + 1);
                    $sheet->getRowDimension($row)->setRowHeight(max(15.0, $lines * 15.0));
                }
            },
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_LEFT,
                    'indent' => 1,
                    'wrapText' => true,
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
                    'wrapText' => true,
                ],
            ],
        ];
    }

    public function title(): string
    {
        return 'RedBox title';
    }

    /**
     * @param \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet
     */
    private function findUrlLinksColumn($sheet, string $highestColumn): ?string
    {
        $col = 'A';
        while (true) {
            $val = trim((string) $sheet->getCell($col . '1')->getValue());
            $valNorm = mb_strtolower($val);
            if (
                $val !== ''
                && (
                    mb_strpos($valNorm, 'ссылки') !== false
                    || mb_strpos($valNorm, 'url_links') !== false
                    || (mb_strpos($valNorm, 'url') !== false && mb_strpos($valNorm, 'ссыл') !== false)
                )
            ) {
                return $col;
            }
            if ($col === $highestColumn) {
                break;
            }
            $col++;
        }

        return null;
    }

    private function normalizeUrlLinksValue(string $raw): string
    {
        $normalized = preg_replace('/<br\s*\/?>/i', "\n", $raw) ?? $raw;
        $normalized = html_entity_decode(strip_tags($normalized), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $normalized = str_replace(["\r\n", "\r"], "\n", $normalized);
        // HTML/Excel часто схлопывают переносы в пробелы между URL.
        if (strpos($normalized, "\n") === false) {
            $normalized = preg_replace('/(?<=\S)\s+(?=https?:\/\/)/', "\n", $normalized) ?? $normalized;
        }
        // Убрать пустые строки и пробелы по краям каждой ссылки.
        $parts = preg_split("/\n+/", $normalized) ?: [];
        $parts = array_values(array_filter(array_map('trim', $parts), static function ($u) {
            return $u !== '';
        }));

        return implode("\n", $parts);
    }

    private function dataFormat()
    {
        $data = $this->data['data'];
        foreach ($data as $ek => $el) {
            // URL-ссылки нормализуем всегда (даже без колонки target).
            if (isset($el['url_links'])) {
                $this->data['data'][$ek]['url_links'] = $this->normalizeUrlLinksValue(
                    is_string($el['url_links']) ? $el['url_links'] : (string) $el['url_links']
                );
            }

            if (!isset($el['target'])) {
                continue;
            }

            $target = (int) trim(strip_tags((string) $el['target']));

            foreach ($el as $fk => $field) {
                if ($fk === 'url_links') {
                    continue;
                }

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
