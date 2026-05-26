<?php

namespace App\Services\Demo;

use App\Http\Controllers\MetaTagsController;
use App\Support\TextAnalyzerPdfBranding;
use Illuminate\Http\Request;

class MetaTagsDemoService
{
    public const MODULE = 'proverka-meta-tegov-online';

    /**
     * @return array<string, mixed>
     */
    public static function config(): array
    {
        return config('cabinet-meta-tags.demo', []);
    }

    /**
     * @param array{url?: string} $input
     * @return array{ok: true, url: string}|array{ok: false, status: int, error: string, message: string}
     */
    public static function validate(array $input): array
    {
        $url = trim((string) ($input['url'] ?? ''));
        if ($url === '') {
            return self::fail(422, 'validation', 'Укажите URL страницы для проверки');
        }
        if (!preg_match('#^https?://#i', $url)) {
            $url = 'https://' . $url;
        }
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return self::fail(422, 'validation', 'URL должен быть корректным (http или https)');
        }

        return ['ok' => true, 'url' => $url];
    }

    /**
     * @param array{url: string} $validated
     * @return array<string, mixed>
     */
    public static function check(array $validated): array
    {
        $cfg = self::config();
        $tags = $cfg['default_tags'] ?? ['title', 'description', 'h1', 'canonical', 'noindex', 'robots'];
        $length = $cfg['length'] ?? [];

        $controller = new MetaTagsController();
        $request = new Request([
            'url' => $validated['url'],
            'tags' => $tags,
            'length' => $length,
        ]);

        $raw = $controller->getMetaTags($request);

        return self::formatResult($raw);
    }

    /**
     * @param array<string, mixed> $result
     * @return array<string, mixed>
     */
    public static function buildResponse(array $result, int $remaining, string $guestId): array
    {
        $cfg = self::config();
        $maxRuns = (int) ($cfg['max_runs_per_day'] ?? 5);

        return [
            'demo' => true,
            'module' => self::MODULE,
            'remaining' => $remaining,
            'limits' => [
                'max_runs_per_day' => $maxRuns,
                'max_urls_per_run' => 1,
            ],
            'result' => $result,
            'upgrade' => [
                'register_url' => url('/register?module=' . self::MODULE . '&from=demo&guest=' . urlencode($guestId)),
                'login_url' => TextAnalyzerPdfBranding::loginUrl(),
            ],
        ];
    }

    /**
     * @param array<string, mixed> $raw
     * @return array<string, mixed>
     */
    private static function formatResult(array $raw): array
    {
        $labels = [
            'title' => 'Title',
            'description' => 'Description',
            'keywords' => 'Keywords',
            'canonical' => 'Canonical',
            'noindex' => 'Noindex',
            'robots' => 'Robots',
            'h1' => 'H1',
            'h2' => 'H2',
            'h3' => 'H3',
            'a' => 'Ссылки',
        ];

        $fields = [];
        $data = $raw['data'] ?? [];
        $errors = $raw['error'] ?? [];

        foreach ($data as $tag => $value) {
            $values = [];
            if (is_array($value)) {
                $values = array_values(array_filter($value, static function ($v) {
                    return $v !== false && trim((string) $v) !== '';
                }));
            } elseif ($value !== false && $value !== null && trim((string) $value) !== '') {
                $values = [(string) $value];
            }

            $messages = [];
            $status = 'ok';
            $badgeHtml = $errors['badge'][$tag] ?? [];
            if (is_array($badgeHtml)) {
                foreach ($badgeHtml as $html) {
                    $text = trim(strip_tags((string) $html));
                    if ($text === '' || mb_stripos($text, 'без проблем') !== false || mb_stripos($text, 'no problem') !== false) {
                        continue;
                    }
                    $status = 'issue';
                    $messages[] = $text;
                }
            }

            if ($status === 'ok' && count($values) === 0 && !in_array($tag, ['noindex', 'robots'], true)) {
                $status = 'missing';
                $messages[] = 'Тег не найден на странице';
            }

            $fields[] = [
                'tag' => $tag,
                'label' => $labels[$tag] ?? $tag,
                'values' => $values,
                'status' => $status,
                'messages' => $messages,
            ];
        }

        foreach ($errors['badge'] ?? [] as $key => $badgeHtml) {
            if (!is_string($key) || strpos($key, 'code:') !== 0) {
                continue;
            }
            $code = (int) substr($key, 5);
            if ($code >= 400) {
                $fields[] = [
                    'tag' => 'http',
                    'label' => 'HTTP',
                    'values' => [(string) $code],
                    'status' => 'issue',
                    'messages' => ['Страница вернула код ' . $code],
                ];
                break;
            }
        }

        $issuesCount = count(array_filter($fields, static function ($f) {
            return ($f['status'] ?? '') === 'issue' || ($f['status'] ?? '') === 'missing';
        }));

        $cfg = self::config();

        return [
            'requested_url' => (string) ($raw['title'] ?? ''),
            'final_url' => (string) ($raw['url'] ?? ''),
            'redirect' => (string) ($raw['redirect'] ?? ''),
            'fields' => $fields,
            'issues_count' => $issuesCount,
            'locked' => $cfg['locked_tags'] ?? ['h2', 'h3', 'a', 'keywords'],
        ];
    }

    /**
     * @return array{ok: false, status: int, error: string, message: string}
     */
    private static function fail(int $status, string $error, string $message): array
    {
        return [
            'ok' => false,
            'status' => $status,
            'error' => $error,
            'message' => $message,
        ];
    }
}
