<?php

namespace App\Services\SiteAudit;

use App\SiteAuditCrawl;
use ZipArchive;

/**
 * Минимальный DOCX (OOXML) без PhpWord — сводка краула.
 */
class SiteAuditDocxBuilder
{
    /**
     * @return string absolute path to temp .docx
     */
    public function buildToTemp(SiteAuditCrawl $crawl): string
    {
        $path = tempnam(sys_get_temp_dir(), 'sa-docx-');
        if ($path === false) {
            throw new \RuntimeException('Cannot create temp file for DOCX');
        }
        $docx = $path . '.docx';
        @unlink($path);

        $zip = new ZipArchive();
        if ($zip->open($docx, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Cannot open DOCX zip');
        }

        $zip->addFromString('[Content_Types].xml', $this->contentTypes());
        $zip->addFromString('_rels/.rels', $this->rootRels());
        $zip->addFromString('word/_rels/document.xml.rels', $this->docRels());
        $zip->addFromString('word/document.xml', $this->documentXml($crawl));
        $zip->addFromString('word/styles.xml', $this->stylesXml());
        $zip->close();

        return $docx;
    }

    private function documentXml(SiteAuditCrawl $crawl): string
    {
        $project = $crawl->project;
        $domain = $project ? (string) $project->domain : '—';
        $buckets = is_array($crawl->buckets_json) ? $crawl->buckets_json : [];
        $counts = is_array($crawl->counts_json) ? $crawl->counts_json : [];
        $catalog = config('site_audit.findings', []);

        $paras = [];
        $paras[] = $this->p('Аудит сайта — сводка', true, 28);
        $paras[] = $this->p('Домен: ' . $domain);
        $paras[] = $this->p('Краул #' . $crawl->id . ' · ' . $crawl->statusLabelRu());
        $paras[] = $this->p(
            'URL: ' . (int) $crawl->pages_fetched . ' / ' . (int) $crawl->pages_total
            . ' (лимит ' . (int) $crawl->pages_limit . ')'
        );
        $paras[] = $this->p('');
        $paras[] = $this->p('Приоритеты', true, 22);
        $paras[] = $this->p('Грубые: ' . (int) ($buckets['critical'] ?? 0));
        $paras[] = $this->p('Прочие: ' . (int) ($buckets['other'] ?? 0));
        $paras[] = $this->p('Предупреждения: ' . (int) ($buckets['warning'] ?? 0));
        $paras[] = $this->p('Инфо: ' . (int) ($buckets['info'] ?? 0));

        if (! empty($counts['click_depth_max']) || isset($counts['deep_pages'])) {
            $paras[] = $this->p('');
            $paras[] = $this->p('Глубина клика', true, 22);
            if (isset($counts['click_depth_max'])) {
                $paras[] = $this->p('Макс. глубина: ' . (int) $counts['click_depth_max']);
            }
            if (isset($counts['deep_pages'])) {
                $paras[] = $this->p('Глубоких страниц: ' . (int) $counts['deep_pages']);
            }
        }

        $paras[] = $this->p('');
        $paras[] = $this->p('Находки по отчётам', true, 22);

        arsort($counts);
        foreach ($counts as $code => $cnt) {
            if ((int) $cnt <= 0) {
                continue;
            }
            if (in_array($code, ['pages_with_canonical', 'click_depth_max'], true)) {
                continue;
            }
            $meta = $catalog[$code] ?? [];
            $title = (string) ($meta['title'] ?? $code);
            $sev = SiteAuditFindingPresenter::severityLabel($meta['severity'] ?? 'info');
            $paras[] = $this->p($title . ' — ' . (int) $cnt . ' (' . $sev . ')');
        }

        $body = implode('', $paras);

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
            . '<w:body>' . $body
            . '<w:sectPr><w:pgSz w:w="11906" w:h="16838"/>'
            . '<w:pgMar w:top="1134" w:right="1134" w:bottom="1134" w:left="1134"/></w:sectPr>'
            . '</w:body></w:document>';
    }

    private function p(string $text, bool $bold = false, int $sz = 20): string
    {
        $t = htmlspecialchars($text, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $rPr = $bold
            ? '<w:rPr><w:b/><w:sz w:val="' . $sz . '"/><w:szCs w:val="' . $sz . '"/></w:rPr>'
            : '<w:rPr><w:sz w:val="' . $sz . '"/><w:szCs w:val="' . $sz . '"/></w:rPr>';

        return '<w:p><w:r>' . $rPr . '<w:t xml:space="preserve">' . $t . '</w:t></w:r></w:p>';
    }

    private function contentTypes(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'
            . '<Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>'
            . '</Types>';
    }

    private function rootRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>'
            . '</Relationships>';
    }

    private function docRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>';
    }

    private function stylesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
            . '<w:style w:type="paragraph" w:default="1" w:styleId="Normal">'
            . '<w:name w:val="Normal"/><w:qFormat/>'
            . '</w:style></w:styles>';
    }
}
