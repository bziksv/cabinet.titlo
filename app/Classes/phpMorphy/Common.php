<?php

namespace App\Classes\phpMorphy;

use phpMorphy;
use phpMorphy_FilesBundle;

require_once 'src/common.php';

class Common
{
    /**
     * @var phpMorphy
     */
    private $en;

    /**
     * @var phpMorphy
     */
    private $ru;

    public function __construct($storage = 'file')
    {
        $this->ru = new phpMorphy(
            new phpMorphy_FilesBundle('rus'),
            array('storage' => $storage));

        $this->en = new phpMorphy(
            new phpMorphy_FilesBundle('eng'),
            array('storage' => $storage)
        );
    }

    /**
     * @param string $word to get base from
     * @return string
     */
    public function base(string $word): ?string
    {
        $forms = $this->baseForms($word);

        return $forms !== [] ? $forms[0] : null;
    }

    /**
     * @return string[]
     */
    public function baseForms(string $word): array
    {
        $sanitizedWord = $this->sanitize($word);
        $result = $this->getMorphy($sanitizedWord)->getBaseForm($sanitizedWord);

        if (!$result) {
            return [];
        }

        $forms = [];
        foreach ($result as $form) {
            $lemma = mb_strtolower(trim((string) $form), 'UTF-8');
            if ($lemma !== '') {
                $forms[$lemma] = true;
            }
        }

        return array_keys($forms);
    }

    /**
     * Выбирает лемму для каждого слова с учётом корпуса (разрешение омонимии phpMorphy).
     *
     * @param array<string, string[]> $candidates surface form => lemma candidates (порядок phpMorphy)
     * @return array<string, string> surface form => выбранная лемма
     */
    public function resolveRootsFromCandidates(array $candidates): array
    {
        if ($candidates === []) {
            return [];
        }

        $rootWeights = $this->buildRootWeights($candidates);
        $resolved = $this->resolveRootsPass($candidates, $rootWeights);

        foreach ($resolved as $root) {
            $rootWeights[$root] = ($rootWeights[$root] ?? 0) + 1;
        }

        return $this->resolveRootsPass($candidates, $rootWeights);
    }

    /**
     * @param array<string, string[]> $candidates
     * @return array<string, int>
     */
    private function buildRootWeights(array $candidates): array
    {
        $rootWeights = [];

        foreach ($candidates as $roots) {
            if (count($roots) === 1) {
                $root = $roots[0];
                $rootWeights[$root] = ($rootWeights[$root] ?? 0) + 2;
            }
        }

        foreach ($candidates as $word => $roots) {
            $wordKey = mb_strtolower(trim((string) $word), 'UTF-8');
            foreach ($roots as $root) {
                if ($root === $wordKey) {
                    $rootWeights[$root] = ($rootWeights[$root] ?? 0) + 3;
                }
            }
        }

        return $rootWeights;
    }

    /**
     * @param array<string, string[]> $candidates
     * @param array<string, int> $rootWeights
     * @return array<string, string>
     */
    private function resolveRootsPass(array $candidates, array $rootWeights): array
    {
        $resolved = [];

        foreach ($candidates as $word => $roots) {
            if ($roots === []) {
                $resolved[$word] = mb_strtolower(trim((string) $word), 'UTF-8');
                continue;
            }

            if (count($roots) === 1) {
                $resolved[$word] = $roots[0];
                continue;
            }

            $best = $roots[0];
            $bestScore = PHP_INT_MIN;
            foreach ($roots as $root) {
                $score = $rootWeights[$root] ?? 0;
                if ($score > $bestScore) {
                    $bestScore = $score;
                    $best = $root;
                }
            }

            $resolved[$word] = $best;
        }

        return $resolved;
    }

    /**
     * @param string $word to sanitize
     * @return string
     */
    private function sanitize(string $word): string
    {
        return mb_strtoupper(trim($word), 'UTF-8');
    }

    /**
     * @param string $word
     * @return phpMorphy
     */
    private function getMorphy(string $word): phpMorphy
    {
        return $this->isRussian($word) ? $this->ru : $this->en;
    }

    /**
     * @param string $word
     * @return bool
     */
    private function isRussian(string $word): bool
    {
        return (bool)preg_match('/[А-Я]/u', $word);
    }
}
