<?php

namespace App\Support;

/**
 * Брендинг и графика PDF-отчёта (mPDF, DejaVu Sans).
 *
 * Эталон для всех модулей: обложка GD-PNG, logo-icon-pdf.png, verifyCoverPng.
 * datagon.ru/docs/cabinet-pdf-report-template.md
 */
class TextAnalyzerPdfBranding
{
    public const BRAND_NAME = 'Датагон';

    public const BRAND_TAGLINE = 'SEO-инструменты для специалистов';

    public const BRAND_SITE = 'https://datagon.ru';

    public const COLOR_PRIMARY = '#2f5de0';

    public const COLOR_PRIMARY_DARK = '#1e3f9e';

    public const COLOR_ACCENT = '#8fd3ff';

    public const COLOR_COMPETITOR = '#b45309';

    /**
     * @return array<string, mixed>
     */
    public static function viewData(): array
    {
        self::ensureCoverAssets();

        return [
            'brandName' => self::BRAND_NAME,
            'brandTagline' => self::BRAND_TAGLINE,
            'brandSite' => self::BRAND_SITE,
            'brandSiteHost' => parse_url(self::BRAND_SITE, PHP_URL_HOST) ?: 'datagon.ru',
            'logoIconPath' => self::logoIconPath(),
            'logoFullPath' => self::logoFullPath(),
            'coverBackgroundPath' => self::coverBackgroundPath(),
            'coverLogoPath' => self::coverLogoPath(),
        ];
    }

    public static function coverBackgroundPath(): string
    {
        return public_path('img/pdf-cover-bg.png');
    }

    public static function coverLogoPath(): string
    {
        return public_path('img/pdf-cover-logo.png');
    }

    /**
     * Готовая обложка A4 (фон + текст) — один PNG, без HTML/mPDF layout.
     */
    public static function coverPageImagePath(array $meta, bool $hasCompare, string $competitorLabel): string
    {
        self::ensureCoverAssets();

        $source = (string) ($meta['source_label'] ?? '');
        if (mb_strlen($source) > 90) {
            $source = mb_substr($source, 0, 87) . '…';
        }

        $payload = [
            'generated' => (string) ($meta['generated_at'] ?? ''),
            'source' => $source,
            'version' => (string) ($meta['version'] ?? ''),
            'compare' => $hasCompare,
            'competitor' => $competitorLabel,
            'locale' => app()->getLocale(),
            'cover_rev' => (string) ($meta['cover_rev'] ?? '16'),
            'cover_kicker' => (string) ($meta['cover_kicker'] ?? ''),
            'cover_title' => (string) ($meta['cover_title'] ?? ''),
            'cover_lead' => (string) ($meta['cover_lead'] ?? ''),
            'cover_footer' => (string) ($meta['cover_footer'] ?? ''),
            'logo_mtime' => is_file(self::logoIconPath()) ? filemtime(self::logoIconPath()) : 0,
        ];
        $path = storage_path('app/mpdf-tmp/cover-' . md5(json_encode($payload)) . '.png');
        if (!is_dir(dirname($path))) {
            @mkdir(dirname($path), 0755, true);
        }
        if (!is_file($path)) {
            self::writeCoverPagePng($path, $payload);
        }

        return $path;
    }

    public static function ensureCoverAssets(): void
    {
        self::ensureRasterLogos();

        $bg = self::coverBackgroundPath();
        if (!is_file($bg) || filesize($bg) < 8000) {
            self::writeCoverBackgroundPng($bg, 1240, 1754);
        }
    }

    /**
     * @return array<int, string>
     */
    public static function verifyLogoIconPng(string $path): array
    {
        $errors = [];
        if (!is_file($path)) {
            return ['logo icon png missing'];
        }
        if (filesize($path) < 5000) {
            $errors[] = 'logo icon png too small (expected SVG raster)';
        }
        $im = @imagecreatefrompng($path);
        if ($im === false) {
            return ['logo icon png unreadable'];
        }
        $w = imagesx($im);
        $h = imagesy($im);
        if ($w < 80 || $h < 80) {
            $errors[] = 'logo icon dimensions too small';
        }
        $corners = [[0, 0], [$w - 1, 0], [0, $h - 1], [$w - 1, $h - 1]];
        foreach ($corners as [$x, $y]) {
            $alpha = (imagecolorat($im, $x, $y) & 0x7F000000) >> 24;
            if ($alpha < 100) {
                $errors[] = 'logo icon corner not transparent';
                break;
            }
        }
        $halo = 0;
        for ($y = 1; $y < $h - 1; $y++) {
            for ($x = 1; $x < $w - 1; $x++) {
                $rgba = imagecolorat($im, $x, $y);
                $alpha = ($rgba & 0x7F000000) >> 24;
                if ($alpha > 100) {
                    continue;
                }
                $r = ($rgba >> 16) & 0xFF;
                $g = ($rgba >> 8) & 0xFF;
                $b = $rgba & 0xFF;
                if ($r < 235 || $g < 235 || $b < 235) {
                    continue;
                }
                foreach ([[-1, 0], [1, 0], [0, -1], [0, 1]] as [$dx, $dy]) {
                    $neighborAlpha = (imagecolorat($im, $x + $dx, $y + $dy) & 0x7F000000) >> 24;
                    if ($neighborAlpha > 100) {
                        $halo++;
                        break;
                    }
                }
            }
        }
        imagedestroy($im);
        if ($halo > 40) {
            $errors[] = 'logo icon has white halo (' . $halo . ' px)';
        }

        return $errors;
    }

    /**
     * @return array<int, string>
     */
    public static function verifyCoverPng(string $path): array
    {
        $errors = [];
        if (!is_file($path)) {
            return ['cover png missing'];
        }
        $im = @imagecreatefrompng($path);
        if ($im === false) {
            return ['cover png unreadable'];
        }

        $width = imagesx($im);
        $height = imagesy($im);
        $padL = (int) round(18 * $width / 210);
        $x1 = max(0, $padL - 10);
        $x2 = min($width - 20, $padL + 420);
        $y1 = (int) round(12 * $height / 297);
        $y2 = (int) round(130 * $height / 297);

        $brightWhite = 0;
        $logoIconPixels = 0;
        $whiteBarRows = 0;
        for ($y = $y1; $y < $y2; $y++) {
            $rowWhite = 0;
            $rowTotal = $x2 - $x1;
            for ($x = $x1; $x < $x2; $x++) {
                $rgba = imagecolorat($im, $x, $y);
                $r = ($rgba >> 16) & 0xFF;
                $g = ($rgba >> 8) & 0xFF;
                $b = $rgba & 0xFF;
                if ($r >= 245 && $g >= 245 && $b >= 245) {
                    $brightWhite++;
                    $rowWhite++;
                }
                if ($b > 180 && $g > 60 && $r < 90) {
                    $logoIconPixels++;
                }
            }
            if ($rowTotal > 0 && ($rowWhite / $rowTotal) >= 0.85) {
                $whiteBarRows++;
            }
        }

        $corner = imagecolorat($im, $padL + 4, $y1 + 4);
        $cr = ($corner >> 16) & 0xFF;
        $cg = ($corner >> 8) & 0xFF;
        $cb = $corner & 0xFF;

        imagedestroy($im);

        if ($whiteBarRows >= 3) {
            $errors[] = 'cover logo white box (' . $whiteBarRows . ' rows)';
        }
        if ($cr >= 245 && $cg >= 245 && $cb >= 245) {
            $errors[] = 'cover logo zone has white background';
        }
        if ($logoIconPixels < 120) {
            $errors[] = 'cover logo icon not found';
        }

        return $errors;
    }

    public static function logoDarkSvgPath(): string
    {
        return public_path('img/logo.svg');
    }

    /**
     * Горизонтальный логотип «иконка + Датагон» из logo.svg для тёмной обложки.
     */
    public static function ensureCoverLogoRaster(): void
    {
        $svg = self::logoDarkSvgPath();
        $png = self::coverLogoPath();
        $needsRefresh = !is_file($png)
            || filesize($png) < 2000
            || (is_file($svg) && filemtime($svg) > filemtime($png));

        if (!$needsRefresh) {
            return;
        }

        if (is_file($svg) && self::rasterizeLogoBand($svg, $png, 440, 96)) {
            return;
        }
    }

    public static function logoIconPath(): string
    {
        return public_path('img/logo-icon-pdf.png');
    }

    /** @deprecated Старый растр обложки; в pdf-cover — иконка + текст. */
    public static function logoFullPath(): string
    {
        return self::logoIconPath();
    }

    /** Логотип для светлого фона (публичные страницы). */
    public static function logoOnLightAsset(): string
    {
        return asset('img/logo-on-light.svg');
    }

    public static function loginUrl(): string
    {
        return route('login');
    }

    public static function registerUrl(string $from = 'text-analyzer-public-share'): string
    {
        $query = http_build_query([
            'module' => 'text-analyzer',
            'from' => $from,
        ]);

        return route('register') . '?' . $query;
    }

    /**
     * PNG иконки для PDF. Эталон: public/img/logo-icon-pdf.png (не перегенерировать, если валиден).
     */
    public static function ensureRasterLogos(): void
    {
        $iconPath = self::logoIconPath();
        if (is_file($iconPath) && self::verifyLogoIconPng($iconPath) === []) {
            return;
        }

        $svg = public_path('img/logo-icon.svg');
        if (!is_file($svg)) {
            return;
        }

        $tmp = sys_get_temp_dir() . '/dg-icon-raster-' . getmypid() . '.png';
        if (!self::rasterizeSvg($svg, $tmp, 256)) {
            return;
        }

        self::makeNearWhiteTransparent($tmp, 252);
        self::defringeExteriorLightPixels($tmp);
        self::cropPngToAlphaBounds($tmp, 1);

        $dir = dirname($iconPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        @copy($tmp, $iconPath);
        @unlink($tmp);
    }

    protected static function pngNeedsRefresh(string $svg, string $png): bool
    {
        if (!is_file($png) || filesize($png) < 1200) {
            return true;
        }

        if (!self::pngHasTransparency($png)) {
            return true;
        }

        return filemtime($svg) > filemtime($png);
    }

    protected static function pngHasTransparency(string $path): bool
    {
        if (!function_exists('imagecreatefrompng')) {
            return true;
        }
        $im = @imagecreatefrompng($path);
        if ($im === false) {
            return false;
        }
        $w = imagesx($im);
        $h = imagesy($im);
        $step = max(1, (int) min($w, $h) / 8);
        for ($y = 0; $y < $h; $y += $step) {
            for ($x = 0; $x < $w; $x += $step) {
                $rgba = imagecolorat($im, $x, $y);
                if (($rgba & 0x7F000000) >> 24 > 0) {
                    imagedestroy($im);

                    return true;
                }
            }
        }
        imagedestroy($im);

        return false;
    }

    protected static function rasterizeSvg(string $svg, string $png, int $size): bool
    {
        $dir = dirname($png);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        if (self::commandExists('rsvg-convert')) {
            $cmd = sprintf(
                'rsvg-convert -w %d -h %d %s -o %s 2>/dev/null',
                $size,
                $size,
                escapeshellarg($svg),
                escapeshellarg($png)
            );
            @exec($cmd);
            if (is_file($png) && filesize($png) > 800) {
                return true;
            }
        }

        $tmpDir = sys_get_temp_dir() . '/dg-pdf-logo-' . getmypid();
        if (!is_dir($tmpDir)) {
            @mkdir($tmpDir, 0755, true);
        }

        $cmd = sprintf(
            'qlmanage -t -s %d -o %s %s 2>/dev/null',
            $size,
            escapeshellarg($tmpDir),
            escapeshellarg($svg)
        );
        @exec($cmd);

        $base = basename($svg);
        $candidates = [
            $tmpDir . '/' . $base . '.png',
            $tmpDir . '/' . pathinfo($base, PATHINFO_FILENAME) . '.png',
        ];

        foreach ($candidates as $candidate) {
            if (is_file($candidate) && filesize($candidate) > 800) {
                @copy($candidate, $png);
                self::cleanupDir($tmpDir);

                return is_file($png);
            }
        }

        self::cleanupDir($tmpDir);

        return false;
    }

    protected static function makeNearWhiteTransparent(string $path, int $threshold = 245): void
    {
        if (!function_exists('imagecreatefrompng')) {
            return;
        }
        $im = @imagecreatefrompng($path);
        if ($im === false) {
            return;
        }
        imagesavealpha($im, true);
        imagealphablending($im, false);
        $w = imagesx($im);
        $h = imagesy($im);
        $transparent = imagecolorallocatealpha($im, 0, 0, 0, 127);
        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                $rgba = imagecolorat($im, $x, $y);
                $r = ($rgba >> 16) & 0xFF;
                $g = ($rgba >> 8) & 0xFF;
                $b = $rgba & 0xFF;
                if ($r >= $threshold && $g >= $threshold && $b >= $threshold) {
                    imagesetpixel($im, $x, $y, $transparent);
                }
            }
        }
        imagepng($im, $path);
        imagedestroy($im);
    }

    protected static function defringeExteriorLightPixels(string $path): void
    {
        if (!function_exists('imagecreatefrompng')) {
            return;
        }
        $im = @imagecreatefrompng($path);
        if ($im === false) {
            return;
        }
        $w = imagesx($im);
        $h = imagesy($im);
        imagesavealpha($im, true);
        imagealphablending($im, false);
        $transparent = imagecolorallocatealpha($im, 0, 0, 0, 127);

        for ($y = 1; $y < $h - 1; $y++) {
            for ($x = 1; $x < $w - 1; $x++) {
                $rgba = imagecolorat($im, $x, $y);
                $alpha = ($rgba & 0x7F000000) >> 24;
                if ($alpha > 100) {
                    continue;
                }
                $r = ($rgba >> 16) & 0xFF;
                $g = ($rgba >> 8) & 0xFF;
                $b = $rgba & 0xFF;
                if ($r < 210 || $g < 210 || $b < 210) {
                    continue;
                }
                $exterior = false;
                foreach ([[-1, 0], [1, 0], [0, -1], [0, 1]] as [$dx, $dy]) {
                    $neighborAlpha = (imagecolorat($im, $x + $dx, $y + $dy) & 0x7F000000) >> 24;
                    if ($neighborAlpha > 100) {
                        $exterior = true;
                        break;
                    }
                }
                if ($exterior) {
                    imagesetpixel($im, $x, $y, $transparent);
                }
            }
        }

        imagepng($im, $path);
        imagedestroy($im);
    }

    protected static function rasterizeLogoBand(string $svg, string $png, int $outW, int $outH): bool
    {
        if (self::rasterizeSvgToSize($svg, $png, $outW, $outH)) {
            return true;
        }

        $tmp = sys_get_temp_dir() . '/dg-logo-band-' . getmypid() . '.png';
        $size = max($outW, $outH * 4);
        if (!self::rasterizeSvg($svg, $tmp, $size)) {
            return false;
        }
        self::cropAndResizeLogoBand($tmp, $png, $outW, $outH);
        @unlink($tmp);

        return is_file($png) && filesize($png) > 800;
    }

    protected static function rasterizeSvgToSize(string $svg, string $png, int $width, int $height): bool
    {
        $dir = dirname($png);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        if (!self::commandExists('rsvg-convert')) {
            return false;
        }

        $cmd = sprintf(
            'rsvg-convert -w %d -h %d %s -o %s 2>/dev/null',
            $width,
            $height,
            escapeshellarg($svg),
            escapeshellarg($png)
        );
        @exec($cmd);

        return is_file($png) && filesize($png) > 800;
    }

    protected static function cropAndResizeLogoBand(string $srcPath, string $destPath, int $outW, int $outH): void
    {
        if (!function_exists('imagecreatefrompng')) {
            return;
        }
        $src = @imagecreatefrompng($srcPath);
        if ($src === false) {
            return;
        }

        $w = imagesx($src);
        $h = imagesy($src);
        $minX = $w;
        $minY = $h;
        $maxX = 0;
        $maxY = 0;

        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                $rgba = imagecolorat($src, $x, $y);
                $alpha = ($rgba & 0x7F000000) >> 24;
                $r = ($rgba >> 16) & 0xFF;
                $g = ($rgba >> 8) & 0xFF;
                $b = $rgba & 0xFF;
                if ($alpha >= 120) {
                    continue;
                }
                if ($r >= 248 && $g >= 248 && $b >= 248) {
                    continue;
                }
                $minX = min($minX, $x);
                $minY = min($minY, $y);
                $maxX = max($maxX, $x);
                $maxY = max($maxY, $y);
            }
        }

        if ($maxX <= $minX || $maxY <= $minY) {
            imagedestroy($src);

            return;
        }

        $cropW = $maxX - $minX + 1;
        $cropH = $maxY - $minY + 1;
        $dest = imagecreatetruecolor($outW, $outH);
        imagesavealpha($dest, true);
        $transparent = imagecolorallocatealpha($dest, 0, 0, 0, 127);
        imagefill($dest, 0, 0, $transparent);
        imagealphablending($dest, true);
        imagecopyresampled($dest, $src, 0, 0, $minX, $minY, $outW, $outH, $cropW, $cropH);
        imagepng($dest, $destPath);
        imagedestroy($dest);
        imagedestroy($src);
    }

    protected static function commandExists(string $command): bool
    {
        $path = trim((string) shell_exec(sprintf('command -v %s 2>/dev/null', escapeshellarg($command))));

        return $path !== '';
    }

    protected static function cleanupDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (glob($dir . '/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($dir);
    }

    /**
     * Линейный график Zipf (факт / идеал), опционально конкурент.
     *
     * @param array<int, array<string, mixed>> $graph
     * @param array<int, array<string, mixed>>|null $competitorGraph
     */
    public static function zipfChartImagePath(array $graph, ?array $competitorGraph = null): ?string
    {
        $points = self::zipfChartPoints($graph, 12);
        if ($points === []) {
            return null;
        }

        $compPoints = $competitorGraph !== null ? self::zipfChartPoints($competitorGraph, 12) : [];

        $w = 760;
        $h = 280;
        $im = imagecreatetruecolor($w, $h);
        if (function_exists('imageantialias')) {
            imageantialias($im, true);
        }
        imagesavealpha($im, true);
        $white = imagecolorallocate($im, 255, 255, 255);
        imagefill($im, 0, 0, $white);

        $grid = imagecolorallocate($im, 226, 232, 240);
        $axis = imagecolorallocate($im, 148, 163, 184);
        $main = imagecolorallocate($im, 47, 93, 224);
        $ideal = imagecolorallocate($im, 234, 88, 12);
        $comp = imagecolorallocate($im, 180, 83, 9);
        $labelColor = imagecolorallocate($im, 71, 85, 105);

        $font = self::resolveFontPath(false);
        $labelSize = 11;

        $padL = 48;
        $padR = 20;
        $padT = 22;
        $padB = 52;
        $plotW = $w - $padL - $padR;
        $plotH = $h - $padT - $padB;

        $maxY = 1;
        foreach ($points as $p) {
            $maxY = max($maxY, $p['actual'], $p['ideal']);
        }
        foreach ($compPoints as $p) {
            $maxY = max($maxY, $p['actual']);
        }
        $maxY = (int) ceil($maxY * 1.12);

        for ($g = 0; $g <= 4; $g++) {
            $y = $padT + (int) round($plotH * $g / 4);
            imageline($im, $padL, $y, $w - $padR, $y, $grid);
        }
        imageline($im, $padL, $padT, $padL, $padT + $plotH, $axis);
        imageline($im, $padL, $padT + $plotH, $w - $padR, $padT + $plotH, $axis);

        $n = count($points);
        $xAt = static function (int $i) use ($padL, $plotW, $n): int {
            if ($n <= 1) {
                return $padL;
            }

            return $padL + (int) round($plotW * $i / ($n - 1));
        };
        $yAt = static function (int $val) use ($padT, $plotH, $maxY): int {
            return $padT + $plotH - (int) round($plotH * $val / $maxY);
        };

        if ($n > 1) {
            imagesetthickness($im, 2);
            for ($i = 0; $i < $n - 1; $i++) {
                imageline(
                    $im,
                    $xAt($i),
                    $yAt($points[$i]['ideal']),
                    $xAt($i + 1),
                    $yAt($points[$i + 1]['ideal']),
                    $ideal
                );
            }
            for ($i = 0; $i < $n - 1; $i++) {
                imageline(
                    $im,
                    $xAt($i),
                    $yAt($points[$i]['actual']),
                    $xAt($i + 1),
                    $yAt($points[$i + 1]['actual']),
                    $main
                );
            }
            if ($compPoints !== []) {
                for ($i = 0; $i < min($n, count($compPoints)) - 1; $i++) {
                    imageline(
                        $im,
                        $xAt($i),
                        $yAt($compPoints[$i]['actual']),
                        $xAt($i + 1),
                        $yAt($compPoints[$i + 1]['actual']),
                        $comp
                    );
                }
            }
            imagesetthickness($im, 1);
            foreach ($points as $i => $p) {
                imagefilledellipse($im, $xAt($i), $yAt($p['actual']), 7, 7, $main);
                if (isset($compPoints[$i])) {
                    imagefilledellipse($im, $xAt($i), $yAt($compPoints[$i]['actual']), 6, 6, $comp);
                }
                if ($i % 2 === 0 || $i === $n - 1) {
                    $lbl = mb_substr($p['word'], 0, 12);
                    $tx = $xAt($i);
                    $tw = self::coverTextWidth($lbl, $font, $labelSize);
                    self::drawChartLabel($im, $font, $labelSize, $tx - (int) ($tw / 2), $padT + $plotH + 24, $labelColor, $lbl);
                }
            }
        }

        $path = storage_path('app/mpdf-tmp/zipf-v2-' . md5(json_encode([$points, $compPoints])) . '.png');
        if (!is_dir(dirname($path))) {
            @mkdir(dirname($path), 0755, true);
        }
        imagepng($im, $path);
        imagedestroy($im);

        return is_file($path) ? $path : null;
    }

    /**
     * @param array<int, array<string, mixed>> $graph
     * @return array<int, array<string, mixed>>
     */
    protected static function zipfChartPoints(array $graph, int $limit): array
    {
        $rows = self::zipfTableRows($graph);

        return array_slice($rows, 0, $limit);
    }

    /**
     * @param array<int, array<string, mixed>> $graph
     * @return array<int, array<string, mixed>>
     */
    public static function zipfTableRows(array $graph): array
    {
        if ($graph === []) {
            return [];
        }

        $baseY = (int) ($graph[0]['y'] ?? 1);
        $rows = [];
        foreach ($graph as $point) {
            $rank = (int) ($point['rank'] ?? $point['x'] ?? 0);
            if ($rank < 1) {
                continue;
            }
            $actual = (int) ($point['y'] ?? 0);
            $ideal = max(1, (int) round($baseY / $rank));
            $rows[] = [
                'rank' => $rank,
                'word' => (string) ($point['label'] ?? ''),
                'actual' => $actual,
                'ideal' => $ideal,
                'delta' => $actual - $ideal,
            ];
        }

        return $rows;
    }

    /**
     * @param array<int, array<string, mixed>> $cloudItems
     * @return array<int, array<string, mixed>>
     */
    public static function cloudRowsForPdf(array $cloudItems, int $limit = 12): array
    {
        $slice = array_slice($cloudItems, 0, $limit);
        $rows = [];
        foreach ($slice as $item) {
            $rows[] = [
                'text' => (string) ($item['text'] ?? ''),
                'weight' => (int) ($item['weight'] ?? 1),
            ];
        }

        return $rows;
    }

    /**
     * @param resource $im
     */
    protected static function imageFilledRoundedRect($im, int $x1, int $y1, int $x2, int $y2, int $radius, int $color): void
    {
        $radius = max(1, min($radius, (int) floor(min($x2 - $x1, $y2 - $y1) / 2)));
        imagefilledrectangle($im, $x1 + $radius, $y1, $x2 - $radius, $y2, $color);
        imagefilledrectangle($im, $x1, $y1 + $radius, $x2, $y2 - $radius, $color);
        imagefilledellipse($im, $x1 + $radius, $y1 + $radius, $radius * 2, $radius * 2, $color);
        imagefilledellipse($im, $x2 - $radius, $y1 + $radius, $radius * 2, $radius * 2, $color);
        imagefilledellipse($im, $x1 + $radius, $y2 - $radius, $radius * 2, $radius * 2, $color);
        imagefilledellipse($im, $x2 - $radius, $y2 - $radius, $radius * 2, $radius * 2, $color);
    }

    protected static function writeCoverBackgroundPng(string $path, int $width, int $height): void
    {
        $im = imagecreatetruecolor($width, $height);
        $top = [26, 58, 158];
        $bottom = [15, 23, 42];
        for ($y = 0; $y < $height; $y++) {
            $t = $height > 1 ? $y / ($height - 1) : 0;
            $r = (int) round($top[0] + ($bottom[0] - $top[0]) * $t);
            $g = (int) round($top[1] + ($bottom[1] - $top[1]) * $t);
            $b = (int) round($top[2] + ($bottom[2] - $top[2]) * $t);
            $line = imagecolorallocate($im, $r, $g, $b);
            imageline($im, 0, $y, $width, $y, $line);
        }

        $gridColor = imagecolorallocatealpha($im, 255, 255, 255, 115);
        $step = 36;
        for ($x = 0; $x < $width; $x += $step) {
            imageline($im, $x, 0, $x, $height, $gridColor);
        }
        for ($y = 0; $y < $height; $y += $step) {
            imageline($im, 0, $y, $width, $y, $gridColor);
        }

        $glow = imagecolorallocatealpha($im, 47, 93, 224, 90);
        imagefilledellipse($im, (int) ($width * 0.88), (int) ($height * 0.12), 180, 180, $glow);

        self::savePng($im, $path);
    }

    protected static function writeLogoIconTransparentPng(string $path, int $size): void
    {
        $im = imagecreatetruecolor($size, $size);
        imagesavealpha($im, true);
        imagealphablending($im, false);
        $transparent = imagecolorallocatealpha($im, 0, 0, 0, 127);
        imagefill($im, 0, 0, $transparent);
        imagealphablending($im, true);

        $pad = (int) round($size * 0.0625);
        $radius = (int) round($size * 0.21875);
        $x2 = $size - $pad - 1;
        $y2 = $size - $pad - 1;

        $blue = imagecolorallocate($im, 47, 93, 224);
        $blueDark = imagecolorallocate($im, 30, 63, 158);
        $white = imagecolorallocate($im, 255, 255, 255);
        $accent = imagecolorallocate($im, 143, 211, 255);

        self::imageFilledRoundedRect($im, $pad, $pad, $x2, $y2, $radius, $blue);
        self::imageFilledRoundedRect($im, $pad + 2, $pad + 2, (int) ($size * 0.58), $y2 - 1, max(2, $radius - 2), $blueDark);

        $scale = ($size - 2 * $pad) / 56.0;
        $tx = static function (float $x) use ($pad, $scale): int {
            return (int) round($pad + ($x - 4) * $scale);
        };
        $ty = static function (float $y) use ($pad, $scale): int {
            return (int) round($pad + ($y - 4) * $scale);
        };

        imagesetthickness($im, max(3, (int) round($size / 13)));
        imageline($im, $tx(18), $ty(18), $tx(18), $ty(42), $white);
        imageline($im, $tx(18), $ty(18), $tx(26), $ty(18), $white);
        imageline($im, $tx(18), $ty(42), $tx(34), $ty(42), $white);
        imagearc(
            $im,
            $tx(33),
            $ty(30),
            (int) round(24 * $scale),
            (int) round(24 * $scale),
            300,
            60,
            $white
        );

        imagefilledellipse($im, $tx(47.5), $ty(18.5), (int) round(5 * $scale), (int) round(5 * $scale), $accent);
        imagefilledellipse($im, $tx(52.5), $ty(24), (int) round(3.6 * $scale), (int) round(3.6 * $scale), $accent);

        self::savePng($im, $path);
    }

    protected static function cropPngToAlphaBounds(string $path, int $padding = 0): void
    {
        if (!function_exists('imagecreatefrompng')) {
            return;
        }
        $src = @imagecreatefrompng($path);
        if ($src === false) {
            return;
        }
        $w = imagesx($src);
        $h = imagesy($src);
        $minX = $w;
        $minY = $h;
        $maxX = 0;
        $maxY = 0;
        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                $alpha = (imagecolorat($src, $x, $y) & 0x7F000000) >> 24;
                if ($alpha > 100) {
                    continue;
                }
                $minX = min($minX, $x);
                $minY = min($minY, $y);
                $maxX = max($maxX, $x);
                $maxY = max($maxY, $y);
            }
        }
        if ($maxX <= $minX || $maxY <= $minY) {
            imagedestroy($src);

            return;
        }
        $minX = max(0, $minX - $padding);
        $minY = max(0, $minY - $padding);
        $maxX = min($w - 1, $maxX + $padding);
        $maxY = min($h - 1, $maxY + $padding);
        $cropW = $maxX - $minX + 1;
        $cropH = $maxY - $minY + 1;
        $dest = imagecreatetruecolor($cropW, $cropH);
        imagesavealpha($dest, true);
        imagealphablending($dest, false);
        $transparent = imagecolorallocatealpha($dest, 0, 0, 0, 127);
        imagefill($dest, 0, 0, $transparent);
        imagealphablending($dest, true);
        imagecopy($dest, $src, 0, 0, $minX, $minY, $cropW, $cropH);
        imagepng($dest, $path);
        imagedestroy($dest);
        imagedestroy($src);
    }

    public static function coverLogoBlockPath(int $height): string
    {
        self::ensureRasterLogos();
        $iconPath = self::logoIconPath();
        $fontBold = self::resolveFontPath(true) ?? self::resolveFontPath(false);
        $fontSize = (int) round($height * 0.5);
        $textW = self::coverTextWidth(self::BRAND_NAME, $fontBold, $fontSize);
        $icon = imagecreatefrompng($iconPath);
        $iconW = $icon !== false ? (int) round(imagesx($icon) * $height / max(1, imagesy($icon))) : $height;
        if ($icon !== false) {
            imagedestroy($icon);
        }
        $gap = (int) round($height * 0.2);
        $blockW = $iconW + $gap + $textW + 4;
        $blockH = $height;
        $rev = md5(filemtime($iconPath) . '|' . $blockW . '|' . $blockH);
        $path = storage_path('app/mpdf-tmp/cover-logo-block-' . $rev . '.png');
        if (is_file($path)) {
            return $path;
        }

        $block = imagecreatetruecolor($blockW, $blockH);
        imagesavealpha($block, true);
        imagealphablending($block, false);
        $transparent = imagecolorallocatealpha($block, 0, 0, 0, 127);
        imagefill($block, 0, 0, $transparent);
        imagealphablending($block, true);

        self::copyRasterIcon($block, $iconPath, 0, 0, $height);

        $brandColor = imagecolorallocate($block, 248, 250, 252);
        $baseline = (int) round($height * 0.68);
        self::drawCoverText($block, $fontBold, $fontSize, $iconW + $gap, $baseline, $brandColor, self::BRAND_NAME);

        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        imagepng($block, $path);
        imagedestroy($block);

        return $path;
    }

    protected static function writeIconPng(string $path, int $size): void
    {
        self::writeLogoIconTransparentPng($path, $size);
    }

    protected static function writeFullLogoPng(string $path): void
    {
        self::writeIconPng($path, 96);
    }

    protected static function resolveFontPath(bool $bold = false): ?string
    {
        $file = $bold ? 'DejaVuSans-Bold.ttf' : 'DejaVuSans.ttf';
        $candidates = [
            base_path('vendor/mpdf/mpdf/ttfonts/' . $file),
            '/System/Library/Fonts/Supplemental/Arial.ttf',
            '/Library/Fonts/Arial.ttf',
            '/usr/share/fonts/truetype/dejavu/' . $file,
            '/opt/homebrew/share/fonts/dejavu-sans-fonts/' . $file,
        ];
        foreach ($candidates as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * @param array{generated:string,source:string,version:string,compare:bool,competitor:string,locale:string} $payload
     */
    protected static function writeCoverPagePng(string $path, array $payload): void
    {
        $width = 1240;
        $height = 1754;
        self::writeCoverBackgroundPng(self::coverBackgroundPath(), $width, $height);

        $im = imagecreatefrompng(self::coverBackgroundPath());
        if ($im === false) {
            return;
        }

        $mmX = static function (float $mm) use ($width): int {
            return (int) round($mm * $width / 210);
        };
        $mmY = static function (float $mm) use ($height): int {
            return (int) round($mm * $height / 297);
        };

        $padL = $mmX(18);
        $contentW = $width - $padL - $mmX(18);

        $font = self::resolveFontPath(false);
        $fontBold = self::resolveFontPath(true) ?? $font;

        $cKicker = imagecolorallocate($im, 143, 211, 255);
        $cTitle = imagecolorallocate($im, 248, 250, 252);
        $cLead = imagecolorallocate($im, 203, 213, 225);
        $cMetaLabel = imagecolorallocate($im, 148, 163, 184);
        $cMetaValue = imagecolorallocate($im, 241, 245, 249);
        $cMetaValueSm = imagecolorallocate($im, 226, 232, 240);
        $cFooter = imagecolorallocate($im, 100, 116, 139);
        $cFooterMid = imagecolorallocate($im, 71, 85, 105);
        $cCompareBg = imagecolorallocate($im, 66, 32, 6);
        $cCompareText = imagecolorallocate($im, 253, 230, 138);
        $cCompareStrong = imagecolorallocate($im, 252, 211, 77);
        $cBoxBg = imagecolorallocate($im, 30, 41, 59);
        $cBoxBorderBlue = imagecolorallocate($im, 47, 93, 224);
        $cBoxBorderGray = imagecolorallocate($im, 100, 116, 139);
        $cLine = imagecolorallocate($im, 71, 85, 105);

        $footerY = $height - $mmY(14);
        $footerLineY = $footerY - 18;
        $zoneTop = $mmY(22);
        $zoneBottom = $footerLineY - $mmY(14);

        $lead = $payload['cover_lead'] !== ''
            ? $payload['cover_lead']
            : (string) __('Word statistics, Zipf distribution, phrase analysis and word clouds for page text or URL.');
        $leadLines = self::wrapCoverText($lead, $font, 22, $contentW);
        $compareH = !empty($payload['compare']) ? 72 : 0;
        $logoH = $mmY(11);

        $coverKicker = $payload['cover_kicker'] !== ''
            ? $payload['cover_kicker']
            : (string) __('Text analyzer report');
        $coverTitle = $payload['cover_title'] !== ''
            ? $payload['cover_title']
            : (string) __('Text Analyse');
        $titleLayout = self::resolveCoverTitleLayout($coverTitle, $fontBold, $contentW);
        $titleSize = $titleLayout['size'];
        $titleLines = $titleLayout['lines'];
        $titleLineStep = (int) round($titleSize * 1.12);
        $titleBlockH = count($titleLines) * $titleLineStep + 12;

        $contentH = $logoH + $mmY(14)
            + $mmY(10) + 28 + $titleBlockH + 16 + count($leadLines) * 28
            + $mmY(10) + 110
            + ($compareH > 0 ? 20 + $compareH : 0);
        $y = $zoneTop + (int) max(0, ($zoneBottom - $zoneTop - $contentH) / 2);

        $y = self::drawCoverLogoBand($im, $padL, $y, $logoH) + $mmY(12);

        $y = self::drawCoverText($im, $font, 18, $padL, $y + 18, $cKicker, mb_strtoupper($coverKicker)) + 10;
        foreach ($titleLines as $titleLine) {
            $y = self::drawCoverText($im, $fontBold, $titleSize, $padL, $y + $titleSize, $cTitle, $titleLine) + 8;
        }
        $y += 8;

        foreach ($leadLines as $line) {
            $y = self::drawCoverText($im, $font, 22, $padL, $y + 22, $cLead, $line) + 6;
        }
        $y += $mmY(8);

        $boxY = $y + 8;
        $boxH = 110;
        $leftW = (int) round($contentW * 0.34);
        $gap = (int) round($contentW * 0.03);
        $rightW = $contentW - $leftW - $gap;
        $rightX = $padL + $leftW + $gap;

        imagefilledrectangle($im, $padL, $boxY, $padL + $leftW, $boxY + $boxH, $cBoxBg);
        imagefilledrectangle($im, $padL, $boxY, $padL + 8, $boxY + $boxH, $cBoxBorderBlue);
        imagefilledrectangle($im, $rightX, $boxY, $rightX + $rightW, $boxY + $boxH, $cBoxBg);
        imagefilledrectangle($im, $rightX, $boxY, $rightX + 8, $boxY + $boxH, $cBoxBorderGray);

        $labelY = $boxY + 28;
        self::drawCoverText($im, $font, 16, $padL + 18, $labelY, $cMetaLabel, mb_strtoupper((string) __('Generated at')));
        self::drawCoverText($im, $fontBold, 24, $padL + 18, $labelY + 34, $cMetaValue, $payload['generated']);
        self::drawCoverText($im, $font, 16, $rightX + 18, $labelY, $cMetaLabel, mb_strtoupper((string) __('Source')));
        $srcY = $labelY + 34;
        foreach (self::wrapCoverText($payload['source'], $font, 20, $rightW - 36) as $line) {
            $srcY = self::drawCoverText($im, $font, 20, $rightX + 18, $srcY, $cMetaValueSm, $line) + 4;
        }

        $y = $boxY + $boxH + 20;

        if (!empty($payload['compare'])) {
            imagefilledrectangle($im, $padL, $y, $padL + $contentW, $y + $compareH, $cCompareBg);
            imagefilledrectangle($im, $padL, $y, $padL + 8, $y + $compareH, imagecolorallocate($im, 217, 119, 6));
            self::drawCoverText($im, $fontBold, 20, $padL + 18, $y + 28, $cCompareStrong, (string) __('Comparison mode active'));
            self::drawCoverText($im, $font, 20, $padL + 18, $y + 52, $cCompareText, $payload['competitor']);
        }

        imageline($im, $padL, $footerLineY, $width - $padL, $footerLineY, $cLine);
        self::drawCoverText($im, $font, 18, $padL, $footerY, $cFooter, parse_url(self::BRAND_SITE, PHP_URL_HOST) ?: 'datagon.ru');
        $footerReport = $payload['cover_footer'] !== ''
            ? $payload['cover_footer']
            : (string) __('Text analyzer report');
        $center = $footerReport . ' · v' . $payload['version'];
        $centerW = self::coverTextWidth($center, $font, 18);
        self::drawCoverText($im, $font, 18, (int) (($width - $centerW) / 2), $footerY, $cFooterMid, $center);
        $pdfLabel = 'PDF';
        self::drawCoverText($im, $font, 18, $width - $padL - self::coverTextWidth($pdfLabel, $font, 18), $footerY, $cFooter, $pdfLabel);

        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        imagepng($im, $path);
        imagedestroy($im);
    }

    /**
     * @param resource $im
     */
    protected static function drawCoverLogoBand($im, int $x, int $y, int $targetH): int
    {
        $blockPath = self::coverLogoBlockPath($targetH);
        if (!is_file($blockPath)) {
            return $y;
        }
        $src = imagecreatefrompng($blockPath);
        if ($src === false) {
            return $y;
        }
        $sw = imagesx($src);
        $sh = imagesy($src);
        imagesavealpha($im, true);
        imagealphablending($im, true);
        imagealphablending($src, true);
        imagecopy($im, $src, $x, $y, 0, 0, $sw, $sh);
        imagedestroy($src);

        return $y + $sh;
    }

    protected static function drawChartLabel($im, ?string $font, int $size, int $x, int $y, int $color, string $text): void
    {
        if ($font !== null) {
            imagettftext($im, $size, 0, $x, $y, $color, $font, $text);
        } else {
            imagestring($im, 2, $x, $y - 10, $text, $color);
        }
    }

    /**
     * @param resource $im
     */
    protected static function copyRasterIcon($im, string $path, int $dx, int $dy, int $targetH): void
    {
        if (!is_file($path)) {
            return;
        }
        $src = imagecreatefrompng($path);
        if ($src === false) {
            return;
        }
        $sw = imagesx($src);
        $sh = imagesy($src);
        $tw = (int) round($sw * $targetH / max(1, $sh));
        imagesavealpha($im, true);
        imagesavealpha($src, true);
        imagealphablending($im, true);
        imagealphablending($src, true);
        imagecopyresampled($im, $src, $dx, $dy, 0, 0, $tw, $targetH, $sw, $sh);
        imagedestroy($src);
    }

    protected static function drawCoverText($im, ?string $font, int $size, int $x, int $y, int $color, string $text): int
    {
        if ($font !== null) {
            imagettftext($im, $size, 0, $x, $y, $color, $font, $text);
        } else {
            imagestring($im, 3, $x, $y - 12, $text, $color);
        }

        return $y;
    }

    protected static function coverTextWidth(string $text, ?string $font, int $size): int
    {
        if ($font === null) {
            return imagefontwidth(3) * mb_strlen($text);
        }
        $box = imagettfbbox($size, 0, $font, $text);

        return (int) abs($box[2] - $box[0]);
    }

    /**
     * Заголовок обложки: перенос по словам, при необходимости уменьшение кегля (длинные RU-строки).
     *
     * @return array{size: int, lines: array<int, string>}
     */
    protected static function resolveCoverTitleLayout(string $title, ?string $font, int $maxWidth): array
    {
        $size = 52;
        $maxLines = 3;
        do {
            $lines = self::wrapCoverText($title, $font, $size, $maxWidth);
            if ($lines !== [] && count($lines) <= $maxLines) {
                return ['size' => $size, 'lines' => $lines];
            }
            $size -= 6;
        } while ($size >= 34);

        $lines = self::wrapCoverText($title, $font, 34, $maxWidth);

        return ['size' => 34, 'lines' => $lines !== [] ? $lines : [$title]];
    }

    /**
     * @return array<int, string>
     */
    protected static function wrapCoverText(string $text, ?string $font, int $size, int $maxWidth): array
    {
        $words = preg_split('/\s+/u', trim($text)) ?: [];
        if ($words === []) {
            return [];
        }
        $lines = [];
        $line = '';
        foreach ($words as $word) {
            $candidate = $line === '' ? $word : $line . ' ' . $word;
            if (self::coverTextWidth($candidate, $font, $size) <= $maxWidth) {
                $line = $candidate;
                continue;
            }
            if ($line !== '') {
                $lines[] = $line;
            }
            $line = $word;
        }
        if ($line !== '') {
            $lines[] = $line;
        }

        return $lines;
    }

    protected static function savePng($im, string $path): void
    {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        imagepng($im, $path);
        imagedestroy($im);
    }
}
