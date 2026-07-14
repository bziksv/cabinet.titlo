<?php

namespace App;

use App\Classes\Xml\SimplifiedXmlFacade;
use App\Jobs\Relevance\RemoveRelevanceProgress;
use App\Support\HybridRelevanceMetrics;
use App\Support\RelevancePhraseNgrams;
use App\Support\TfidfMetrics;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class Relevance
{
    public $countWords = 0;

    public $countSymbols = 0;

    public $countNotIgnoredSites = 0;

    public $countSitesForTextAvg = 0;

    public $countWordsForTextAvg = 0;

    public $countSymbolsForTextAvg = 0;

    private const MIN_SYMBOLS_FOR_TEXT_AVG = 500;

    public $mainPageIsRelevance = false;

    public $competitorsTextAndLinks = '';

    public $competitorsLinks = '';

    public $competitorsText = '';

    public $competitorsCloud = [];

    public $recommendations = [];

    public $ignoredWords = [];

    public $tfCompClouds = [];

    public $wordForms = [];

    public $mainPage = [];

    public $domains = [];

    public $pages = [];

    public $avg = [];

    public $competitorsTextAndLinksCloud;

    public $competitorsLinksCloud;

    public $competitorsTextCloud;

    public $countSymbolsInMyPage;

    public $countWordsInMyPage;

    public $avgCoveragePercent;

    public $maxWordLength;

    public $coverageInfo;

    public $phrases;

    public $request;

    public $phrase;

    public $params;

    public $sites;

    public $queue;

    public $userId;

    public $scanHash;

    public function __construct($request, $userId, bool $queue = false)
    {
        $this->queue = $queue;
        $this->request = $request;
        $this->userId = $userId;
        $this->scanHash = $request['hash'] ?? 'no hash';

        $this->maxWordLength = $request['separator'];
        $this->phrase = $request['phrase'] ?? '';
        $this->request['searchPassages'] = isset($this->request['searchPassages'])
            ? filter_var($this->request['searchPassages'], FILTER_VALIDATE_BOOLEAN)
            : false;

        $params = [
            'user_id' => $this->userId,
            'page_hash' => $this->queue ? null : $request['pageHash']
        ];

        $this->params = RelevanceAnalyseResults::firstOrNew($params);

        $this->params['main_page_link'] = isset($request['link']) ? $request['link'] : '';
        $this->params['sites'] = '';
        $this->params['html_main_page'] = '';
    }

    public function getMainPageHtml()
    {
        $html = TextAnalyzer::removeStylesAndScripts(TextAnalyzer::curlInitV2($this->params['main_page_link']));
        $this->setMainPage($html);
    }

    public function parseSites($xmlResponse = false, $searchPosition = false)
    {
        $mainUrl = parse_url($this->params['main_page_link']);
        $host = isset($mainUrl['host']) ? Str::lower($mainUrl['host']) : '';

        foreach ($this->domains as $key => $item) {
            $domain = Str::lower($item['item']);

            $result = TextAnalyzer::removeStylesAndScripts(TextAnalyzer::curlInitV2($domain));

            $this->sites[$domain]['danger'] = $result == '' || $result == null;
            $this->sites[$domain]['html'] = $result;
            $this->sites[$domain]['defaultHtml'] = $result;
            $this->sites[$domain]['site'] = $domain;
            $this->sites[$domain]['position'] = $item['position'];

            $compUrl = parse_url($domain);

            $this->sites[$domain]['equallyHost'] = isset($compUrl['host']) && $host === $compUrl['host'];

            if ($domain === Str::lower($this->params['main_page_link']) ||
                $domain === Str::lower($this->params['main_page_link']) . '/' ||
                $domain . '/' === Str::lower($this->params['main_page_link'])) {
                $this->mainPageIsRelevance = true;
                $this->sites[$domain]['mainPage'] = true;
                $this->sites[$domain]['inRelevance'] = $item['inRelevance'] ?? true;
                $this->sites[$domain]['ignored'] = false;
            } else {
                $this->sites[$domain]['mainPage'] = false;
                $this->sites[$domain]['ignored'] = $item['ignored'];
            }
        }

        if (!$this->mainPageIsRelevance) {
            if ($xmlResponse) {
                $position = array_search(Str::lower($this->params['main_page_link']), $xmlResponse);
                if ($position === false) {
                    $position = array_search(Str::lower($this->params['main_page_link'] . '/'), $xmlResponse);
                }
            } elseif ($searchPosition) {
                $position = SimplifiedXmlFacade::getPosition($this->request);
            } else {
                $position = count($this->domains) + 1;
            }

            $this->sites[$this->params['main_page_link']] = [
                'inRelevance' => false,
                'danger' => $this->mainPage['html'] === '',
                'ignored' => false,
                'mainPage' => true,
                'defaultHtml' => $this->mainPage['html'],
                'html' => $this->mainPage['html'],
                'site' => $this->params['main_page_link'],
                'position' => $position
            ];
        }
    }

    public function analysis($historyId = false)
    {
        $this->removeNoIndex();
        $this->getHiddenData();
        $this->separateLinksFromText();
        $this->removePartsOfSpeech();
        $this->removeListWords();
        $this->getTextFromCompetitors();
        $this->separateAllText();
        $this->searchWordForms();
        $this->processingOfGeneralInformation();
        RelevanceProgress::editProgress(82, $this->request);
        $this->prepareUnigramTable();
        $this->prepareAnalysedSitesTable();
        RelevanceProgress::editProgress(85, $this->request);
        $this->analyseRecommendations();
        $this->preparePhrasesTable();
        RelevanceProgress::editProgress(88, $this->request);
        $this->prepareClouds();
        $this->applyTableTfidfToUnigramTable();
        $this->applyHybridTfCloudsFromUnigramTable();
        $this->applyHybridTfCompCloudsFromUnigramTable();
        $this->saveHistory($historyId);

        UsersJobs::where('user_id', '=', $this->params['user_id'])->decrement('count_jobs');

        RemoveRelevanceProgress::dispatch($this->scanHash)
            ->onQueue('default')
            ->delay(now()->addSeconds(100));
    }

    /**
     * Удалить текст, который помечен <noindex>
     * @return void
     */
    public function removeNoIndex()
    {
        RelevanceProgress::editProgress(20, $this->request);

        if (isset($this->request['noIndex']) && $this->request['noIndex'] == 'false') {
            $this->mainPage['html'] = TextAnalyzer::removeNoindexText($this->mainPage['html']);
            foreach ($this->sites as $key => $page) {
                $this->sites[$key]['html'] = TextAnalyzer::removeNoindexText($page['html']);
            }
        }
    }

    public function separateAllText()
    {
        $this->competitorsLinks = $this->separateText($this->competitorsLinks);
        $this->competitorsText = $this->separateText($this->competitorsText);
        $this->mainPage['html'] = $this->separateText($this->mainPage['html']);
        $this->mainPage['linkText'] = $this->separateText($this->mainPage['linkText']);
        $this->mainPage['hiddenText'] = $this->separateText($this->mainPage['hiddenText']);
        $this->competitorsTextAndLinks = ' ' . $this->competitorsLinks . ' ' . $this->competitorsText . ' ';
    }

    public function separateLinksFromText()
    {
        foreach ($this->sites as $key => $page)
        {
            $this->sites[$key]['linkText'] = TextAnalyzer::getLinkText($this->sites[$key]['html']);
            $this->sites[$key]['html'] = TextAnalyzer::deleteEverythingExceptCharacters(TextAnalyzer::clearHTMLFromLinks($this->sites[$key]['html']));

            if ($this->request['searchPassages']) {

                $this->sites[$key]['passages'] = Relevance::searchPassages($this->sites[$key]['defaultHtml']);

                $passagesArray = explode(' ', $this->sites[$key]['passages']);
                $html = ' ' . $this->sites[$key]['html'] . ' ';
                foreach ($passagesArray as $item) {
                    $search = " $item ";
                    $pos = strpos($html, $search);
                    if ($pos !== false) {
                        $html = substr_replace($html, " ", $pos, strlen($search));
                    }
                }

                $this->sites[$key]['html'] = trim($html);

            } else {
                $this->sites[$key]['passages'] = '';
            }

            if ($this->sites[$key]['mainPage']) {
                $this->mainPage['linkText'] = $this->sites[$key]['linkText'];
                $this->mainPage['html'] = $this->sites[$key]['html'];
                $this->mainPage['passages'] = $this->sites[$key]['passages'];
            }
        }
    }

    public static function searchPassages($html): string
    {
        $passages = '';
        preg_match_all('(<li.*?>(.*?)</li>)', $html, $li, PREG_SET_ORDER);

        foreach ($li as $item) {
            $ul = str_replace('>', '> ', $item[1]);
            $ul = TextAnalyzer::clearHTMLFromLinks($ul);

            $text = trim(strip_tags($ul));
            $text = preg_replace('| +|', ' ', $text);
            $text = trim(TextAnalyzer::deleteEverythingExceptCharacters($text));
            if (mb_strlen($text) < 200 && $text != "") {
                $passages .= ' ' . $text;
            }
        }

        return trim($passages);
    }

    public function getHiddenData()
    {
        if (isset($this->request['hiddenText']) && $this->request['hiddenText'] == 'true') {
            $this->mainPage['hiddenText'] = Relevance::getHiddenText($this->mainPage['html']);
            foreach ($this->sites as $key => $page) {
                $this->sites[$key]['hiddenText'] = Relevance::getHiddenText($this->sites[$key]['html']);
            }
        } else {
            $this->mainPage['hiddenText'] = '';
            foreach ($this->sites as $key => $page) {
                $this->sites[$key]['hiddenText'] = '';
            }
        }
    }

    public function getTextFromCompetitors()
    {
        RelevanceProgress::editProgress(40, $this->request);
        foreach ($this->sites as $key => $page) {
            if (!$this->sites[$key]['ignored']) {
                $this->competitorsLinks .= ' ' . $this->sites[$key]['linkText'] . ' ';
                $this->competitorsText .= ' ' . $this->sites[$key]['hiddenText'] . ' ' . $this->sites[$key]['html'] . ' ';
            }

            $this->sites[$key]['coverage'] = 0;
            $this->sites[$key]['coverageTf'] = 0;
        }
    }

    public function calculateCoveragePoints()
    {
        $totalTf = 0;
        foreach ($this->wordForms as $wordForm) {
            $totalTf += $wordForm['total']['tf'];
        }

        foreach ($this->sites as $pageKey => $page) {
            $object = $page['html'] . ' ' . $page['linkText'] . ' ' . $page['hiddenText'];
            $coverage = $this->calculateCoverage($object);

            $this->sites[$pageKey]['coverage'] = round($coverage['text'] / 10, 2);
            $this->sites[$pageKey]['coverageTf'] = round($coverage['tf'] / ($totalTf / 100), 2);
        }
    }

    public function calculateCoverage($object): array
    {
        $text = 0;
        $tf = 0;
        foreach ($this->wordForms as $wordForm) {
            foreach ($wordForm as $word => $form) {
                if ($word != 'total') {
                    if (strpos($object, " $word ") !== false) {
                        $text++;
                        break;
                    }
                }
            }
        }

        foreach ($this->wordForms as $wordForm) {
            foreach ($wordForm as $word => $form) {
                if ($word != 'total') {
                    if (strpos($object, " $word ") !== false) {
                        $tf += $form['tf'];
                    }
                }
            }
        }

        return [
            'text' => $text,
            'tf' => $tf,
        ];
    }

    public function calculateWidthPoints()
    {
        $this->avgCoveragePercent = $iterator = 0;
        foreach ($this->sites as $site) {
            if (!$site['ignored']) {
                if ($iterator == 10) {
                    break;
                }
                $this->avgCoveragePercent += $site['coverage'];
                $iterator++;
            }
        }

        $this->avgCoveragePercent /= 10;
        foreach ($this->sites as $key => $site) {
            $points = $this->sites[$key]['coverage'] / ($this->avgCoveragePercent / 100);
            $points = min($points, 100);
            $this->sites[$key]['width'] = round($points, 2);
        }
    }

    public function calculateTotalPoints()
    {
        foreach ($this->sites as $key => $site) {
            $points = $site['coverage'] + $site['coverageTf'] + $site['density']['densityMainPercent'];
            $this->sites[$key]['mainPoints'] = min(round(($points / 3) * 2, 2), 100);
        }
    }

    public function calculateTextInfo()
    {
        foreach ($this->sites as $key => $site) {
            $text = trim(implode(' ', array_filter([
                $site['html'] ?? '',
                $site['hiddenText'] ?? '',
                strip_tags($site['linkText'] ?? ''),
            ])));
            $countSymbols = Str::length($text);
            $countWords = count(preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY));

            $this->sites[$key]['countWords'] = max($countWords, 0);
            $this->sites[$key]['countSymbols'] = max($countSymbols, 0);

            if ($this->sites[$key]['mainPage']) {
                $this->countSymbolsInMyPage = $countSymbols;
                $this->countWordsInMyPage = $countWords;
            } elseif (!$site['ignored']) {
                $this->countNotIgnoredSites++;
                $this->countSymbols += $countSymbols;
                $this->countWords += $countWords;

                if ($countSymbols >= self::MIN_SYMBOLS_FOR_TEXT_AVG) {
                    $this->countSitesForTextAvg++;
                    $this->countWordsForTextAvg += $countWords;
                    $this->countSymbolsForTextAvg += $countSymbols;
                } else {
                    $this->sites[$key]['excludedFromTextAvg'] = true;
                }
            }
        }
    }

    public function analyseRecommendations()
    {
        foreach ($this->wordForms as $wordForm) {
            if (empty($wordForm['total']) || !is_array($wordForm['total'])) {
                continue;
            }

            foreach ($wordForm as $word => $form) {
                if ($word === 'total') {
                    continue;
                }

                if ($wordForm['total']['avgInTotalCompetitors'] >= 10) {
                    $recommendationMin = ceil($wordForm['total']['avgInTotalCompetitors'] * 0.9);
                    $recommendationMax = ceil($wordForm['total']['avgInTotalCompetitors'] * 1.1);
                } else if ($wordForm['total']['avgInTotalCompetitors'] >= 2) {
                    $recommendationMin = $wordForm['total']['avgInTotalCompetitors'] - 1;
                    $recommendationMax = $wordForm['total']['avgInTotalCompetitors'] + 1;
                } else {
                    $recommendationMin = 1;
                    $recommendationMax = 2;
                }

                if ($wordForm['total']['totalRepeatMainPage'] < $recommendationMin) {
                    $this->recommendations[$word] = [
                        'onPage' => $wordForm['total']['totalRepeatMainPage'],
                        'tf' => round($wordForm['total']['tf'], 5),
                        'avg' => $wordForm['total']['avgInTotalCompetitors'],
                        'diapason' => $recommendationMin . ' - ' . $recommendationMax,
                        'spam' => 0,
                        'add' => ($recommendationMin - $wordForm['total']['totalRepeatMainPage']) . ' - ' . ($recommendationMax - $wordForm['total']['totalRepeatMainPage']),
                        'remove' => 0,
                    ];
                    break;
                } else if ($wordForm['total']['totalRepeatMainPage'] > $recommendationMax) {
                    $this->recommendations[$word] = [
                        'onPage' => $wordForm['total']['totalRepeatMainPage'],
                        'tf' => round($wordForm['total']['tf'], 5),
                        'avg' => $wordForm['total']['avgInTotalCompetitors'],
                        'diapason' => $recommendationMin . ' - ' . $recommendationMax,
                        'spam' => round(($wordForm['total']['totalRepeatMainPage'] - $recommendationMax) / ($recommendationMax / 100)) . '%',
                        'add' => 0,
                        'remove' => ($wordForm['total']['totalRepeatMainPage'] - $recommendationMax) . ' - ' . ($wordForm['total']['totalRepeatMainPage'] - $recommendationMin),
                    ];
                    break;
                }
            }
        }
    }

    public function prepareAnalysedSitesTable()
    {
        $this->calculateDensity();
        $this->calculateCoveragePoints();
        $this->calculateWidthPoints();
        $this->calculateTotalPoints();
        $this->calculateTextInfo();
        $this->calculateAvg();
    }

    public static function getHiddenText($html)
    {
        $hiddenText = '';
        $regex = ["<.*?title=\"(.*?)\".*>", "<.*?alt=\"(.*?)\".*>", "<.*?data-text=\"(.*?)\".*>"];
        foreach ($regex as $reg) {
            preg_match_all($reg, $html, $matches, PREG_SET_ORDER);
            foreach ($matches as $match) {
                if ($match[1] != "") {
                    $hiddenText .= $match[1] . ' ';
                }
            }
        }

        return TextAnalyzer::deleteEverythingExceptCharacters($hiddenText);
    }

    public function removePartsOfSpeech()
    {
        if ($this->request['conjunctionsPrepositionsPronouns'] == 'false') {
            $this->mainPage['html'] = TextAnalyzer::removeConjunctionsPrepositionsPronouns($this->mainPage['html']);
            $this->mainPage['linkText'] = TextAnalyzer::removeConjunctionsPrepositionsPronouns($this->mainPage['linkText']);
            $this->mainPage['hiddenText'] = TextAnalyzer::removeConjunctionsPrepositionsPronouns($this->mainPage['hiddenText']);
            foreach ($this->sites as $key => $page) {
                $this->sites[$key]['html'] = TextAnalyzer::removeConjunctionsPrepositionsPronouns($this->sites[$key]['html']);
                $this->sites[$key]['linkText'] = TextAnalyzer::removeConjunctionsPrepositionsPronouns($this->sites[$key]['linkText']);
                $this->sites[$key]['hiddenText'] = TextAnalyzer::removeConjunctionsPrepositionsPronouns($this->sites[$key]['hiddenText']);
            }
        }
    }

    public function removeListWords()
    {
        if (!self::shouldApplyExcludedWordsList($this->request)) {
            return;
        }

        $listWords = (string) $this->request['listWords'];
        $this->ignoredWords = TextAnalyzer::parseExcludeWordList($listWords);

        foreach ($this->sites as $key => $page) {
            $this->sites[$key]['html'] = TextAnalyzer::removeWords($listWords, $this->sites[$key]['html']);
            $this->sites[$key]['linkText'] = TextAnalyzer::removeWords($listWords, $this->sites[$key]['linkText']);
            $this->sites[$key]['hiddenText'] = TextAnalyzer::removeWords($listWords, $this->sites[$key]['hiddenText']);

            if ($this->sites[$key]['mainPage']) {
                $this->mainPage['html'] = $this->sites[$key]['html'];
                $this->mainPage['linkText'] = $this->sites[$key]['linkText'];
                $this->mainPage['hiddenText'] = $this->sites[$key]['hiddenText'];
            }
        }
    }

    public static function shouldApplyExcludedWordsList($request): bool
    {
        if (!is_array($request)) {
            return false;
        }

        return filter_var($request['switchMyListWords'] ?? false, FILTER_VALIDATE_BOOLEAN)
            && trim((string) ($request['listWords'] ?? '')) !== '';
    }

    public static function excludedWordsLookup($request): array
    {
        if (!self::shouldApplyExcludedWordsList($request)) {
            return [];
        }

        return array_flip(TextAnalyzer::parseExcludeWordList((string) $request['listWords']));
    }

    public static function isExcludedLemma(string $word, array $excludeLookup): bool
    {
        if (!$excludeLookup) {
            return false;
        }

        $lemma = mb_strtolower(trim($word), 'UTF-8');

        return $lemma !== '' && isset($excludeLookup[$lemma]);
    }

    public static function filterCloudPayload($cloud, array $excludeLookup)
    {
        if (!is_array($cloud) || !$excludeLookup) {
            return $cloud;
        }

        $filtered = [];
        foreach ($cloud as $item) {
            if (!is_array($item) || !isset($item['text'])) {
                continue;
            }
            if (self::isExcludedLemma((string) $item['text'], $excludeLookup)) {
                continue;
            }
            $filtered[] = $item;
        }
        $filtered['count'] = count($filtered);

        return $filtered;
    }

    public static function filterStoredDetailsExcludedWords(array &$data, array $request): void
    {
        $excludeLookup = self::excludedWordsLookup($request);
        if (!$excludeLookup) {
            return;
        }

        foreach (['clouds_competitors', 'clouds_main_page'] as $section) {
            if (empty($data[$section]) || !is_array($data[$section])) {
                continue;
            }
            foreach ($data[$section] as $key => $cloud) {
                $data[$section][$key] = self::filterCloudPayload($cloud, $excludeLookup);
            }
        }

        if (!empty($data['tf_comp_clouds']) && is_array($data['tf_comp_clouds'])) {
            foreach ($data['tf_comp_clouds'] as $site => $cloud) {
                $data['tf_comp_clouds'][$site] = self::filterCloudPayload($cloud, $excludeLookup);
            }
        }

        if (!empty($data['unigram_table']) && is_array($data['unigram_table'])) {
            foreach ($data['unigram_table'] as $root => $wordForm) {
                if (!is_array($wordForm)) {
                    unset($data['unigram_table'][$root]);
                    continue;
                }

                if (self::isExcludedLemma((string) $root, $excludeLookup)) {
                    unset($data['unigram_table'][$root]);
                    continue;
                }

                foreach ($wordForm as $word => $item) {
                    if ($word === 'total') {
                        continue;
                    }
                    if (self::isExcludedLemma((string) $word, $excludeLookup)) {
                        unset($data['unigram_table'][$root][$word]);
                    }
                }

                if (!array_diff(array_keys($data['unigram_table'][$root]), ['total'])) {
                    unset($data['unigram_table'][$root]);
                }
            }
        }

        if (!empty($data['phrases']) && is_array($data['phrases'])) {
            $data['phrases'] = TextAnalyzer::filterExcludedFromPhrases(
                $data['phrases'],
                (string) ($request['listWords'] ?? '')
            );
        }
    }

    private function applyExcludedWordsToPreparedClouds(): void
    {
        $excludeLookup = self::excludedWordsLookup($this->request);
        if (!$excludeLookup) {
            return;
        }

        foreach (['totalTf', 'textTf', 'linkTf', 'text', 'links', 'textWithLinks'] as $key) {
            if (isset($this->mainPage[$key])) {
                $this->mainPage[$key] = self::filterCloudPayload($this->mainPage[$key], $excludeLookup);
            }
        }

        foreach (['totalTf', 'textTf', 'linkTf', 'text', 'links', 'textAndLinks'] as $key) {
            if (isset($this->competitorsCloud[$key])) {
                $this->competitorsCloud[$key] = self::filterCloudPayload($this->competitorsCloud[$key], $excludeLookup);
            }
        }

        foreach ($this->tfCompClouds as $site => $cloud) {
            $this->tfCompClouds[$site] = self::filterCloudPayload($cloud, $excludeLookup);
        }
    }

    public static function mbStrReplace($search, $replace, $string): string
    {
        $charset = mb_detect_encoding($string);

        $unicodeString = iconv($charset, "UTF-8", $string);

        return preg_replace('| +|', ' ', str_replace($search, $replace, $unicodeString));
    }

    /**
     * Сливает группы словоформ по выбранной лемме (на случай расхождения bucket-ключей).
     *
     * @param array<string, array<string, int>> $wordWorms
     * @param array<string, string> $resolvedRoots
     * @return array<string, array<string, int>>
     */
    private static function canonicalizeWordFormGroups(array $wordWorms, array $resolvedRoots): array
    {
        $merged = [];

        foreach ($wordWorms as $root => $wordWorm) {
            if (!is_array($wordWorm)) {
                continue;
            }

            foreach ($wordWorm as $surface => $count) {
                if (!is_string($surface) || $surface === 'total') {
                    continue;
                }

                $canonical = $resolvedRoots[$surface] ?? (string) $root;
                $merged[$canonical][$surface] = ($merged[$canonical][$surface] ?? 0) + (int) $count;
            }
        }

        return $merged;
    }

    public function searchWordForms()
    {
        $m = new Morphy();
        $wordWorms = [];

        $array = explode(' ', $this->competitorsTextAndLinks);
        $array = array_count_values($array);
        arsort($array);
        $excludeLookup = self::excludedWordsLookup($this->request);

        $candidates = [];
        foreach (array_keys($array) as $key) {
            if (self::isExcludedLemma((string) $key, $excludeLookup)) {
                continue;
            }

            $forms = $m->baseForms($key);
            $candidates[$key] = $forms !== [] ? $forms : [mb_strtolower((string) $key, 'UTF-8')];
        }

        $resolvedRoots = $m->resolveRootsFromCandidates($candidates);

        foreach ($array as $key => $item) {
            if (self::isExcludedLemma((string) $key, $excludeLookup)) {
                continue;
            }
            if (!in_array($key, $this->ignoredWords)) {
                $this->ignoredWords[] = $key;

                $root = $resolvedRoots[$key] ?? mb_strtolower((string) $key, 'UTF-8');

                $wordWorms[$root][$key] = $item;

                if (count($wordWorms) >= 3500) {
                    break;
                }
            }
        }

        $this->wordForms = self::canonicalizeWordFormGroups($wordWorms, $resolvedRoots);

        uasort($this->wordForms, function ($l, $r) {
            $first = array_sum($r);
            $second = array_sum($l);

            if ($first == $second) return 0;
            return ($first < $second) ? -1 : 1;
        });

        $this->wordForms = array_slice($this->wordForms, 0, 1000);
    }

    public function processingOfGeneralInformation()
    {
        RelevanceProgress::editProgress(80, $this->request);
        $countSites = 0;
        $topLimit = max(1, (int) ($this->request['count'] ?? 20));
        $siteWordMaps = [];

        foreach ($this->sites as $key => $site) {
            if (!$site['ignored']) {
                $countSites++;
            }

            $siteWordMaps[$key] = [
                'html' => self::wordFrequencyMap($site['html'] ?? ''),
                'hiddenText' => self::wordFrequencyMap($site['hiddenText'] ?? ''),
                'linkText' => self::wordFrequencyMap($site['linkText'] ?? ''),
                'passages' => self::wordFrequencyMap($site['passages'] ?? ''),
            ];
        }

        $countSites = max(1, $countSites);
        $documentCount = $this->competitorDocumentCount();

        $myText = $this->mainPage['html'] . ' ' . $this->mainPage['hiddenText'];
        $myText = explode(" ", $myText);
        $myText = array_count_values($myText);

        $myLink = strip_tags($this->mainPage['linkText']);
        $myLink = explode(" ", $myLink);
        $myLink = array_count_values($myLink);

        $myPassages = strip_tags($this->mainPage['passages']);
        $myPassages = explode(" ", $myPassages);
        $myPassages = array_count_values($myPassages);

        $wordCount = count(explode(' ', $this->competitorsTextAndLinks));

        foreach ($this->wordForms as $root => $wordForm) {
            foreach ($wordForm as $word => $item) {
                $reSpam = $numberTextOccurrences = $numberLinkOccurrences = $numberOccurrences = $numberPassageOccurrences = 0;
                $occurrences = [];
                $inc = 1;

                foreach ($this->sites as $key => $page) {
                    if (!$page['ignored'] && $topLimit >= $inc) {
                        $bags = $siteWordMaps[$key];
                        $htmlCount = (int) ($bags['html'][$word] ?? 0);
                        $hiddenTextCount = (int) ($bags['hiddenText'][$word] ?? 0);
                        $linkTextCount = (int) ($bags['linkText'][$word] ?? 0);
                        $passagesCount = (int) ($bags['passages'][$word] ?? 0);

                        if ($htmlCount > 0) {
                            $numberTextOccurrences += $htmlCount;
                        }

                        if ($hiddenTextCount > 0) {
                            $numberTextOccurrences += $hiddenTextCount;
                        }

                        if ($linkTextCount > 0) {
                            $numberLinkOccurrences += $linkTextCount;
                        }

                        if ($passagesCount > 0) {
                            $numberPassageOccurrences += $passagesCount;
                        }

                        if ($htmlCount > 0 || $hiddenTextCount > 0 || $linkTextCount > 0) {
                            $countRepeat = $htmlCount + $hiddenTextCount + $linkTextCount;
                            $numberOccurrences++;
                            $occurrences[$key] = $countRepeat;
                            if ($reSpam < $countRepeat) {
                                $reSpam = $countRepeat;
                            }
                        }

                        $inc += 1;
                    }
                }

                arsort($occurrences);
                $repeatInTextMainPage = $myText[$word] ?? 0;
                $repeatLinkInMainPage = $myLink[$word] ?? 0;
                $repeatInPassagesMainPage = $myPassages[$word] ?? 0;

                $tf = TfidfMetrics::termFrequency((float) $item, (float) $wordCount);
                $idf = TfidfMetrics::inverseDocumentFrequency($documentCount, max(1, $numberOccurrences));
                $score = TfidfMetrics::score($tf, $idf);

                $this->wordForms[$root][$word] = [
                    'tf' => $tf,
                    'idf' => $idf,
                    'score' => $score,
                    'countTopText' => $numberTextOccurrences,
                    'countTopLink' => $numberLinkOccurrences,
                    'numberOccurrences' => $numberOccurrences,
                    'reSpam' => $reSpam,
                    'avgInTotalCompetitors' => (int)ceil(($numberLinkOccurrences + $numberTextOccurrences) / $countSites),
                    'avgInLink' => (int)ceil($numberLinkOccurrences / $countSites),
                    'avgInText' => (int)ceil($numberTextOccurrences / $countSites),
                    'avgInPassages' => (int)ceil($numberPassageOccurrences / $countSites),
                    'repeatInLinkMainPage' => $repeatLinkInMainPage,
                    'repeatInTextMainPage' => $repeatInTextMainPage,
                    'repeatInPassagesMainPage' => $repeatInPassagesMainPage,
                    'totalRepeatMainPage' => $repeatLinkInMainPage + $repeatInTextMainPage + $repeatInPassagesMainPage,
                    'occurrences' => $occurrences,
                ];
            }
        }
    }

    /**
     * Частоты токенов (как substr_count по пробелам, но один раз на зону).
     *
     * @return array<string, int>
     */
    public static function wordFrequencyMap(string $text): array
    {
        $text = trim($text);
        if ($text === '') {
            return [];
        }

        $parts = preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY);
        if (!is_array($parts) || $parts === []) {
            return [];
        }

        return array_count_values($parts);
    }

    public function prepareUnigramTable()
    {
        $this->coverageInfo['sum'] = 0;

        foreach ($this->wordForms as $key => $wordForm) {
            $tf = $reSpam = $repeatInPassages = $repeatInText = $repeatInLink = $avgInText = $avgInPassages = 0;
            $avgInLink = $avgInTotalCompetitors = $totalRepeatMainPage = $countTopText = $countTopLink = 0;
            $occurrences = [];
            $danger = false;

            foreach ($wordForm as $wordKey => $word) {
                if ($wordKey === 'total' || !is_array($word)) {
                    continue;
                }

                $danger = $danger || $word['repeatInTextMainPage'] == 0 || $word['repeatInLinkMainPage'] == 0;

                $tf += $word['tf'];
                $countTopText += (int) ($word['countTopText'] ?? 0);
                $countTopLink += (int) ($word['countTopLink'] ?? 0);

                $avgInTotalCompetitors += $word['avgInTotalCompetitors'];
                $totalRepeatMainPage += $word['totalRepeatMainPage'];

                $avgInText += $word['avgInText'];
                $avgInLink += $word['avgInLink'];

                $repeatInText += $word['repeatInTextMainPage'];
                $repeatInLink += $word['repeatInLinkMainPage'];

                $avgInPassages += $word['avgInPassages'];
                $repeatInPassages += $word['repeatInPassagesMainPage'];

                if ($reSpam < $word['reSpam']) {
                    $reSpam = $word['reSpam'];
                }

                foreach ($word['occurrences'] as $key2 => $value) {
                    if (key_exists($key2, $occurrences)) {
                        $occurrences[$key2] += $value;
                    } else {
                        $occurrences[$key2] = $value;
                    }
                }
            }
            arsort($occurrences);

            $documentCount = $this->competitorDocumentCount();
            $documentFrequency = max(1, count($occurrences));
            $idf = TfidfMetrics::inverseDocumentFrequency($documentCount, $documentFrequency);
            $corpusWords = max(1, HybridRelevanceMetrics::countWordsInText(trim($this->competitorsTextAndLinks)));
            $score = HybridRelevanceMetrics::hybridTfidfTop(
                (float) array_sum($occurrences),
                (float) $corpusWords,
                $documentFrequency,
                $documentCount
            );

            $this->wordForms[$key]['total'] = [
                'tf' => $tf,
                'idf' => $idf,
                'score' => $score,
                'countTopText' => $countTopText,
                'countTopLink' => $countTopLink,
                'avgInTotalCompetitors' => (int)ceil($avgInTotalCompetitors),
                'avgInText' => (int)ceil($avgInText),
                'avgInLink' => (int)ceil($avgInLink),
                'repeatInTextMainPage' => $repeatInText,
                'repeatInLinkMainPage' => $repeatInLink,
                'totalRepeatMainPage' => $totalRepeatMainPage,
                'numberOccurrences' => count($occurrences),
                'reSpam' => $reSpam,
                'danger' => $danger,
                'occurrences' => $occurrences,
            ];

            if ($this->request['searchPassages']) {
                $this->wordForms[$key]['total']['avgInPassages'] = $avgInPassages;
                $this->wordForms[$key]['total']['repeatInPassagesMainPage'] = $repeatInPassages;
            }
        }

        $collection = collect($this->wordForms);

        $this->wordForms = $collection->sortByDesc(function ($wordForm) {
            return $wordForm['total']['score'] ?? 0;
        })->toArray();

        $this->filterWordFormsExcluded();
    }

    private function filterWordFormsExcluded(): void
    {
        $excludeLookup = self::excludedWordsLookup($this->request);
        if (!$excludeLookup) {
            return;
        }

        foreach ($this->wordForms as $root => $wordForm) {
            if (!is_array($wordForm)) {
                unset($this->wordForms[$root]);
                continue;
            }

            if (self::isExcludedLemma((string) $root, $excludeLookup)) {
                unset($this->wordForms[$root]);
                continue;
            }

            foreach ($wordForm as $word => $item) {
                if ($word === 'total') {
                    continue;
                }
                if (self::isExcludedLemma((string) $word, $excludeLookup)) {
                    unset($this->wordForms[$root][$word]);
                }
            }

            if (!array_diff(array_keys($this->wordForms[$root]), ['total'])) {
                unset($this->wordForms[$root]);
            }
        }
    }

  /**
   * @return array<string, int> lemma => df (число сайтов конкурентов с термином)
   */
    private function buildDocumentFrequencyMap(): array
    {
        $map = [];

        foreach ($this->wordForms as $wordForm) {
            foreach ($wordForm as $word => $data) {
                if ($word === 'total' || !is_array($data)) {
                    continue;
                }

                $df = (int) ($data['numberOccurrences'] ?? 0);
                if ($df < 1) {
                    continue;
                }

                if (!isset($map[$word]) || $df > $map[$word]) {
                    $map[$word] = $df;
                }
            }
        }

        return $map;
    }

    public function prepareClouds()
    {
        RelevanceProgress::editProgress(90, $this->request);
        $documentFrequencyMap = $this->buildDocumentFrequencyMap();
        $mainPage = Relevance::concatenation([
            $this->mainPage['html'],
            $this->mainPage['hiddenText'],
            $this->mainPage['linkText']
        ]);
        $textMainPage = Relevance::concatenation([
            $this->mainPage['html'],
            $this->mainPage['hiddenText']
        ]);
        $this->mainPage['totalTf'] = $this->prepareTfCloud($mainPage, $documentFrequencyMap);
        $this->mainPage['textTf'] = $this->prepareTfCloud($textMainPage, $documentFrequencyMap);
        $this->mainPage['linkTf'] = $this->prepareTfCloud($this->mainPage['linkText'], $documentFrequencyMap);

        $this->mainPage['textWithLinks'] = TextAnalyzer::prepareCloud($mainPage);
        $this->mainPage['text'] = TextAnalyzer::prepareCloud($textMainPage);
        $this->mainPage['links'] = TextAnalyzer::prepareCloud($this->mainPage['linkText']);

        $this->competitorsCloud['totalTf'] = $this->prepareTfCloud($this->competitorsTextAndLinks, $documentFrequencyMap);
        $this->competitorsCloud['textTf'] = $this->prepareTfCloud($this->competitorsText, $documentFrequencyMap);
        $this->competitorsCloud['linkTf'] = $this->prepareTfCloud($this->competitorsLinks, $documentFrequencyMap);

        $this->competitorsTextAndLinksCloud = TextAnalyzer::prepareCloud($this->competitorsTextAndLinks);
        $this->competitorsTextCloud = TextAnalyzer::prepareCloud($this->competitorsText);
        $this->competitorsLinksCloud = TextAnalyzer::prepareCloud($this->competitorsLinks);

        foreach ($this->sites as $key => $page) {
            $this->tfCompClouds[$key] = $this->prepareTfCloud(
                $this->separateText($page['html'] . ' ' . $page['linkText']),
                $documentFrequencyMap
            );
        }

        $this->applyExcludedWordsToPreparedClouds();
    }

    /**
     * TF-IDF облака — те же гибридные метрики, что в колонках TLP (не prepareTfCloud).
     */
    private function applyHybridTfCloudsFromUnigramTable(): void
    {
        self::applyHybridTfCloudsFromUnigramToPrepared(
            $this->wordForms,
            $this->competitorsCloud,
            $this->mainPage
        );
    }

    /**
     * @param array<string, mixed> $unigramTable
     * @param array<string, mixed> $competitorsCloud
     * @param array<string, mixed> $mainPageCloud
     */
    public static function applyHybridTfCloudsFromUnigramToPrepared(
        array $unigramTable,
        array &$competitorsCloud,
        array &$mainPageCloud
    ): void {
        $competitorsCloud['totalTf'] = self::buildHybridTfCloudFromUnigram($unigramTable, 'tfidfTop');
        $competitorsCloud['textTf'] = self::buildHybridTfCloudFromUnigram($unigramTable, 'tfidfTopText');
        $competitorsCloud['linkTf'] = self::buildHybridTfCloudFromUnigram($unigramTable, 'tfidfTopLink');
        $mainPageCloud['totalTf'] = self::buildHybridTfCloudFromUnigram($unigramTable, 'tfidfSite');
        $mainPageCloud['textTf'] = self::buildHybridTfCloudFromUnigram($unigramTable, 'tfidfSiteText');
        $mainPageCloud['linkTf'] = self::buildHybridTfCloudFromUnigram($unigramTable, 'tfidfSiteLink');
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function applyHybridTfCloudsFromUnigram(array &$data): void
    {
        if (empty($data['unigram_table']) || !is_array($data['unigram_table'])) {
            return;
        }

        if (!isset($data['clouds_competitors']) || !is_array($data['clouds_competitors'])) {
            $data['clouds_competitors'] = [];
        }
        if (!isset($data['clouds_main_page']) || !is_array($data['clouds_main_page'])) {
            $data['clouds_main_page'] = [];
        }

        self::applyHybridTfCloudsFromUnigramToPrepared(
            $data['unigram_table'],
            $data['clouds_competitors'],
            $data['clouds_main_page']
        );
    }

    /**
     * Облака по каждому конкуренту — гибридный TF-IDF документа (как tfidfSite у нашей страницы).
     *
     * @param array<string, array<string, mixed>> $sites
     * @param array<string, mixed> $unigramTable
     * @param array<string, int|float> $corpusZones
     */
    public static function buildHybridTfCompCloudsFromSitesAndUnigram(
        array $sites,
        array $unigramTable,
        array $corpusZones,
        int $limit = 200
    ): array {
        $result = [];
        $documentCount = max(1, (int) ($corpusZones['documentCount'] ?? $corpusZones['competitorSiteCount'] ?? 1));
        $corpusWords = max(1.0, (float) ($corpusZones['competitorCorpusWords'] ?? 1));
        $siteWordCounts = [];

        foreach ($sites as $siteUrl => $site) {
            if (!is_array($site) || !empty($site['ignored']) || !empty($site['mainPage'])) {
                continue;
            }

            $siteWordCounts[$siteUrl] = self::siteWordCountForTfCloud($site);
        }

        foreach ($sites as $siteUrl => $site) {
            if (!is_array($site) || !empty($site['ignored']) || !empty($site['mainPage'])) {
                continue;
            }

            $siteWords = (float) ($siteWordCounts[$siteUrl] ?? 1);
            $items = [];

            foreach ($unigramTable as $root => $wordForm) {
                if (!is_array($wordForm) || empty($wordForm['total']) || !is_array($wordForm['total'])) {
                    continue;
                }

                $total = $wordForm['total'];
                $occurrences = $total['occurrences'] ?? [];
                if (empty($occurrences[$siteUrl])) {
                    continue;
                }

                $siteRepeats = (float) $occurrences[$siteUrl];
                $rawTopTotal = !empty($occurrences)
                    ? (float) array_sum($occurrences)
                    : max(1.0, (float) ($total['tf'] ?? 0) * $corpusWords);
                $documentFrequency = max(1, (int) ($total['numberOccurrences'] ?? count($occurrences)));

                $score = HybridRelevanceMetrics::hybridTfidfSite(
                    $siteRepeats,
                    max(1.0, $rawTopTotal),
                    $siteWords,
                    $corpusWords,
                    $documentFrequency,
                    $documentCount
                );

                if ($score <= 0) {
                    continue;
                }

                $items[] = [
                    'text' => (string) $root,
                    'weight' => $score,
                    'tfidfScore' => $score,
                    'tf' => $siteWords > 0 ? round($siteRepeats / $siteWords, 7) : 0.0,
                    'idf' => (float) ($total['idf'] ?? 0),
                ];
            }

            usort($items, static function ($a, $b) {
                return ($b['weight'] ?? 0) <=> ($a['weight'] ?? 0);
            });

            if (count($items) > $limit) {
                $items = array_slice($items, 0, $limit);
            }

            $items['count'] = count($items);
            $result[$siteUrl] = $items;
        }

        return $result;
    }

    public static function applyHybridTfCompCloudsFromUnigram(array &$data): void
    {
        if (empty($data['unigram_table']) || !is_array($data['unigram_table'])
            || empty($data['sites']) || !is_array($data['sites'])) {
            return;
        }

        $data['tf_comp_clouds'] = self::buildHybridTfCompCloudsFromSitesAndUnigram(
            $data['sites'],
            $data['unigram_table'],
            HybridRelevanceMetrics::corpusZoneStatsFromData($data)
        );
    }

    private function applyHybridTfCompCloudsFromUnigramTable(): void
    {
        $this->tfCompClouds = self::buildHybridTfCompCloudsFromSitesAndUnigram(
            $this->sites,
            $this->wordForms,
            $this->hybridCorpusZoneStats()
        );
    }

    /**
     * @param array<string, mixed> $unigramTable
     */
    public static function buildHybridTfCloudFromUnigram(array $unigramTable, string $scoreKey, int $limit = 200): array
    {
        $items = [];

        foreach ($unigramTable as $root => $wordForm) {
            if (!is_array($wordForm) || empty($wordForm['total']) || !is_array($wordForm['total'])) {
                continue;
            }

            $total = $wordForm['total'];
            if (!isset($total[$scoreKey])) {
                continue;
            }

            $score = round((float) $total[$scoreKey], 7);
            if ($score <= 0) {
                continue;
            }

            $items[] = [
                'text' => (string) $root,
                'weight' => $score,
                'tfidfScore' => $score,
                'tf' => (float) ($total['tf'] ?? 0),
                'idf' => (float) ($total['idf'] ?? 0),
            ];
        }

        usort($items, static function ($a, $b) {
            return ($b['weight'] ?? 0) <=> ($a['weight'] ?? 0);
        });

        if (count($items) > $limit) {
            $items = array_slice($items, 0, $limit);
        }

        $items['count'] = count($items);

        return $items;
    }

    private function applyTableTfidfToUnigramTable(): void
    {
        self::applyTableTfidfToUnigramWordForms($this->wordForms, $this->hybridCorpusZoneStats());
    }

    /**
     * @param array<string, mixed> $wordForms
     * @param array<string, int|float> $corpusZones
     */
    public static function applyTableTfidfToUnigramWordForms(array &$wordForms, array $corpusZones): void
    {
        foreach ($wordForms as &$wordForm) {
            if (!is_array($wordForm)) {
                continue;
            }

            foreach ($wordForm as $wordKey => &$word) {
                if ($wordKey === 'total' || !is_array($word)) {
                    continue;
                }

                HybridRelevanceMetrics::applyTableTfidfToWordStats($word, $corpusZones);
                HybridRelevanceMetrics::applyTableBm25ToWordStats($word, $corpusZones);
            }
            unset($word);

            if (!isset($wordForm['total']) || !is_array($wordForm['total'])) {
                continue;
            }

            HybridRelevanceMetrics::applyTableTfidfToWordStats($wordForm['total'], $corpusZones);
            HybridRelevanceMetrics::applyTableBm25ToWordStats($wordForm['total'], $corpusZones);
        }
        unset($wordForm);
    }

    /**
     * TF-idf облака сортируем по Tf из униграммы (как в таблице TLP), а не по score из ссылок.
     */
    private function applyUnigramTfToPreparedTfClouds(): void
    {
        $lookup = self::buildUnigramTfLookup($this->wordForms);
        if (!$lookup) {
            return;
        }

        $tfKeys = ['totalTf', 'textTf', 'linkTf'];
        foreach ($tfKeys as $key) {
            if (isset($this->mainPage[$key])) {
                $this->mainPage[$key] = self::remapTfCloudWithUnigramTf($this->mainPage[$key], $lookup);
            }
            if (isset($this->competitorsCloud[$key])) {
                $this->competitorsCloud[$key] = self::remapTfCloudWithUnigramTf($this->competitorsCloud[$key], $lookup);
            }
        }

        foreach ($this->tfCompClouds as $site => $cloud) {
            $this->tfCompClouds[$site] = self::remapTfCloudWithUnigramTf($cloud, $lookup);
        }
    }

    public static function buildUnigramTfLookup(array $unigramTable): array
    {
        $lookup = [];

        foreach ($unigramTable as $root => $wordForm) {
            if (!is_array($wordForm)) {
                continue;
            }

            $rootKey = mb_strtolower((string) $root, 'UTF-8');
            if (isset($wordForm['total']['tf'])) {
                $lookup[$rootKey] = max($lookup[$rootKey] ?? 0, (float) $wordForm['total']['tf']);
            }

            foreach ($wordForm as $word => $data) {
                if ($word === 'total' || !is_array($data) || !isset($data['tf'])) {
                    continue;
                }

                $wordKey = mb_strtolower((string) $word, 'UTF-8');
                $lookup[$wordKey] = max($lookup[$wordKey] ?? 0, (float) $data['tf']);
            }
        }

        return $lookup;
    }

    public static function remapTfCloudWithUnigramTf($cloud, array $lookup)
    {
        if (!is_array($cloud) || !$lookup) {
            return $cloud;
        }

        $items = [];
        foreach ($cloud as $item) {
            if (!is_array($item) || !isset($item['text'])) {
                continue;
            }

            $key = mb_strtolower((string) $item['text'], 'UTF-8');
            if (!isset($item['tfidfScore']) || $item['tfidfScore'] === '' || $item['tfidfScore'] === null) {
                $item['tfidfScore'] = $item['html']['title'] ?? $item['weight'] ?? null;
            }
            if (isset($lookup[$key])) {
                $item['weight'] = $lookup[$key];
                $item['tf'] = $lookup[$key];
            }

            $items[] = $item;
        }

        usort($items, function ($a, $b) {
            return ($b['weight'] ?? 0) <=> ($a['weight'] ?? 0);
        });
        $items['count'] = count($items);

        return $items;
    }

    public static function ensureCloudTfidfScoresBeforeRemap(array &$data): void
    {
        foreach (['clouds_competitors', 'clouds_main_page'] as $section) {
            if (empty($data[$section]) || !is_array($data[$section])) {
                continue;
            }

            foreach (['totalTf', 'textTf', 'linkTf'] as $key) {
                if (isset($data[$section][$key])) {
                    $data[$section][$key] = self::ensureCloudItemsTfidfScore($data[$section][$key]);
                }
            }
        }

        if (!empty($data['tf_comp_clouds']) && is_array($data['tf_comp_clouds'])) {
            foreach ($data['tf_comp_clouds'] as $site => $cloud) {
                $data['tf_comp_clouds'][$site] = self::ensureCloudItemsTfidfScore($cloud);
            }
        }
    }

    /**
     * Старые сохранённые облака: weight = TF×IDF, tfidfScore ещё нет.
     *
     * @param mixed $cloud
     * @return mixed
     */
    public static function ensureCloudItemsTfidfScore($cloud)
    {
        if (!is_array($cloud)) {
            return $cloud;
        }

        $items = [];
        foreach ($cloud as $item) {
            if (!is_array($item) || !isset($item['text'])) {
                continue;
            }

            if (!isset($item['tfidfScore']) || $item['tfidfScore'] === '' || $item['tfidfScore'] === null) {
                if (isset($item['html']['title']) && is_numeric($item['html']['title'])) {
                    $item['tfidfScore'] = (float) $item['html']['title'];
                } elseif (isset($item['tf'], $item['idf']) && is_numeric($item['tf']) && is_numeric($item['idf'])) {
                    $item['tfidfScore'] = TfidfMetrics::score((float) $item['tf'], (float) $item['idf']);
                } elseif (isset($item['weight']) && is_numeric($item['weight'])) {
                    $item['tfidfScore'] = (float) $item['weight'];
                }
            }

            $items[] = $item;
        }

        $items['count'] = count($items);

        return $items;
    }

    public static function applyUnigramTfToStoredTfClouds(array &$data): void
    {
        if (empty($data['unigram_table']) || !is_array($data['unigram_table'])) {
            return;
        }

        $lookup = self::buildUnigramTfLookup($data['unigram_table']);
        if (!$lookup) {
            return;
        }

        foreach (['clouds_competitors', 'clouds_main_page'] as $section) {
            if (empty($data[$section]) || !is_array($data[$section])) {
                continue;
            }

            foreach (['totalTf', 'textTf', 'linkTf'] as $key) {
                if (isset($data[$section][$key])) {
                    $data[$section][$key] = self::remapTfCloudWithUnigramTf($data[$section][$key], $lookup);
                }
            }
        }

        if (!empty($data['tf_comp_clouds']) && is_array($data['tf_comp_clouds'])) {
            foreach ($data['tf_comp_clouds'] as $site => $cloud) {
                $data['tf_comp_clouds'][$site] = self::remapTfCloudWithUnigramTf($cloud, $lookup);
            }
        }
    }

    public function removeIgnoredDomains($request, $sites, $exp)
    {
        $ignoredDomains = str_replace("\r\n", "\n", $request['ignoredDomains']);
        $ignoredDomains = explode("\n", $ignoredDomains);
        $ignoredDomains = array_map("mb_strtolower", $ignoredDomains);
        $iterator = 0;

        foreach ($sites as $key => $item) {
            if (str_contains($item, '.pdf') || str_contains($item, 'video')) {
                continue;
            }

            $domain = parse_url($item);
            $domain = str_replace('www.', "", mb_strtolower($domain['host']));

            if ($iterator < $request['count']) {
                $this->domains[$key] = [
                    'item' => $item,
                    'position' => $key + 1,
                ];

                if (in_array($domain, $ignoredDomains)) {
                    $this->domains[$key]['ignored'] = true;
                } else {
                    $this->domains[$key]['ignored'] = false;
                    $iterator++;
                }

            } else {
                if (filter_var($exp, FILTER_VALIDATE_BOOLEAN) && $key < 50) {
                    $this->domains[$key] = [
                        'exp' => true,
                        'ignored' => true,
                        'item' => $item,
                        'position' => $key + 1,
                    ];
                } else {
                    break;
                }
            }
        }
    }

    public function setMainPage($html)
    {
        $this->mainPage['html'] = $html;
        $this->params['html_main_page'] = $html;
    }

    public function setSites($sites)
    {
        $mainPageInRelevance = false;
        $this->params['sites'] = $sites;

        foreach (json_decode($sites, true) as $key => $site) {
            if (isset($this->sites[$key]['mainPage']) && $this->sites[$key]['mainPage']) {
                $this->sites[$key] = [
                    'danger' => false,
                    'html' => $this->mainPage['html'],
                    'defaultHtml' => $this->mainPage['html'],
                    'ignored' => false,
                    'mainPage' => true,
                    'equallyHost' => false,
                    'site' => $key,
                    'position' => $site['position'],
                ];

                $mainPageInRelevance = true;
            } else {
                $this->sites[$key] = [
                    'danger' => $site['danger'],
                    'html' => gzuncompress(base64_decode($site['defaultHtml'])),
                    'defaultHtml' => gzuncompress(base64_decode($site['defaultHtml'])),
                    'ignored' => $site['ignored'],
                    'mainPage' => $site['mainPage'],
                    'equallyHost' => $site['equallyHost'] ?? false,
                    'site' => $key,
                    'position' => $site['position'],
                ];
            }

            if (!$mainPageInRelevance) {
                $this->sites[$this->params['main_page_link']] = [
                    'danger' => false,
                    'html' => $this->mainPage['html'],
                    'defaultHtml' => $this->mainPage['html'],
                    'ignored' => false,
                    'mainPage' => true,
                    'equallyHost' => false,
                    'site' => $this->params['main_page_link'],
                    'position' => 0,
                ];
            }
        }
    }

    public function setDomains($domains)
    {
        $array = json_decode($domains, true);

        foreach ($array as $key => $item) {
            $this->domains[$key] = [
                'item' => $item['site'],
                'ignored' => $item['ignored'],
                'position' => $item['position'],
            ];

            if (isset($item['inRelevance']) && !$item['inRelevance']) {
                $this->domains[$key]['inRelevance'] = false;
            }
        }

    }

    public static function concatenation(array $array): string
    {
        return implode(' ', $array);
    }

    public function prepareTfCloud($text, array $documentFrequencyMap = []): array
    {
        $wordForms = $cloud = [];
        $m = new Morphy();
        $documentCount = max(1, (int) $this->countNotIgnoredSites);

        $array = array_count_values(explode(' ', $text));
        arsort($array);
        $array = array_slice($array, 0, 199);

        $wordCount = max(1, count(explode(' ', $text)));
        $excludeLookup = self::excludedWordsLookup($this->request);
        foreach ($array as $key => $item) {
            if (self::isExcludedLemma((string) $key, $excludeLookup)) {
                continue;
            }
            $tf = TfidfMetrics::termFrequency((float) $item, (float) $wordCount);
            $documentFrequency = max(1, (int) ($documentFrequencyMap[$key] ?? 1));
            $idf = TfidfMetrics::inverseDocumentFrequency($documentCount, $documentFrequency);
            $score = TfidfMetrics::score($tf, $idf);
            $cloud[] = [
                'text' => $key,
                'weight' => $score,
                'tfidfScore' => $score,
                'tf' => $tf,
                'idf' => $idf,
            ];
        }

        foreach ($cloud as $key1 => $item1) {
            $weight = 0;
            foreach ($cloud as $key2 => $item2) {
                similar_text($item1['text'], $item2['text'], $percent);
                if (
                    preg_match("/[А-я]/", $item1['text']) &&
                    $m->base($item1['text']) == $m->base($item2['text']) ||
                    preg_match("/[A-Za-z]/", $item2['text']) &&
                    $percent >= 82
                ) {
                    $weight += $item2['weight'];
                    unset($cloud[$key1]);
                    unset($cloud[$key2]);
                }
            }

            $totalWeight = $item1['weight'] + $weight;
            $wordForms[] = [
                'text' => $item1['text'],
                'weight' => $totalWeight,
                'tfidfScore' => $totalWeight,
                'html' => [
                    'title' => $totalWeight
                ]
            ];

            if (count($wordForms) == 200) {
                break;
            }
        }
        $wordForms['count'] = count($wordForms) - 1;
        $collection = collect($wordForms);

        $dense = [];
        foreach ($collection->sortByDesc('weight')->values()->all() as $item) {
            if (is_array($item) && isset($item['text'])) {
                $dense[] = $item;
            }
        }
        $dense['count'] = count($dense);

        return $dense;
    }

    public function separateText($text): string
    {
        $text = explode(" ", $text);
        foreach ($text as $key => $item) {
            if (Str::length($item) < $this->maxWordLength) {
                unset($text[$key]);
            }
        }

        return implode(" ", $text);
    }

    public function preparePhrasesTable()
    {
        $this->phrases = self::buildPhrasesTableFromSites(
            $this->sites,
            $this->mainPage,
            $this->hybridCorpusZoneStats(),
            $this->wordForms ?: null
        );
        $this->phrases = TextAnalyzer::filterExcludedFromPhrases(
            $this->phrases,
            (string) ($this->request['listWords'] ?? '')
        );
    }

    /**
     * @param array<string, array<string, mixed>> $sites
     * @param array<string, mixed> $mainPage
     * @param array<string, int|float> $corpusZones
     * @return array<string, array<string, mixed>>
     */
    public static function buildPhrasesTableFromSites(
        array $sites,
        array $mainPage,
        array $corpusZones,
        ?array $unigramTable = null
    ): array {
        RelevancePhraseNgrams::configureLemmaContext($unigramTable);

        try {
            return self::buildPhrasesTableFromSitesWithLemmaContext($sites, $mainPage, $corpusZones, $unigramTable);
        } finally {
            RelevancePhraseNgrams::resetLemmaContext();
        }
    }

    /**
     * @param array<string, array<string, mixed>> $sites
     * @param array<string, mixed> $mainPage
     * @param array<string, int|float> $corpusZones
     * @return array<string, array<string, mixed>>
     */
    private static function buildPhrasesTableFromSitesWithLemmaContext(
        array $sites,
        array $mainPage,
        array $corpusZones,
        ?array $unigramTable = null
    ): array {
        $result = [];
        $phraseCandidates = RelevancePhraseNgrams::candidatePhrases($sites);
        $documentCount = max(1, (int) ($corpusZones['documentCount'] ?? self::competitorDocumentCountFromSites($sites)));
        $totalCandidates = max(1, count($phraseCandidates));

        foreach ($phraseCandidates as $phrase) {
            if ($phrase === '' || !RelevancePhraseNgrams::isValidUnigramPhrase($phrase)) {
                continue;
            }

            $row = self::phraseStatsRow($phrase, $sites, $mainPage, $documentCount, $totalCandidates);
            if ($row === null) {
                continue;
            }

            if ((int) ($row['numberOccurrences'] ?? 0) < 2) {
                continue;
            }

            HybridRelevanceMetrics::applyTableTfidfToWordStats($row, $corpusZones);
            HybridRelevanceMetrics::applyTableBm25ToWordStats($row, $corpusZones);
            $result[$phrase] = $row;
        }

        $result = RelevancePhraseNgrams::filterQualityPhrases($result, $unigramTable);
        $result = RelevancePhraseNgrams::deduplicatePermutedPhrases($result);
        $result = RelevancePhraseNgrams::deduplicateOverlappingPhrases($result);

        $collection = collect($result)->sortByDesc(static function (array $row, string $phrase) {
            return [
                (float) ($row['tfidfTop'] ?? $row['score'] ?? 0),
                (float) ($row['bm25Top'] ?? 0),
                RelevancePhraseNgrams::phraseLengthScore(RelevancePhraseNgrams::phraseTokens($phrase)),
                (int) ($row['numberOccurrences'] ?? 0),
            ];
        });

        return $collection->slice(0, 600)->toArray();
    }

    /**
     * @param array<string, array<string, mixed>> $sites
     * @param array<string, mixed> $mainPage
     */
    private static function phraseStatsRow(
        string $phrase,
        array $sites,
        array $mainPage,
        int $documentCount,
        int $totalCandidates
    ): ?array {
        $reSpam = 0;
        $numberTextOccurrences = 0;
        $numberLinkOccurrences = 0;
        $numberOccurrences = 0;
        $occurrences = [];
        $perSiteCounts = [];

        foreach ($sites as $key => $page) {
            if (!empty($page['ignored']) || !empty($page['mainPage'])) {
                continue;
            }

            $htmlCount = RelevancePhraseNgrams::countLemmaPhraseOccurrences($phrase, $page['html'] ?? '');
            $hiddenTextCount = RelevancePhraseNgrams::countLemmaPhraseOccurrences($phrase, $page['hiddenText'] ?? '');
            $linkTextCount = RelevancePhraseNgrams::countLemmaPhraseOccurrences($phrase, $page['linkText'] ?? '');

            if ($htmlCount > 0) {
                $numberTextOccurrences += $htmlCount;
            }
            if ($hiddenTextCount > 0) {
                $numberTextOccurrences += $hiddenTextCount;
            }
            if ($linkTextCount > 0) {
                $numberLinkOccurrences += $linkTextCount;
            }

            if ($linkTextCount > 0 || $hiddenTextCount > 0 || $htmlCount > 0) {
                $countRepeat = $linkTextCount + $hiddenTextCount + $htmlCount;
                $numberOccurrences++;
                $occurrences[$key] = $countRepeat;
                $perSiteCounts[] = $countRepeat;
                if ($reSpam < $countRepeat) {
                    $reSpam = $countRepeat;
                }
            }
        }

        if ($numberOccurrences <= 0) {
            return null;
        }

        $countOccurrences = $numberTextOccurrences + $numberLinkOccurrences;
        $tf = TfidfMetrics::termFrequency((float) $countOccurrences, (float) max(1, $totalCandidates));
        $idf = TfidfMetrics::inverseDocumentFrequency($documentCount, $numberOccurrences);
        $score = TfidfMetrics::score($tf, $idf);

        $mainText = Relevance::concatenation([$mainPage['html'] ?? '', $mainPage['hiddenText'] ?? '']);
        $repeatInTextMainPage = RelevancePhraseNgrams::countLemmaPhraseOccurrences($phrase, $mainText);
        $repeatLinkInMainPage = RelevancePhraseNgrams::countLemmaPhraseOccurrences($phrase, $mainPage['linkText'] ?? '');
        $countSites = max(1, $documentCount);
        arsort($occurrences);

        return [
            'tf' => $tf,
            'idf' => $idf,
            'score' => $score,
            'numberOccurrences' => $numberOccurrences,
            'medianInCompetitors' => self::medianCount($perSiteCounts),
            'reSpam' => $reSpam,
            'avgInTotalCompetitors' => (int) ceil(($numberLinkOccurrences + $numberTextOccurrences) / $countSites),
            'avgInLink' => (int) ceil($numberLinkOccurrences / $countSites),
            'avgInText' => (int) ceil($numberTextOccurrences / $countSites),
            'repeatInLinkMainPage' => $repeatLinkInMainPage,
            'repeatInTextMainPage' => $repeatInTextMainPage,
            'totalRepeatMainPage' => $repeatLinkInMainPage + $repeatInTextMainPage,
            'occurrences' => $occurrences,
        ];
    }

    /**
     * @param list<int> $values
     */
    private static function medianCount(array $values): int
    {
        $values = array_values(array_filter($values, static function ($value) {
            return (int) $value > 0;
        }));
        if ($values === []) {
            return 0;
        }

        sort($values);
        $count = count($values);
        $middle = (int) floor($count / 2);

        if ($count % 2 === 1) {
            return (int) $values[$middle];
        }

        return (int) round(($values[$middle - 1] + $values[$middle]) / 2);
    }

    /**
     * @param array<string, mixed> $site
     */
    private static function siteWordCountForTfCloud(array $site): int
    {
        $countWords = (int) ($site['countWords'] ?? 0);
        if ($countWords > 0) {
            return $countWords;
        }

        $siteText = self::concatenation([
            $site['html'] ?? '',
            $site['hiddenText'] ?? '',
            $site['linkText'] ?? '',
        ]);

        return max(1, HybridRelevanceMetrics::countWordsInText($siteText));
    }

    public static function decodeStoredSiteHtml($raw): string
    {
        if (!is_string($raw) || $raw === '') {
            return '';
        }

        $decoded = @gzuncompress(base64_decode($raw, true));
        if (is_string($decoded) && $decoded !== '') {
            return $decoded;
        }

        return $raw;
    }

    /**
     * В истории saveResults() удаляет html/linkText/hiddenText, оставляя только defaultHtml.
     * Для пересборки TLPs восстанавливаем текстовые зоны тем же пайплайном, что при анализе.
     *
     * @param array<string, array<string, mixed>> $sites
     */
    public static function hydrateStoredSitesTextZones(array &$sites, array $request = [], ?string $mainPageRawHtml = null): void
    {
        $searchPassages = filter_var($request['searchPassages'] ?? false, FILTER_VALIDATE_BOOLEAN);

        foreach ($sites as $key => &$site) {
            if (!is_array($site) || !self::storedSiteNeedsTextHydration($site)) {
                continue;
            }

            $rawHtml = '';
            if (!empty($site['mainPage']) && is_string($mainPageRawHtml) && $mainPageRawHtml !== '') {
                $rawHtml = $mainPageRawHtml;
            } elseif (!empty($site['defaultHtml'])) {
                $rawHtml = self::decodeStoredSiteHtml((string) $site['defaultHtml']);
            }

            if ($rawHtml === '') {
                continue;
            }

            $zones = self::processStoredSiteTextZones($rawHtml, $request, $searchPassages);
            $site['html'] = $zones['html'];
            $site['linkText'] = $zones['linkText'];
            $site['hiddenText'] = $zones['hiddenText'];
            $site['passages'] = $zones['passages'];
        }
        unset($site);
    }

    /**
     * @param array<string, mixed> $site
     */
    private static function storedSiteNeedsTextHydration(array $site): bool
    {
        return trim((string) ($site['html'] ?? '')) === ''
            && trim((string) ($site['linkText'] ?? '')) === ''
            && trim((string) ($site['hiddenText'] ?? '')) === ''
            && (!empty($site['defaultHtml']) || !empty($site['mainPage']));
    }

    /**
     * @return array{html:string,linkText:string,hiddenText:string,passages:string}
     */
    private static function processStoredSiteTextZones(string $rawHtml, array $request, bool $searchPassages): array
    {
        $html = $rawHtml;

        if (($request['noIndex'] ?? '') === 'false') {
            $html = TextAnalyzer::removeNoindexText($html);
        }

        $hiddenText = '';
        if (($request['hiddenText'] ?? '') === 'true') {
            $hiddenText = self::getHiddenText($html);
        }

        $sourceHtml = $html;
        $linkText = TextAnalyzer::getLinkText($html);
        $html = TextAnalyzer::deleteEverythingExceptCharacters(TextAnalyzer::clearHTMLFromLinks($html));

        $passages = '';
        if ($searchPassages) {
            $passages = self::searchPassages($sourceHtml);
            $passagesArray = explode(' ', $passages);
            $htmlSpaced = ' ' . $html . ' ';
            foreach ($passagesArray as $item) {
                if ($item === '') {
                    continue;
                }
                $search = ' ' . $item . ' ';
                $pos = strpos($htmlSpaced, $search);
                if ($pos !== false) {
                    $htmlSpaced = substr_replace($htmlSpaced, ' ', $pos, strlen($search));
                }
            }
            $html = trim($htmlSpaced);
        }

        if (($request['conjunctionsPrepositionsPronouns'] ?? '') === 'false') {
            $html = TextAnalyzer::removeConjunctionsPrepositionsPronouns($html);
            $linkText = TextAnalyzer::removeConjunctionsPrepositionsPronouns($linkText);
            $hiddenText = TextAnalyzer::removeConjunctionsPrepositionsPronouns($hiddenText);
        }

        if (self::shouldApplyExcludedWordsList($request)) {
            $listWords = (string) ($request['listWords'] ?? '');
            $html = TextAnalyzer::removeWords($listWords, $html);
            $linkText = TextAnalyzer::removeWords($listWords, $linkText);
            $hiddenText = TextAnalyzer::removeWords($listWords, $hiddenText);
        }

        return [
            'html' => trim($html),
            'linkText' => trim($linkText),
            'hiddenText' => trim($hiddenText),
            'passages' => trim($passages),
        ];
    }

    public function searchPhrases(): array
    {
        return RelevancePhraseNgrams::candidatePhrases($this->sites);
    }

    public function calculateDensity()
    {
        foreach ($this->sites as $keyPage => $page) {
            $allText = Relevance::concatenation([$page['html'], $page['linkText'], $page['hiddenText']]);

            $this->sites[$keyPage]['density'] = $this->calculateDensityPoints($allText);
        }
    }

    public function calculateDensityPoints($text): array
    {
        $result = [];
        $array = explode(' ', $text);
        $array = array_count_values($array);
        $densityMain = 0;
        $testMainIterator = 0;
        foreach ($this->wordForms as $wordForm) {
            if (empty($wordForm['total']) || !is_array($wordForm['total'])) {
                continue;
            }

            $countRepeatInPage = 0;
            foreach ($wordForm as $word => $form) {
                if ($word == 'total') {
                    continue;
                }
                if (array_key_exists($word, $array)) {
                    foreach ($wordForm as $w => $info) {
                        if ($w == 'total') {
                            continue;
                        }
                        $countRepeatInPage += $array[$w] ?? 0;
                    }

                    $avg = (float) ($wordForm['total']['avgInTotalCompetitors'] ?? 0);
                    $points = $avg > 0 ? min($countRepeatInPage / ($avg / 100), 100) : 0;
                    $densityMain += $points;

                    break;
                }
            }
            $testMainIterator++;
        }

        $result['densityMain'] = min(round($densityMain), 100);
        $result['densityMainPercent'] = round($densityMain / 1000);

        return $result;
    }

    public function saveResults()
    {
        $saveObject = [];
        foreach ($this->sites as $key => $site) {
            if (!array_key_exists('exp', $this->sites[$key])) {
                unset($this->sites[$key]['html']);
                unset($this->sites[$key]['linkText']);
                unset($this->sites[$key]['hiddenText']);
                $this->sites[$key]['defaultHtml'] = base64_encode(gzcompress($this->sites[$key]['defaultHtml'], 9));

                $saveObject[$key] = $this->sites[$key];
            }
        }

        if (!$this->queue) {
            $this->params['sites'] = json_encode($saveObject);
            $this->params->save();
        }
    }

    public function saveHistory($historyId)
    {
        RelevanceProgress::editProgress(100, $this->request);
        $this->saveResults();
        $this->saveStatistic();

        $time = Carbon::now()->toDateTimeString();
        $link = parse_url($this->params['main_page_link']);

        $main = ProjectRelevanceHistory::createOrUpdate($link['host'], $time, $this->userId);

        foreach ($this->sites as $site) {
            if ($site['mainPage']) {
                $stat = [
                    'mainPoints' => $site['mainPoints'],
                    'coverage' => $site['coverage'],
                    'coverageTf' => $site['coverageTf'],
                    'width' => $site['width'],
                    'density' => $site['density']['densityMainPercent'],
                    'position' => $site['position']
                ];

                $id = RelevanceHistory::createOrUpdate(
                    $this->phrase,
                    $this->params['main_page_link'],
                    $this->request,
                    $stat,
                    $time,
                    $main,
                    true,
                    $historyId,
                    base64_encode(gzcompress($this->params['html_main_page'], 9)),
                    json_encode($this->sites)
                );

                RelevanceHistory::where('user_id', '=', $this->userId)
                    ->where('phrase', '=', $this->request['phrase'])
                    ->where('main_link', '=', $this->request['link'])
                    ->where('position', '=', 0)
                    ->where('points', '=', 0)
                    ->where('coverage', '=', 0)
                    ->where('density', '=', 0)
                    ->where('html_main_page', '=', '')
                    ->delete();

                ProjectRelevanceHistory::calculateInfo($main);

                $this->saveHistoryResult($id);
            }
        }
    }

    public function saveHistoryResult($id)
    {
        $result = RelevanceHistoryResult::firstOrNew(['project_id' => $id]);

        $result->clouds_competitors = base64_encode(gzcompress(json_encode([
            'totalTf' => json_encode($this->competitorsCloud['totalTf']),
            'textTf' => json_encode($this->competitorsCloud['textTf']),
            'linkTf' => json_encode($this->competitorsCloud['linkTf']),

            'textAndLinks' => json_encode($this->competitorsTextAndLinksCloud),
            'links' => json_encode($this->competitorsLinksCloud),
            'text' => json_encode($this->competitorsTextCloud),
        ]), 9));


        $result->clouds_main_page = base64_encode(gzcompress(json_encode([
            'totalTf' => json_encode($this->mainPage['totalTf']),
            'textTf' => json_encode($this->mainPage['textTf']),
            'linkTf' => json_encode($this->mainPage['linkTf']),
            'textWithLinks' => json_encode($this->mainPage['textWithLinks']),
            'links' => json_encode($this->mainPage['links']),
            'text' => json_encode($this->mainPage['text']),
        ]), 9));

        $result->avg = base64_encode(gzcompress(json_encode([
            'countWords' => $this->countWordsForTextAvg / max(1, $this->countSitesForTextAvg),
            'countSymbols' => $this->countSymbolsForTextAvg / max(1, $this->countSitesForTextAvg),
        ]), 9));

        $corpusZones = $this->hybridCorpusZoneStats();

        $result->main_page = base64_encode(gzcompress(json_encode([
            'countWords' => $this->countWordsInMyPage,
            'countSymbols' => $this->countSymbolsInMyPage,
            'competitorCorpusWords' => $corpusZones['competitorCorpusWords'],
            'competitorTextWords' => $corpusZones['competitorTextWords'],
            'competitorLinkWords' => $corpusZones['competitorLinkWords'],
            'mainPageTextWords' => $corpusZones['mainPageTextWords'],
            'mainPageLinkWords' => $corpusZones['mainPageLinkWords'],
            'avgCompetitorDocWords' => $corpusZones['avgCompetitorDocWords'],
        ]), 9));

        $result->average_values = json_encode($this->avg);
        $result->unigram_table = base64_encode(gzcompress(json_encode($this->wordForms), 9));
        $result->sites = base64_encode(gzcompress(json_encode($this->sites), 9));
        $result->tf_comp_clouds = base64_encode(gzcompress(json_encode($this->tfCompClouds), 9));
        $result->phrases = base64_encode(gzcompress(json_encode($this->phrases), 9));
        $result->avg_coverage_percent = base64_encode(gzcompress(json_encode($this->avgCoveragePercent), 9));
        $result->recommendations = base64_encode(gzcompress(json_encode($this->recommendations), 9));
        $result->hash = $this->scanHash;

        $result->compressed = true;
        $result->save();
    }

    public function analysisByPhrase($request, $exp)
    {
        RelevanceProgress::editProgress(10, $request);
        $xml = new SimplifiedXmlFacade($request['region']);
        $xml->setQuery($request['phrase']);
        $xmlResponse = $xml->getXMLResponse();

        $this->removeIgnoredDomains($request, $xmlResponse, $exp);
        $this->parseSites($xmlResponse);
    }

    public function analysisByList($request)
    {
        RelevanceProgress::editProgress(10, $request);
        $this->prepareDomains($request['siteList']);
        $this->parseSites(false, true);
    }

    public function prepareDomains($siteList)
    {
        $sitesList = str_replace("\r\n", "\n", $siteList);
        $sitesList = explode("\n", $sitesList);

        foreach ($sitesList as $item) {
            $this->domains[] = [
                'item' => str_replace('www.', '', mb_strtolower(trim($item))),
                'ignored' => false,
                'position' => count($this->domains) + 1
            ];
        }
    }

    public function saveStatistic()
    {
        $toDay = RelevanceStatistics::firstOrNew(['date' => Carbon::now()->toDateString()]);
        if ($toDay->id) {
            $toDay->count_checks += 1;
        } else {
            $toDay->count_checks = 1;
        }
        $toDay->save();

        RelevanceUniquePages::firstOrCreate(['name' => Str::lower($this->params['main_page_link'])]);

        $mainUrl = parse_url($this->params['main_page_link']);
        RelevanceUniqueDomains::firstOrCreate(['name' => Str::lower($mainUrl['host'])]);

        foreach ($this->sites as $url => $item) {
            RelevanceAllUniquePages::firstOrCreate(['name' => Str::lower($url)]);

            $link = parse_url($url);
            RelevanceAllUniqueDomains::firstOrCreate(['name' => Str::lower($link['host'])]);
        }
    }

    public function calculateAvg()
    {
        $coverage = $coverageTf = $density = $width = $points = $countSymbols = [];
        foreach ($this->sites as $site) {
            $coverage[] = $site['coverage'];
            $coverageTf[] = $site['coverageTf'];
            $density[] = $site['density']['densityMainPercent'];
            $width[] = $site['width'];
            $points[] = $site['mainPoints'];
            $countSymbols[] = $site['countSymbols'];
        }

        rsort($coverage);
        rsort($coverageTf);
        rsort($density);
        rsort($width);
        rsort($points);
        rsort($countSymbols);

        for ($i = 0; $i <= 4; $i++) {
            $this->calculate('coverage', $coverage[$i] / 5);
            $this->calculate('coverageTf', $coverageTf[$i] / 5);
            $this->calculate('densityPercent', $density[$i] / 5);
            $this->calculate('width', $width[$i] / 5);
            $this->calculate('points', $points[$i] / 5);
            $this->calculate('countSymbols', $countSymbols[$i] / 5);
        }
    }

    public function calculate($key, $elem)
    {
        if (isset($this->avg[$key])) {
            $this->avg[$key] += $elem;
        } else {
            $this->avg[$key] = $elem;
        }
    }

    public function saveError($exception)
    {
        Log::debug('Relevance Error', [
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'message' => $exception->getMessage(),
        ]);

        $toDay = RelevanceStatistics::firstOrNew(['date' => date('Y-m-d')]);
        $toDay->increment('count_fails');
        $toDay->save();

        UsersJobs::where('user_id', '=', $this->params['user_id'])->decrement('count_jobs');
        RelevanceProgress::where('hash', $this->scanHash)->update(['error' => 1]);
    }

    public static function uncompress($history)
    {
        if (!isset($history)) {
            return null;
        }

        if (is_string($history)) {
            $history = json_decode($history, true);
        } elseif ($history instanceof \Illuminate\Database\Eloquent\Model) {
            $history = $history->toArray();
        } elseif (is_object($history)) {
            $history = json_decode(json_encode($history), true);
        }

        if (!is_array($history)) {
            return null;
        }

        if (!$history['cleaning']) {
                $clouds_competitors = json_decode(gzuncompress(base64_decode($history['clouds_competitors'])), true);
                $clouds_main_page = json_decode(gzuncompress(base64_decode($history['clouds_main_page'])), true);
                $avg = json_decode(gzuncompress(base64_decode($history['avg'])), true);
                $main_page = json_decode(gzuncompress(base64_decode($history['main_page'])), true);

                $data = [
                    'clouds_competitors' => [
                        'totalTf' => json_decode($clouds_competitors['totalTf'], true),
                        'textTf' => json_decode($clouds_competitors['textTf'], true),
                        'linkTf' => json_decode($clouds_competitors['linkTf'], true),

                        'textAndLinks' => json_decode($clouds_competitors['textAndLinks'], true),
                        'links' => json_decode($clouds_competitors['links'], true),
                        'text' => json_decode($clouds_competitors['text'], true),
                    ],
                    'clouds_main_page' => [
                        'totalTf' => json_decode($clouds_main_page['totalTf'], true),
                        'textTf' => json_decode($clouds_main_page['textTf'], true),
                        'linkTf' => json_decode($clouds_main_page['linkTf'], true),
                        'textWithLinks' => json_decode($clouds_main_page['textWithLinks'], true),
                        'links' => json_decode($clouds_main_page['links'], true),
                        'text' => json_decode($clouds_main_page['text'], true),
                    ],
                    'avg' => [
                        'countWords' => json_decode($avg['countWords'], true),
                        'countSymbols' => json_decode($avg['countSymbols'], true),
                    ],
                    'main_page' => [
                        'countWords' => json_decode($main_page['countWords'], true),
                        'countSymbols' => json_decode($main_page['countSymbols'], true),
                    ],

                    'unigram_table' => json_decode(gzuncompress(base64_decode($history['unigram_table'])), true),
                    'history_id' => $history['id'],
                    'sites' => json_decode(gzuncompress(base64_decode($history['sites'])), true),
                    'tf_comp_clouds' => json_decode(gzuncompress(base64_decode($history['tf_comp_clouds'])), true),
                    'phrases' => json_decode(gzuncompress(base64_decode($history['phrases'])), true),
                    'avg_coverage_percent' => json_decode(gzuncompress(base64_decode($history['avg_coverage_percent'])), true),
                    'recommendations' => json_decode(gzuncompress(base64_decode($history['recommendations'])), true),
                    'cleaning' => false
                ];
            } else {
                $data = [
                    'sites' => json_decode(gzuncompress(base64_decode($history['sites'])), true),
                    'avg_coverage_percent' => json_decode(gzuncompress(base64_decode($history['avg_coverage_percent'])), true),
                    'cleaning' => true
                ];
            }

            $data['average_values'] = json_decode($history['average_values'], true);

            if (empty($data['cleaning'])) {
                self::recalculateStoredTfidf($data);
                self::ensureCloudTfidfScoresBeforeRemap($data);
            }

            $historyRequest = null;
            $historyRow = null;
            if (!empty($history['project_id'])) {
                $historyRow = RelevanceHistory::find($history['project_id']);
                if ($historyRow && !empty($historyRow->request)) {
                    $historyRequest = json_decode($historyRow->request, true);
                }
            }
            if (is_array($historyRequest)) {
                self::filterStoredDetailsExcludedWords($data, $historyRequest);
            }

            if (empty($data['cleaning']) && !empty($data['sites']) && is_array($data['sites'])) {
                $storedPhrases = $data['phrases'] ?? null;
                $shouldRebuildPhrases = self::shouldRebuildStoredPhrases($storedPhrases);

                if ($shouldRebuildPhrases) {
                    $sitesForPhrases = $data['sites'];
                    $mainPageRawHtml = null;
                    if ($historyRow && !empty($historyRow->html_main_page)) {
                        $mainPageRawHtml = self::decodeStoredSiteHtml((string) $historyRow->html_main_page);
                    }
                    if (is_array($historyRequest)) {
                        self::hydrateStoredSitesTextZones($sitesForPhrases, $historyRequest, $mainPageRawHtml);
                    } else {
                        self::hydrateStoredSitesTextZones($sitesForPhrases, [], $mainPageRawHtml);
                    }

                    $mainPageSite = self::mainPageFromSites($sitesForPhrases);
                    $corpusZones = HybridRelevanceMetrics::corpusZoneStatsFromData($data);
                    $data['phrases'] = self::buildPhrasesTableFromSites(
                        $sitesForPhrases,
                        $mainPageSite,
                        $corpusZones,
                        $data['unigram_table'] ?? null
                    );
                    if (is_array($historyRequest)) {
                        $data['phrases'] = TextAnalyzer::filterExcludedFromPhrases(
                            $data['phrases'],
                            (string) ($historyRequest['listWords'] ?? '')
                        );
                    }
                } else {
                    self::enrichPhrasesHybridMetrics($data);
                }
            } elseif (!empty($data['phrases']) && is_array($data['phrases'])) {
                self::enrichPhrasesHybridMetrics($data);
            }

            if (empty($data['cleaning'])) {
                self::enrichUnigramHybridMetrics($data);
                self::applyHybridTfCloudsFromUnigram($data);
                self::applyHybridTfCompCloudsFromUnigram($data);
                if (is_array($historyRequest)) {
                    $excludeLookup = self::excludedWordsLookup($historyRequest);
                    if ($excludeLookup && !empty($data['tf_comp_clouds']) && is_array($data['tf_comp_clouds'])) {
                        foreach ($data['tf_comp_clouds'] as $site => $cloud) {
                            $data['tf_comp_clouds'][$site] = self::filterCloudPayload($cloud, $excludeLookup);
                        }
                    }
                }
            }

            return $data;
    }

    public static function enrichPhrasesHybridMetrics(array &$data): void
    {
        if (empty($data['phrases']) || !is_array($data['phrases'])) {
            return;
        }

        RelevancePhraseNgrams::configureLemmaContext($data['unigram_table'] ?? null);

        try {
            self::enrichPhrasesHybridMetricsWithLemmaContext($data);
        } finally {
            RelevancePhraseNgrams::resetLemmaContext();
        }
    }

    private static function enrichPhrasesHybridMetricsWithLemmaContext(array &$data): void
    {
        $corpusZones = HybridRelevanceMetrics::corpusZoneStatsFromData($data);

        foreach ($data['phrases'] as &$phrase) {
            if (!is_array($phrase)) {
                continue;
            }

            HybridRelevanceMetrics::applyTableTfidfToWordStats($phrase, $corpusZones);
            HybridRelevanceMetrics::applyTableBm25ToWordStats($phrase, $corpusZones);
        }
        unset($phrase);

        $data['phrases'] = RelevancePhraseNgrams::filterQualityPhrases(
            $data['phrases'],
            $data['unigram_table'] ?? null
        );
        $data['phrases'] = RelevancePhraseNgrams::deduplicatePermutedPhrases($data['phrases']);
        $data['phrases'] = RelevancePhraseNgrams::deduplicateOverlappingPhrases($data['phrases']);

        $data['phrases'] = collect($data['phrases'])
            ->sortByDesc(static function (array $row, string $phrase) {
                return [
                    (float) ($row['tfidfTop'] ?? $row['score'] ?? 0),
                    (float) ($row['bm25Top'] ?? 0),
                    RelevancePhraseNgrams::phraseLengthScore(RelevancePhraseNgrams::phraseTokens($phrase)),
                    (int) ($row['numberOccurrences'] ?? 0),
                ];
            })
            ->take(600)
            ->all();
    }

    /**
     * @param array<string, array<string, mixed>>|null $phrases
     */
    public static function shouldRebuildStoredPhrases(?array $phrases): bool
    {
        if (!is_array($phrases) || $phrases === []) {
            return true;
        }

        if (count($phrases) > 600) {
            return true;
        }

        $singleSite = 0;
        $total = 0;
        foreach ($phrases as $row) {
            if (!is_array($row)) {
                continue;
            }

            $total++;
            if ((int) ($row['numberOccurrences'] ?? 0) < 2) {
                $singleSite++;
            }
        }

        if ($total > 0 && $singleSite > (int) floor($total / 2)) {
            return true;
        }

        foreach ($phrases as $row) {
            if (!is_array($row)) {
                continue;
            }

            return !isset($row['tfidfTop']);
        }

        return true;
    }

    /**
     * @param array<string, array<string, mixed>> $sites
     * @return array<string, mixed>
     */
    public static function mainPageFromSites(array $sites): array
    {
        foreach ($sites as $page) {
            if (!empty($page['mainPage']) && is_array($page)) {
                return $page;
            }
        }

        return [];
    }

    /**
     * Пересчёт IDF/score для сохранённых результатов (fix: countNotIgnoredSites был 0 при первичном расчёте).
     */
    public static function recalculateStoredTfidf(array &$data): void
    {
        if (empty($data['sites']) || !is_array($data['sites'])) {
            return;
        }

        $documentCount = self::competitorDocumentCountFromSites($data['sites']);

        if (!empty($data['unigram_table']) && is_array($data['unigram_table'])) {
            foreach ($data['unigram_table'] as &$wordForm) {
                foreach ($wordForm as $wordKey => &$word) {
                    if ($wordKey === 'total' || !is_array($word)) {
                        continue;
                    }

                    $documentFrequency = max(1, (int) ($word['numberOccurrences'] ?? 1));
                    $tf = (float) ($word['tf'] ?? 0);
                    $word['idf'] = TfidfMetrics::inverseDocumentFrequency($documentCount, $documentFrequency);
                    $word['score'] = TfidfMetrics::score($tf, $word['idf']);
                }
                unset($word);

                if (!isset($wordForm['total']) || !is_array($wordForm['total'])) {
                    continue;
                }

                $documentFrequency = max(1, (int) count($wordForm['total']['occurrences'] ?? []));
                $tf = (float) ($wordForm['total']['tf'] ?? 0);
                $wordForm['total']['numberOccurrences'] = $documentFrequency;
                $wordForm['total']['idf'] = TfidfMetrics::inverseDocumentFrequency($documentCount, $documentFrequency);
                $wordForm['total']['score'] = TfidfMetrics::score($tf, $wordForm['total']['idf']);
            }
            unset($wordForm);
        }

        if (!empty($data['phrases']) && is_array($data['phrases'])) {
            foreach ($data['phrases'] as &$phrase) {
                if (!is_array($phrase)) {
                    continue;
                }

                $documentFrequency = max(1, (int) ($phrase['numberOccurrences'] ?? 1));
                $tf = (float) ($phrase['tf'] ?? 0);
                $phrase['idf'] = TfidfMetrics::inverseDocumentFrequency($documentCount, $documentFrequency);
                $phrase['score'] = TfidfMetrics::score($tf, $phrase['idf']);
            }
            unset($phrase);
        }
    }

    public static function enrichUnigramHybridMetrics(array &$data): void
    {
        if (empty($data['unigram_table']) || !is_array($data['unigram_table'])) {
            return;
        }

        $corpusZones = HybridRelevanceMetrics::corpusZoneStatsFromData($data);

        foreach ($data['unigram_table'] as &$wordForm) {
            foreach ($wordForm as $wordKey => &$word) {
                if ($wordKey === 'total' || !is_array($word)) {
                    continue;
                }

                HybridRelevanceMetrics::applyTableTfidfToWordStats($word, $corpusZones);
                HybridRelevanceMetrics::applyTableBm25ToWordStats($word, $corpusZones);
            }
            unset($word);

            if (!isset($wordForm['total']) || !is_array($wordForm['total'])) {
                continue;
            }

            HybridRelevanceMetrics::applyTableTfidfToWordStats($wordForm['total'], $corpusZones);
            HybridRelevanceMetrics::applyTableBm25ToWordStats($wordForm['total'], $corpusZones);
        }
        unset($wordForm);
    }

    private function hybridCorpusStats(): array
    {
        return $this->hybridCorpusZoneStats();
    }

    private function hybridCorpusZoneStats(): array
    {
        $mainPageText = trim(($this->mainPage['html'] ?? '') . ' ' . ($this->mainPage['hiddenText'] ?? ''));
        $mainPageLink = trim(strip_tags($this->mainPage['linkText'] ?? ''));

        return [
            'mainPageWords' => max(1, (int) $this->countWordsInMyPage),
            'mainPageTextWords' => max(1, HybridRelevanceMetrics::countWordsInText($mainPageText)),
            'mainPageLinkWords' => max(1, HybridRelevanceMetrics::countWordsInText($mainPageLink)),
            'competitorCorpusWords' => max(1, HybridRelevanceMetrics::countWordsInText(trim($this->competitorsTextAndLinks))),
            'competitorTextWords' => max(1, HybridRelevanceMetrics::countWordsInText(trim($this->competitorsText ?? ''))),
            'competitorLinkWords' => max(1, HybridRelevanceMetrics::countWordsInText(trim($this->competitorsLinks ?? ''))),
            'avgCompetitorDocWords' => $this->countWords / max(1, $this->competitorDocumentCount()),
            'documentCount' => $this->competitorDocumentCount(),
            'competitorSiteCount' => max(1, $this->competitorDocumentCount()),
        ];
    }

    public static function competitorDocumentCountFromSites(array $sites): int
    {
        $count = 0;
        foreach ($sites as $site) {
            if (!empty($site['ignored']) || !empty($site['mainPage'])) {
                continue;
            }
            $count++;
        }

        return max(1, $count);
    }

    private function competitorDocumentCount(): int
    {
        return self::competitorDocumentCountFromSites($this->sites);
    }

    /**
     * Части ответа /get-details-history — чтобы не парсить 3+ MB JSON одним куском в браузере.
     */
    public static function historyDetailsPart(array $data, string $part): array
    {
        switch ($part) {
            case 'meta':
                return [
                    'history_id' => $data['history_id'] ?? null,
                    'cleaning' => $data['cleaning'] ?? false,
                    'avg' => $data['avg'] ?? null,
                    'main_page' => $data['main_page'] ?? null,
                    'recommendations' => $data['recommendations'] ?? [],
                    'average_values' => $data['average_values'] ?? null,
                    'avg_coverage_percent' => $data['avg_coverage_percent'] ?? null,
                ];
            case 'sites':
                return [
                    'sites' => $data['sites'] ?? [],
                ];
            case 'tables':
                return [
                    'unigram_table' => $data['unigram_table'] ?? [],
                    'phrases' => $data['phrases'] ?? [],
                    'clouds_competitors' => $data['clouds_competitors'] ?? null,
                    'clouds_main_page' => $data['clouds_main_page'] ?? null,
                    'tf_comp_clouds' => $data['tf_comp_clouds'] ?? null,
                ];
            default:
                return $data;
        }
    }

    public static function uncompressItem($item) {
        return json_decode(gzuncompress(base64_decode($item)), true);
    }
}
