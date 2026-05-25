<?php

namespace App\Services;

use App\UniqueWords\ShinglesWord;
use App\UniqueWords\WordForms;

class UniqueWordsAnalysisService
{
    /**
     * @return array{
     *   rows: list<array{word: string, wordForms: string, count: int, keyPhrases: string}>,
     *   metrics: array{phrases: int, uniqueWords: int, totalOccurrences: int}
     * }
     */
    public static function analyze(string $content): array
    {
        $rows = [];
        $totalOccurrences = 0;

        if (trim($content) === '') {
            return [
                'rows' => [],
                'metrics' => [
                    'phrases' => 0,
                    'uniqueWords' => 0,
                    'totalOccurrences' => 0,
                ],
            ];
        }

        $morphy = new WordForms($content);
        $shingles = new ShinglesWord();
        $shingles->setText($content);

        $phraseCount = count(array_filter(
            preg_split('/[\r\n]+/', $content) ?: [],
            static function ($line) {
                return trim((string) $line) !== '';
            }
        ));

        foreach ($morphy->getOriginWords() as $word) {
            $forms = $morphy->getWordFormsInText($word);

            if (!$forms) {
                continue;
            }

            $keyPhrases = implode("\n", $shingles->getShinglesAroundWord($forms));
            $count = $morphy->getCount();
            $totalOccurrences += $count;

            $rows[] = [
                'word' => mb_strtolower($word),
                'wordForms' => mb_strtolower(implode(', ', $forms)),
                'count' => $count,
                'keyPhrases' => $keyPhrases,
            ];
        }

        usort($rows, static function ($a, $b) {
            return $b['count'] <=> $a['count'];
        });

        return [
            'rows' => $rows,
            'metrics' => [
                'phrases' => $phraseCount,
                'uniqueWords' => count($rows),
                'totalOccurrences' => $totalOccurrences,
            ],
        ];
    }
}
