<?php

namespace App\Services;

class TextLengthAnalysisService
{
  /**
   * @return array{
   *   chars_with_spaces: int,
   *   chars_no_spaces: int,
   *   words: int,
   *   lines: int,
   *   spaces: int
   * }
   */
  public static function analyzeSummary(string $text): array
  {
    $trimmed = trim($text);
    if ($trimmed === '') {
      return [
        'chars_with_spaces' => 0,
        'chars_no_spaces' => 0,
        'words' => 0,
        'lines' => 0,
        'spaces' => 0,
      ];
    }

    $charsWithSpaces = mb_strlen($text);
    $charsNoSpaces = mb_strlen(preg_replace('/\s/u', '', $text) ?? '');
    $words = count(preg_split('/\s+/u', $trimmed, -1, PREG_SPLIT_NO_EMPTY) ?: []);
    $lines = substr_count($text, "\n") + 1;
    preg_match_all('/\s/u', $text, $spaceMatches);
    $spaces = count($spaceMatches[0] ?? []);

    return [
      'chars_with_spaces' => $charsWithSpaces,
      'chars_no_spaces' => $charsNoSpaces,
      'words' => $words,
      'lines' => $lines,
      'spaces' => $spaces,
    ];
  }

  /**
   * @param array{title?: string, description?: string, h1?: string} $fields
   * @return array{
   *   title_chars: int|null,
   *   description_chars: int|null,
   *   h1_chars: int|null,
   *   title_ok: bool|null,
   *   description_ok: bool|null
   * }
   */
  public static function analyzeSeo(array $fields): array
  {
    $titleMax = (int) config('cabinet-text-length.seo.title_max', 60);
    $descriptionMax = (int) config('cabinet-text-length.seo.description_max', 160);

    $title = trim((string) ($fields['title'] ?? ''));
    $description = trim((string) ($fields['description'] ?? ''));
    $h1 = trim((string) ($fields['h1'] ?? ''));

    $titleChars = $title !== '' ? mb_strlen($title) : null;
    $descriptionChars = $description !== '' ? mb_strlen($description) : null;
    $h1Chars = $h1 !== '' ? mb_strlen($h1) : null;

    return [
      'title_chars' => $titleChars,
      'description_chars' => $descriptionChars,
      'h1_chars' => $h1Chars,
      'title_ok' => $titleChars !== null ? $titleChars <= $titleMax : null,
      'description_ok' => $descriptionChars !== null ? $descriptionChars <= $descriptionMax : null,
    ];
  }

  /**
   * @return array{sentences: int, paragraphs: int, reading_time_min: int}
   */
  public static function analyzeExtended(string $text): array
  {
    $trimmed = trim($text);
    if ($trimmed === '') {
      return [
        'sentences' => 0,
        'paragraphs' => 0,
        'reading_time_min' => 0,
      ];
    }

    $sentences = preg_split('/[.!?…]+/u', $trimmed, -1, PREG_SPLIT_NO_EMPTY);
    $sentenceCount = max(1, count(array_filter($sentences, static function ($s) {
      return trim($s) !== '';
    })));

    $paragraphs = preg_split('/\n\s*\n/u', $trimmed, -1, PREG_SPLIT_NO_EMPTY);
    $paragraphCount = max(1, count(array_filter($paragraphs, static function ($p) {
      return trim($p) !== '';
    })));

    $words = count(preg_split('/\s+/u', $trimmed, -1, PREG_SPLIT_NO_EMPTY) ?: []);
    $readingTimeMin = max(1, (int) ceil($words / 200));

    return [
      'sentences' => $sentenceCount,
      'paragraphs' => $paragraphCount,
      'reading_time_min' => $readingTimeMin,
    ];
  }

  /**
   * @param array{title?: string, description?: string, h1?: string} $fields
   * @return array{
   *   summary: array<string, int>,
   *   seo: array<string, int|bool|null>,
   *   extended: array<string, int>
   * }
   */
  public static function analyze(string $text, array $fields = []): array
  {
    return [
      'summary' => self::analyzeSummary($text),
      'seo' => self::analyzeSeo($fields),
      'extended' => self::analyzeExtended($text),
    ];
  }
}
