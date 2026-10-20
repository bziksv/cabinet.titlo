<?php

namespace App\Services\SiteAudit;

use Generator;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

/**
 * Потоковая запись XLSX (OpenXML) без PhpSpreadsheet — для больших выгрузок Site Audit.
 * Строки пишутся в temp XML с inlineStr, затем упаковываются в zip.
 */
class SiteAuditStreamXlsx
{
    /**
     * @param array<int, array{title:string, headings:list<string>, rows:Generator|iterable}> $sheets
     */
    public static function download(string $filename, array $sheets): StreamedResponse
    {
        return response()->streamDownload(function () use ($sheets) {
            $tmpDir = storage_path('app/tmp/sa-xlsx-' . uniqid('', true));
            if (! is_dir($tmpDir) && ! @mkdir($tmpDir, 0775, true) && ! is_dir($tmpDir)) {
                throw new \RuntimeException('Cannot create temp dir for XLSX export');
            }

            $zipPath = $tmpDir . '/out.xlsx';
            $sheetFiles = [];
            $sheetTitles = [];

            try {
                $i = 0;
                foreach ($sheets as $sheet) {
                    $i++;
                    $title = self::safeSheetTitle((string) ($sheet['title'] ?? ('Sheet' . $i)));
                    $headings = isset($sheet['headings']) && is_array($sheet['headings'])
                        ? $sheet['headings']
                        : [];
                    $rows = $sheet['rows'] ?? [];
                    $path = $tmpDir . '/sheet' . $i . '.xml';
                    self::writeSheetXml($path, $headings, $rows);
                    $sheetFiles[] = $path;
                    $sheetTitles[] = $title;
                }

                self::packZip($zipPath, $sheetFiles, $sheetTitles);

                $fh = fopen($zipPath, 'rb');
                if ($fh === false) {
                    throw new \RuntimeException('Cannot read XLSX temp file');
                }
                fpassthru($fh);
                fclose($fh);
            } finally {
                self::cleanupDir($tmpDir);
            }
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * @param list<string> $headings
     * @param Generator|iterable $rows
     */
    private static function writeSheetXml(string $path, array $headings, $rows): void
    {
        $fh = fopen($path, 'wb');
        if ($fh === false) {
            throw new \RuntimeException('Cannot write sheet XML');
        }

        fwrite($fh, '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n");
        fwrite($fh, '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">');
        fwrite($fh, '<sheetData>');

        $r = 1;
        if ($headings !== []) {
            fwrite($fh, self::rowXml($r, $headings));
            $r++;
        }

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            fwrite($fh, self::rowXml($r, $row));
            $r++;
            if (($r % 500) === 0) {
                fflush($fh);
            }
        }

        fwrite($fh, '</sheetData></worksheet>');
        fclose($fh);
    }

    /**
     * @param list<string|int|float|null> $values
     */
    private static function rowXml(int $rowNum, array $values): string
    {
        $xml = '<row r="' . $rowNum . '">';
        $col = 0;
        foreach ($values as $value) {
            $col++;
            $ref = self::colLetter($col) . $rowNum;
            if ($value === null || $value === '') {
                continue;
            }
            if (is_int($value) || is_float($value)) {
                $xml .= '<c r="' . $ref . '"><v>' . $value . '</v></c>';
                continue;
            }
            $text = self::xmlText((string) $value);
            $xml .= '<c r="' . $ref . '" t="inlineStr"><is><t xml:space="preserve">' . $text . '</t></is></c>';
        }

        return $xml . '</row>';
    }

    private static function colLetter(int $index): string
    {
        $name = '';
        while ($index > 0) {
            $index--;
            $name = chr(65 + ($index % 26)) . $name;
            $index = intdiv($index, 26);
        }

        return $name;
    }

    private static function xmlText(string $value): string
    {
        // Excel запрещает часть control chars.
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $value) ?? $value;

        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private static function safeSheetTitle(string $title): string
    {
        $title = preg_replace('/[\\\\\\/\\*\\?\\:\\[\\]]+/', ' ', $title) ?? $title;
        $title = trim($title);
        if ($title === '') {
            $title = 'Sheet';
        }

        return mb_substr($title, 0, 31);
    }

    /**
     * @param list<string> $sheetFiles
     * @param list<string> $sheetTitles
     */
    private static function packZip(string $zipPath, array $sheetFiles, array $sheetTitles): void
    {
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Cannot create XLSX zip');
        }

        $zip->addFromString('[Content_Types].xml', self::contentTypesXml(count($sheetFiles)));
        $zip->addFromString('_rels/.rels', self::rootRelsXml());
        $zip->addFromString('xl/workbook.xml', self::workbookXml($sheetTitles));
        $zip->addFromString('xl/_rels/workbook.xml.rels', self::workbookRelsXml(count($sheetFiles)));
        $zip->addFromString('xl/styles.xml', self::stylesXml());

        foreach ($sheetFiles as $i => $path) {
            $zip->addFile($path, 'xl/worksheets/sheet' . ($i + 1) . '.xml');
        }

        $zip->close();
    }

    private static function contentTypesXml(int $sheetCount): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>';
        for ($i = 1; $i <= $sheetCount; $i++) {
            $xml .= '<Override PartName="/xl/worksheets/sheet' . $i
                . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }

        return $xml . '</Types>';
    }

    private static function rootRelsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>';
    }

    /**
     * @param list<string> $titles
     */
    private static function workbookXml(array $titles): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
            . ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets>';
        foreach ($titles as $i => $title) {
            $n = $i + 1;
            $xml .= '<sheet name="' . self::xmlText($title) . '" sheetId="' . $n . '" r:id="rId' . $n . '"/>';
        }

        return $xml . '</sheets></workbook>';
    }

    private static function workbookRelsXml(int $sheetCount): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
        for ($i = 1; $i <= $sheetCount; $i++) {
            $xml .= '<Relationship Id="rId' . $i
                . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet"'
                . ' Target="worksheets/sheet' . $i . '.xml"/>';
        }
        $xml .= '<Relationship Id="rId' . ($sheetCount + 1)
            . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles"'
            . ' Target="styles.xml"/>';

        return $xml . '</Relationships>';
    }

    private static function stylesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="1"><font><sz val="11"/><name val="Calibri"/></font></fonts>'
            . '<fills count="1"><fill><patternFill patternType="none"/></fill></fills>'
            . '<borders count="1"><border/></borders>'
            . '<cellStyleXfs count="1"><xf/></cellStyleXfs>'
            . '<cellXfs count="1"><xf/></cellXfs>'
            . '</styleSheet>';
    }

    private static function cleanupDir(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) ?: [] as $f) {
            if ($f === '.' || $f === '..') {
                continue;
            }
            $p = $dir . DIRECTORY_SEPARATOR . $f;
            if (is_file($p)) {
                @unlink($p);
            }
        }
        @rmdir($dir);
    }
}
