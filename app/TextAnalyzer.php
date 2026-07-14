<?php

namespace App;

use App\Classes\SimpleHtmlDom\HtmlDocument;
use App\Support\TextAnalyzerStopWords;
use App\Support\TfidfMetrics;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class TextAnalyzer extends Model
{
    /** Точек на графике Ципфа (топ слов по частоте). */
    public const ZIPF_GRAPH_TOP_WORDS = 20;

    protected $guarded = [];

    protected $table = 'text_analyser_count_checks';

    public static function curlInitV2($link)
    {
        $refers = ['google.com', 'yandex.ru'];

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_COOKIEJAR, '/tmp/cookies.txt');
        curl_setopt($curl, CURLOPT_COOKIEFILE, '/tmp/cookies.txt');
        curl_setopt($curl, CURLOPT_COOKIE, 'beget=begetok; path=/; realauth=SvBD85dINu3; expires=Sat, 25 Feb 2030 02:16:43 GMT; SameSite=Lax');
        curl_setopt($curl, CURLOPT_URL, $link);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_HEADER, false);
        curl_setopt($curl, CURLOPT_FAILONERROR, true);
        curl_setopt($curl, CURLOPT_AUTOREFERER, true);
        curl_setopt($curl, CURLOPT_ENCODING, '');
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 4);
        curl_setopt($curl, CURLOPT_TIMEOUT, 5);
        curl_setopt($curl, CURLOPT_REFERER, $refers[array_rand($refers)]);

        $headers = curl_getinfo($curl);
        $html = curl_exec($curl);

        if (preg_match('/<meta[^>]+charset=["\']?([\w-]+)["\']?/i', $html, $matches)) {
            $encoding = strtoupper($matches[1]);
        } else {
            // Если не найдено, используем UTF-8 по умолчанию
            $encoding = 'UTF-8';
        }

        if ($encoding !== 'UTF-8') {
            $html = mb_convert_encoding($html, 'UTF-8', $encoding);
            $html = str_ireplace($encoding, 'UTF-8', $html);
        }

        return $html;
    }

    public static function curlInit($link)
    {
        $refers = ['google.com', 'yandex.ru'];

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_COOKIEJAR, '/tmp/cookies.txt');
        curl_setopt($curl, CURLOPT_COOKIEFILE, '/tmp/cookies.txt');
        curl_setopt($curl, CURLOPT_COOKIE, 'beget=begetok; path=/; realauth=SvBD85dINu3; expires=Sat, 25 Feb 2030 02:16:43 GMT; SameSite=Lax');
        curl_setopt($curl, CURLOPT_URL, $link);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_HEADER, false);
        curl_setopt($curl, CURLOPT_FAILONERROR, true);
        curl_setopt($curl, CURLOPT_AUTOREFERER, true);
        curl_setopt($curl, CURLOPT_ENCODING, 'UTF-8');
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 4);
        curl_setopt($curl, CURLOPT_TIMEOUT, 5);
        curl_setopt($curl, CURLOPT_REFERER, $refers[array_rand($refers)]);

        return TextAnalyzer::curlConnect($curl);
    }

    public static function curlConnect($curl)
    {
        $userAgents = [
            //Mozilla Firefox
            'Mozilla/5.0 (Windows NT 6.3; Win64; x64; rv:87.0) Gecko/20100101 Firefox/87.0',
            'Mozilla/5.0 (Windows NT 10.0; rv:87.0) Gecko/20100101 Firefox/87.0',
            //opera
            'Mozilla/5.0 (Windows NT 6.1; Win64; x64) AppleWebKit/537.43 (KHTML, like Gecko) Chrome/93.0.4577.82 Safari/537.36 OPR/79.0.4143.72',
            'Mozilla/5.0 (Windows NT 6.3) AppleWebKit/537.43 (KHTML, like Gecko) Chrome/93.0.4577.82 Safari/537.36 OPR/79.0.4143.72',
            // chrome
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/93.0.4577.63 Safari/537.36',
            'Mozilla/5.0 (Windows NT 6.1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/92.0.4515.159 Safari/537.36'
        ];

        foreach ($userAgents as $agent) {
            curl_setopt($curl, CURLOPT_USERAGENT, $agent);

            $html = curl_exec($curl);
            $headers = curl_getinfo($curl);
            if ($headers['http_code'] == 200 && $html) {

                $contentType = strtolower($headers['content_type']);
                if (strpos($contentType, 'application/pdf') !== false) {
                    // Пропустить обработку PDF-страниц
                    return '';
                }

                $html = preg_replace('//i', '', $html);
                break;
            }
        }

        curl_close($curl);

        try {
            $contentType = trim(str_replace('text/html;', '', $headers['content_type']));
            $contentType = trim(str_replace('charset=', '', $contentType));
            $html = mb_convert_encoding($html, "utf-8", $contentType);
        } catch (\Exception $exception) {

        }

        return $html;
    }

    public static function analyze($string, $request): array
    {
        $data = '';
        $alt = '';
        $title = '';
        $html = mb_strtolower($string);

        if ($request['noIndex'] ?? false) {
            $html = TextAnalyzer::removeNoindexText($html);
        }

        $link = TextAnalyzer::getLinkText($html);

        if ($request['hiddenText'] ?? false) {
            $title = TextAnalyzer::getHiddenText($html, "<.*?title=\"(.*?)\".*>");
            $alt = TextAnalyzer::getHiddenText($html, "<.*?alt=\"(.*?)\".*>");
            $data = TextAnalyzer::getHiddenText($html, "<.*?data-text=\"(.*?)\".*>");
        }

        $html = TextAnalyzer::clearHTMLFromLinks($html);
        $text = TextAnalyzer::deleteEverythingExceptCharacters($html);

        if (self::shouldExcludeConjunctionsPrepositionsPronouns($request)) {
            $text = TextAnalyzer::removeConjunctionsPrepositionsPronouns($text);
            $title = TextAnalyzer::removeConjunctionsPrepositionsPronouns($title);
            $alt = TextAnalyzer::removeConjunctionsPrepositionsPronouns($alt);
            $data = TextAnalyzer::removeConjunctionsPrepositionsPronouns($data);
            $link = TextAnalyzer::removeConjunctionsPrepositionsPronouns($link);
        }

        if (self::shouldApplyCustomWordExclusion($request)) {
            $excludeList = (string) $request['listWords'];
            $text = TextAnalyzer::removeWords($excludeList, $text);
            $title = TextAnalyzer::removeWords($excludeList, $title);
            $alt = TextAnalyzer::removeWords($excludeList, $alt);
            $data = TextAnalyzer::removeWords($excludeList, $data);
            $link = TextAnalyzer::removeWords($excludeList, $link);
        }

        $total = trim($text . ' ' . $alt . ' ' . $title . ' ' . $data);
        $generalText = trim(preg_replace('/\s+/u', ' ', $total . ' ' . $link));
        $countSpaces = $generalText === '' ? 0 : substr_count($generalText, ' ');
        $length = mb_strlen($generalText);
        $wordParts = $generalText === ''
            ? []
            : array_values(array_filter(explode(' ', $generalText), static function ($word) {
                return $word !== '';
            }));

        $response['general'] = [
            'textLength' => $length,
            'countSpaces' => $countSpaces,
            'lengthWithOutSpaces' => $length - $countSpaces,
            'countWords' => count($wordParts),
        ];

        $excludePhraseStopWords = self::shouldExcludeConjunctionsPrepositionsPronouns($request);

        $response['totalWords'] = TextAnalyzer::analyzeWords($total, $link);
        if ($excludePhraseStopWords) {
            $response['totalWords'] = TextAnalyzer::filterStopWordsFromStats($response['totalWords']);
        }
        if (self::shouldApplyCustomWordExclusion($request)) {
            $response['totalWords'] = TextAnalyzer::filterExcludedFromWordStats(
                $response['totalWords'],
                (string) $request['listWords']
            );
        }
        $response['phrases'] = TextAnalyzer::searchPhrases(trim($total . ' ' . $link), $excludePhraseStopWords);
        if (self::shouldApplyCustomWordExclusion($request)) {
            $response['phrases'] = TextAnalyzer::filterExcludedFromPhrases(
                $response['phrases'],
                (string) $request['listWords']
            );
        }
        $response['clouds'] = [
            'text' => TextAnalyzer::prepareCloudFromAnalyzedWords($response['totalWords'], 'inText', 100),
            'links' => TextAnalyzer::prepareCloudFromAnalyzedWords($response['totalWords'], 'inLink', 100),
            'both' => TextAnalyzer::prepareCloudFromAnalyzedWords($response['totalWords'], 'total', 100),
        ];
        $response['graph'] = TextAnalyzer::prepareDataGraph($response['totalWords']);

        if (empty($request['demo'])) {
            TariffSetting::saveStatistics(TextAnalyzer::class, Auth::id());
        }

        return $response;
    }

    public static function loadHtml(string $html): \DOMDocument
    {
        $dom = new \DOMDocument();

        $dom->encoding = 'utf-8';

        $html = str_starts_with($html, "\xEF\xBB\xBF") ? $html : "\xEF\xBB\xBF" . $html;

        $dom->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        return $dom;
    }

    public static function saveHtml(\DOMDocument $dom): string
    {
        return $dom->saveHTML( $dom->documentElement );
    }

    public static function deleteEverythingExceptCharacters($html)
    {
        if(!$html)
            return "";

        $html = str_replace('><', '> <', $html);
        $html = str_replace('&nbsp;', ' ', $html);

        $dom = TextAnalyzer::loadHtml($html);

        $array = preg_split('/[^a-zA-ZА-Яа-яЁё]+/u', $dom->textContent);

        return implode(' ', $array);
    }

    protected static function removeNumbersWithoutLetters($text): string
    {
        $words = explode(' ', $text);
        $result = [];

        foreach ($words as $word) {
            if (TextAnalyzer::hasLetters($word) || TextAnalyzer::hasLettersNearby($word)) {
                $result[] = $word;
            }
        }

        return implode(' ', $result);
    }

    protected static function hasLetters($word)
    {
        return preg_match('/[a-zA-Zа-яА-Я]/u', $word);
    }

    protected static function hasLettersNearby($word): bool
    {
        $length = strlen($word);

        for ($i = 0; $i < $length; $i++) {
            if (TextAnalyzer::hasLetters(substr($word, $i, 1))) {
                return true;
            }
        }

        return false;
    }

    public static function removeStylesAndScripts(string $html): string
    {
        if(strlen($html) < 1)
            return "";

        $dom = TextAnalyzer::loadHtml(mb_strtolower($html));

        $removeTags = [
            'script',
            'link',
            'style',
            'path',
            'noscript',
            'svg',
            'img',
            'title',
        ];

        foreach($removeTags as $tag)
        {
            foreach (iterator_to_array($dom->getElementsByTagName($tag)) as $item)
                $item->parentNode->removeChild($item);
        }

        return TextAnalyzer::saveHtml($dom);
    }

    public static function removeNoindexText($html)
    {
        $document = new HtmlDocument();
        $document->load(mb_strtolower($html));
        $document->removeElements('noindex');

        return $document->outertext;
    }

    public static function removeConjunctionsPrepositionsPronouns($text)
    {
        $pronouns = [
            'я', 'мы', 'ты', 'вы', 'он', 'она', 'оно', 'они', 'себя', 'мой', 'наш', 'твой', 'ваш', 'свой',
            'кто', 'что', 'какой', 'каков', 'который', 'чей', 'сколько', 'этот', 'тот', 'такой', 'таков',
            'столько', 'сам', 'самый', 'весь', 'вся', 'всё', 'все', 'всякий', 'каждый', 'любой', 'иной',
            'никто', 'ничто', 'никакой', 'ничей', 'никоторый', 'некого', 'нечего', 'некто', 'нечто',
            'некоторый', 'кто-то', 'сколько-то', 'что-либо', 'кое-кто', 'какой-то', 'какой-либо',
            'кое-какой', 'чей-то', 'чей-нибудь',
            'i', 'you', 'she', 'he', 'it', 'we ', 'you', 'they', 'me', 'you', 'her',
            'him', 'it', 'us', 'you', 'them', 'my', 'your', 'her', 'his', 'its', 'our',
            'your', 'their', 'mine', 'yours', 'hers', 'his', 'its', 'our', 'your',
            'their', 'yours', 'hers', 'ours', 'yours', 'theirs', 'myself', 'yourself',
            'herself', 'himself', 'itself', 'ourselves', 'yourselves', 'themselves',
        ];
        $preposition = [
            'без', 'безо', 'близ', 'в', 'во', 'вместо', 'вне', 'для', 'до', 'за', 'из', 'по',
            'изо', 'из-за', 'из-под', 'к', 'не', 'ко', 'кроме', 'между', 'меж', 'на', 'над',
            'о', 'об', 'обо', 'от', 'ото', 'перед', 'передо', 'пред', 'пред', 'пo', 'под',
            'подо', 'при', 'про', 'ради', 'с', 'со', 'сквозь', 'среди', 'у', 'через', 'чрез',
            'aboard', 'about', 'above', 'absent', 'across', 'before', 'after', 'against', 'along',
            'amid', 'amidst', 'among', 'amongst', 'around', 'as', 'aside', 'aslant', 'astride', 'at',
            'athwart', 'atop', 'bar', 'before', 'behind', 'below', 'beneath', 'beside', 'besides', 'between',
            'betwixt', 'beyond', 'but', 'by', 'circa', 'despite', 'down', 'except', 'for', 'from', 'given',
            'in', 'inside', 'into', 'like', 'minus', 'near', 'neath', 'next', 'notwithstanding', 'of', 'off',
            'on', 'opposite', 'out', 'outside', 'over', 'pace', 'per', 'plus', 'post', 'pro', 'qua', 'round',
            'save', 'since', 'than', 'through', 'till', 'times', 'to', 'toward', 'towards', 'under',
            'underneath', 'unlike', 'until', 'up', 'versus', 'via', 'vice', 'with', 'without', 'barring',
            'concerning', 'considering', 'depending', 'during', 'granted', 'excepting', 'excluding', 'failing',
            'following', 'including', 'past', 'pending', 'regarding', 'alongside', 'within', 'outside', 'upon',
            'onto', 'throughout', 'wherewith', 'according to', 'ahead of', 'apart from', 'as far as', 'as for',
            'as of', 'as per', 'as regards', 'aside from', 'as well as', 'away from',
            'because of', 'by force of', 'by means of', 'by virtue of', 'close to', 'contrary to', 'due to',
            'except for', 'far from', 'for the sake of', 'in accordance with', 'in addition to', 'in case of',
            'in connection with', 'in consequence of', 'in front of', 'in spite of', 'in the back of',
            'in the course of', 'in the event of', 'in the middle of', 'inside of', 'instead of', 'in view of',
            'near to', 'next to', 'on account of', 'on top of', 'opposite to', 'out of	из,', 'outside of',
            'owing to', 'thanks to', 'up to', 'with regard to', 'with respect to',
        ];
        $conjunctions = [
            'а', 'а вдобавок', 'а именно', 'а также', 'а то', 'благодаря тому что', 'благо', 'буде',
            'будто', 'вдобавок', 'в результате чего', 'в результате того что', 'в связи с тем что',
            'в силу того что', 'в случае если', 'в то время как', 'в том случае если', 'в силу чего',
            'ввиду того что', 'вопреки тому что', 'вроде того как', 'вследствие чего', 'вследствие того что',
            'да вдобавок', 'да еще', 'да и', 'да и то', 'дабы', 'даже', 'даром что', 'для того чтобы',
            'же', 'едва', 'ежели', 'если', 'если бы', 'затем чтобы', 'затем что', 'зато', 'зачем', 'и',
            'и все же', 'и значит', 'а именно', 'и поэтому', 'и притом',
            'и все-таки', 'и следовательно', 'и то', 'и тогда времени', 'и еще', 'ибо', 'и вдобавок',
            'из-за того что', 'или', 'или, или', 'кабы', 'как', 'Как скоро', 'как будто',
            'как если бы', 'как словно', 'как только', 'кактак и', 'как-то?', 'когда',
            'коли', 'к тому же', 'кроме того', 'либо', 'лишь', 'лишь бы', 'лишь только', 'между тем как',
            'нежели', 'не столько, сколько', 'не то, не то', 'не только не, но и',
            'не только, но и', 'не только., а и', 'не только, но даже', 'невзирая на то что',
            'независимо от того что', 'несмотря на то что', 'но', 'однако', 'особенно',
            'оттого', 'оттого что', 'отчего', 'перед тем как', 'по мере того как', 'по причине того что',
            'подобно тому как', 'пока', 'покамест', 'покуда', 'пока не', 'после того как',
            'поскольку', 'потому', 'потому что', 'почему', 'прежде чем', 'при всем том что',
            'при условии что', 'притом', 'причем', 'пускай', 'пусть', 'ради того чтобы', 'раз',
            'раньше чем', 'с тем чтобы', 'с тех пор как', 'словно', 'так как', 'так что', 'также',
            'тем более что', 'тогда как', 'то есть', 'тоже', 'только', 'только бы', 'только что',
            'только лишь', 'только чуть', 'точно', 'хотя', 'хотя и, но', 'чем', 'что', 'чтоб', 'чтобы',
            'also', 'and', 'as', 'as far as', 'as long as', 'as soon as', 'as well as', 'because',
            'because of', 'but', 'however', 'if', 'in case', 'in order', 'moreover', 'nevertheless',
            'no matter where', 'no matter how', 'no matter when', 'no matter who', 'no matter why',
            'now that', 'once', 'on the contrary', 'on the other hand', 'or', 'otherwise', 'not so as',
            'still', 'than', 'that', 'therefore', 'although', 'thus', 'unless', 'what', 'while', 'yet',
            'not', 'for', 'against', 'like', 'unlike', 'with', 'without', 'within', 'owing to', 'meanwhile',
            'from time to time', 'beyond', 'whereas', 'at least', 'at last', 'as if, as though', 'on condition',
        ];
        $listWords = array_merge($pronouns, $preposition, $conjunctions);

        foreach ($listWords as $listWord) {
            $text = str_replace(' ' . $listWord . ' ', ' ', $text);
        }

        return $text;
    }

    public static function shouldApplyCustomWordExclusion(array $request): bool
    {
        if (empty($request['removeWords'])) {
            return false;
        }

        return trim((string) ($request['listWords'] ?? '')) !== '';
    }

    public static function shouldExcludeConjunctionsPrepositionsPronouns(array $request): bool
    {
        if (!array_key_exists('conjunctionsPrepositionsPronouns', $request)) {
            return true;
        }

        $value = $request['conjunctionsPrepositionsPronouns'];

        return !in_array($value, [false, 0, '0', 'false', 'off', ''], true);
    }

    /**
     * Строка списка = одно исключение (слово или фраза целиком), как в lk.redbox.su.
     *
     * @return string[]
     */
    public static function parseExcludeWordList(string $listWords): array
    {
        $normalized = str_replace("\r\n", "\n", trim($listWords));
        if ($normalized === '') {
            return [];
        }

        $entries = [];
        foreach (explode("\n", $normalized) as $line) {
            $line = mb_strtolower(trim($line), 'UTF-8');
            if ($line === '') {
                continue;
            }
            $line = preg_replace('/\s+/u', ' ', $line);
            if (is_string($line) && $line !== '') {
                $entries[] = $line;
            }
        }

        usort($entries, static function ($a, $b) {
            return mb_strlen($b) <=> mb_strlen($a);
        });

        return array_values(array_unique($entries));
    }

    public static function removeWords($listWords, $text): string
    {
        $text = trim((string) $text);
        if ($text === '') {
            return '';
        }

        $text = ' ' . preg_replace('/\s+/u', ' ', $text) . ' ';
        foreach (self::parseExcludeWordList((string) $listWords) as $entry) {
            if ($entry === '') {
                continue;
            }
            $quoted = preg_quote($entry, '/');
            $text = preg_replace('/(?<=\s)' . $quoted . '(?=\s)/iu', ' ', $text);
            $text = preg_replace('/\s+/u', ' ', $text);
        }

        return trim($text);
    }

    /**
     * @param array<int|string, array<string, mixed>> $phrases
     * @return array<int|string, array<string, mixed>>
     */
    public static function filterExcludedFromPhrases(array $phrases, string $listWords): array
    {
        $exclude = self::parseExcludeWordList($listWords);
        if (!$exclude) {
            return $phrases;
        }

        if (self::isListArray($phrases)) {
            return array_values(array_filter($phrases, static function ($row) use ($exclude) {
                if (!is_array($row)) {
                    return false;
                }

                $phrase = mb_strtolower(trim((string) ($row['phrase'] ?? '')), 'UTF-8');

                return $phrase !== '' && !self::phraseIsExcludedByList($phrase, $exclude);
            }));
        }

        $filtered = [];
        foreach ($phrases as $phraseKey => $row) {
            if (!is_array($row)) {
                continue;
            }

            $phrase = mb_strtolower(trim((string) $phraseKey), 'UTF-8');
            if ($phrase === '' || self::phraseIsExcludedByList($phrase, $exclude)) {
                continue;
            }

            $filtered[$phraseKey] = $row;
        }

        return $filtered;
    }

    private static function phraseIsExcludedByList(string $phrase, array $exclude): bool
    {
        foreach ($exclude as $item) {
            if ($item === '') {
                continue;
            }
            if (strpos($item, ' ') !== false) {
                if ($phrase === $item) {
                    return true;
                }
                continue;
            }
            $tokens = preg_split('/\s+/u', $phrase, -1, PREG_SPLIT_NO_EMPTY) ?: [];
            if (in_array($item, $tokens, true)) {
                return true;
            }
        }

        return false;
    }

    private static function isListArray(array $array): bool
    {
        if ($array === []) {
            return true;
        }

        return array_keys($array) === range(0, count($array) - 1);
    }

    /**
     * @param array<int, array<string, mixed>> $words
     * @return array<int, array<string, mixed>>
     */
    public static function filterExcludedFromWordStats(array $words, string $listWords): array
    {
        $exclude = array_flip(self::parseExcludeWordList($listWords));
        if (!$exclude) {
            return $words;
        }

        $filtered = [];
        foreach ($words as $row) {
            $lemma = mb_strtolower((string) ($row['text'] ?? ''), 'UTF-8');
            if (isset($exclude[$lemma])) {
                continue;
            }

            $skip = false;
            foreach ($row['wordForms'] ?? [] as $zoneForms) {
                if (!is_array($zoneForms)) {
                    continue;
                }
                foreach ($zoneForms as $formEntry) {
                    if (!is_array($formEntry)) {
                        continue;
                    }
                    foreach ($formEntry as $form => $count) {
                        if (isset($exclude[mb_strtolower((string) $form, 'UTF-8')])) {
                            $skip = true;
                            break 3;
                        }
                    }
                }
            }

            if (!$skip) {
                $filtered[] = $row;
            }
        }

        return array_values($filtered);
    }

    public static function mbStrReplace($search, $replace, $string)
    {
        $charset = mb_detect_encoding($string);

        $unicodeString = iconv($charset, "UTF-8", $string);

        return str_replace($search, $replace, $unicodeString);
    }

    public static function prepareCloud($string, int $minLength = 2, bool $excludeStopWords = false): array
    {
        return self::cloudListFromCounts(self::cloudWordCounts($string, $minLength, $excludeStopWords));
    }

    /**
     * Облако из той же статистики, что таблица «Общий анализ слов» (поле inText / inLink / total).
     *
     * @param array<int, array<string, mixed>> $analyzedWords
     * @return array<int, array{text: string, weight: int}>
     */
    public static function prepareCloudFromAnalyzedWords(array $analyzedWords, string $zone, int $limit = 80): array
    {
        $allowed = ['inText', 'inLink', 'total'];
        if (!in_array($zone, $allowed, true)) {
            $zone = 'total';
        }

        $counts = [];
        foreach ($analyzedWords as $row) {
            if (!is_array($row)) {
                continue;
            }
            $text = trim((string) ($row['text'] ?? ''));
            if ($text === '' || mb_strlen($text) < 2 || $text === 'т') {
                continue;
            }
            $weight = (int) ($row[$zone] ?? 0);
            if ($weight <= 0) {
                continue;
            }
            $counts[$text] = $weight;
        }

        $list = self::cloudListFromCounts($counts, $limit);
        unset($list['count']);

        return array_values(array_filter($list, 'is_array'));
    }

    /** Список слов для UI (без ключа count — только массив). */
    public static function prepareCloudForUi(string $string, int $minLength = 2, bool $excludeStopWords = false): array
    {
        $list = self::prepareCloud($string, $minLength, $excludeStopWords);
        unset($list['count']);

        return array_values(array_filter($list, 'is_array'));
    }

    /** Облако для зоны «текст + ссылки»: сумма частот по обеим зонам. */
    public static function prepareCloudCombined(string $text, string $link, int $minLength = 2, bool $excludeStopWords = false): array
    {
        $textCounts = self::cloudWordCounts($text, $minLength, $excludeStopWords);
        $linkCounts = self::cloudWordCounts($link, $minLength, $excludeStopWords);
        if ($textCounts === [] && $linkCounts === []) {
            return [];
        }
        $merged = $textCounts;
        foreach ($linkCounts as $word => $count) {
            $merged[$word] = ($merged[$word] ?? 0) + $count;
        }

        return self::cloudListFromCounts($merged);
    }

    public static function prepareCloudCombinedForUi(string $text, string $link, int $minLength = 2, bool $excludeStopWords = false): array
    {
        $list = self::prepareCloudCombined($text, $link, $minLength, $excludeStopWords);
        unset($list['count']);

        return array_values(array_filter($list, 'is_array'));
    }

    /**
     * @return array<string, int>
     */
    private static function cloudWordCounts(string $string, int $minLength, bool $excludeStopWords = false): array
    {
        $string = trim($string);
        if ($string === '') {
            return [];
        }
        $counts = [];
        foreach (preg_split('/\s+/u', $string, -1, PREG_SPLIT_NO_EMPTY) as $word) {
            if ($word === '' || mb_strlen($word) < $minLength || $word === 'т') {
                continue;
            }
            if ($excludeStopWords && TextAnalyzerStopWords::isPhraseStopWord($word)) {
                continue;
            }
            $counts[$word] = ($counts[$word] ?? 0) + 1;
        }

        return $counts;
    }

    /**
     * Убрать служебные слова из таблицы/графика (тот же список, что для фраз).
     *
     * @param array<int, array<string, mixed>> $words
     * @return array<int, array<string, mixed>>
     */
    public static function filterStopWordsFromStats(array $words): array
    {
        return array_values(array_filter($words, static function ($row) {
            $text = mb_strtolower(trim((string) ($row['text'] ?? '')));
            if ($text === '' || $text === 'т') {
                return false;
            }

            return !TextAnalyzerStopWords::isPhraseStopWord($text);
        }));
    }

    /**
     * @param array<string, int> $counts
     * @return array<int, array{text: string, weight: int}>
     */
    private static function cloudListFromCounts(array $counts, int $limit = 150): array
    {
        if ($counts === []) {
            return [];
        }
        arsort($counts);
        $words = [];
        foreach (array_slice($counts, 0, $limit, true) as $text => $weight) {
            $words[] = [
                'text' => $text,
                'weight' => $weight,
            ];
        }

        // Совместимость с relevance-analysis (arrayToObj ожидает ключ count).
        $words['count'] = count($words);

        return $words;
    }

    public static function analyzeWords($textWords, $linkWords): array
    {
        $textWords = explode(' ', $textWords);
        $linkWords = explode(' ', $linkWords);
        $totalWords = array_merge($linkWords, $textWords);

        $text = TextAnalyzer::countWordsInText($textWords);
        $link = TextAnalyzer::countWordsInLink($linkWords);
        $result = TextAnalyzer::mergeTextAndLinks($text, $link);

        $result = TextAnalyzer::calculateTFIDF($result, $totalWords, 'inLink');

        return TextAnalyzer::calculateTFIDF($result, $totalWords, 'inText');
    }

    public static function searchPhrases($string, bool $excludeStopWords = true)
    {
        $array = preg_split('/\s+/u', trim($string), -1, PREG_SPLIT_NO_EMPTY);
        $generalCount = count($array);
        if ($generalCount < 2) {
            return [];
        }

        $phrases = [];
        for ($i = 1; $i < $generalCount; $i++) {
            $left = $array[$i - 1];
            $right = $array[$i];
            if (!self::isMeaningfulPhraseToken($left, $excludeStopWords) || !self::isMeaningfulPhraseToken($right, $excludeStopWords)) {
                continue;
            }
            $phrases[] = $left . ' ' . $right;
        }

        if ($phrases === []) {
            return [];
        }

        $phraseCounts = array_count_values($phrases);
        $result = [];
        foreach ($phraseCounts as $phrase => $count) {
            $result[] = [
                'phrase' => $phrase,
                'count' => $count,
                'density' => round((100 / $generalCount) * $count, 2),
            ];
        }

        usort($result, static function ($a, $b) {
            return $b['count'] <=> $a['count'] ?: strcmp($a['phrase'], $b['phrase']);
        });

        return array_slice($result, 0, 150);
    }

    /**
     * Значимое слово для биграммы: не служебная часть речи и не короче 2 букв.
     */
    private static function isMeaningfulPhraseToken(string $word, bool $excludeStopWords): bool
    {
        $word = mb_strtolower(trim($word));
        if ($word === '') {
            return false;
        }
        // Частица «т» не участвует в биграммах
        if ($word === 'т') {
            return false;
        }
        if ($excludeStopWords) {
            if (mb_strlen($word) < 2) {
                return false;
            }

            return !TextAnalyzerStopWords::isPhraseStopWord($word);
        }

        return mb_strlen($word) >= 1;
    }

    public static function getHiddenText($html, $regex)
    {
        $hiddenText = '';
        preg_match_all($regex, $html, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            if ($match[1] != "") {
                $hiddenText .= $match[1] . ' ';
            }
        }
        return TextAnalyzer::deleteEverythingExceptCharacters($hiddenText);
    }

    public static function getLinkText($html)
    {
        if(!$html)
            return "";

        $linkText = '';
        $html = str_replace("article", "div", $html);
        $html = preg_replace('| +|', ' ', $html);
        $html = str_replace("\n", " ", $html);
        preg_match_all('(<a.*?href=["\']?(.*?)([\'"].*?>(.*?)</a>))', $html, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $match = strip_tags($match[3]);
            if ($match !== "") {
                $linkText .= $match . " ";
            }
        }

        return TextAnalyzer::deleteEverythingExceptCharacters($linkText);
    }

    public static function prepareDataGraph($array): array
    {
        $result = [];
        $i = 0;
        foreach ($array as $item) {
            $result[] = [
                'x' => $i + 1,
                'y' => $item['total'],
                'label' => $item['text'],
                'rank' => $i + 1,
            ];
            if ($i >= self::ZIPF_GRAPH_TOP_WORDS - 1) {
                break;
            }
            $i++;
        }

        return $result;
    }

    public static function clearHTMLFromLinks($html): string
    {
        $html = str_replace("article", "div", $html);
        $html = str_replace(["\n", "\r", "\t"], " ", $html);
        $html = preg_replace("| +|", ' ', $html);

        preg_match_all('(<a.*?>.*?</a>)', $html, $matches, PREG_SET_ORDER);
        foreach ($matches as $items) {
            $html = str_replace($items[0], "", $html);
        }

        return trim($html);
    }

    public static function countWordsInText($text): array
    {
        $wordForms = TextAnalyzer::searchWordForms($text);
        $textAr = array_count_values($text);
        arsort($textAr);
        $result = [];

        foreach ($wordForms as $key => $wordForm) {
            $extra = $textAr[$key];
            $result[$key] = [
                'text' => $key,
                'inText' => $textAr[$key],
                'inLink' => 0,
                'total' => $textAr[$key],
                'wordForms' => ['inText' => $wordForm]
            ];
            foreach ($wordForm as $item) {
                $count = array_shift($item);
                $result[$key]['total'] += $count;
                $result[$key]['inText'] += $count;
            }
            $result[$key]['total'] -= $extra;
            $result[$key]['inText'] -= $extra;
        }

        return $result;
    }

    public static function searchWordForms($array): array
    {
        $array = array_count_values($array);
        arsort($array);

        $morphy = new Morphy();
        $candidates = [];
        foreach (array_keys($array) as $key) {
            $forms = $morphy->baseForms($key);
            $candidates[$key] = $forms !== [] ? $forms : [mb_strtolower((string) $key, 'UTF-8')];
        }

        $resolvedRoots = $morphy->resolveRootsFromCandidates($candidates);
        $groups = [];

        foreach ($array as $key => $item) {
            $root = $resolvedRoots[$key] ?? mb_strtolower((string) $key, 'UTF-8');
            $groups[$root][$key] = $item;
        }

        $wordForms = [];
        foreach ($groups as $root => $forms) {
            $nested = [];
            foreach ($forms as $word => $count) {
                $nested[] = [$word => $count];
            }

            $wordForms[$root] = $nested;
        }

        return $wordForms;
    }

    /**
     * @param $link
     * @return array
     */
    public static function countWordsInLink($link): array
    {
        $wordForms = TextAnalyzer::searchWordForms($link);
        $linkAr = array_count_values($link);
        asort($linkAr);
        $linkAr = array_reverse($linkAr);
        $links = [];

        foreach ($wordForms as $key => $wordForm) {
            $extra = $linkAr[$key];
            $links[$key] = [
                'text' => $key,
                'inLink' => $linkAr[$key],
                'inText' => 0,
                'total' => $linkAr[$key],
                'wordForms' => ['inLink' => $wordForm]
            ];
            foreach ($wordForm as $item) {
                $count = array_shift($item);
                $links[$key]['inLink'] += $count;
                $links[$key]['total'] += $count;
            }
            $links[$key]['inLink'] -= $extra;
            $links[$key]['total'] -= $extra;
        }

        return $links;
    }

    public static function mergeTextAndLinks($text, $link): array
    {
        $result = [];
        $resultWithDensity = [];
        $density = 0;
        foreach ($text as $key1 => $item1) {
            foreach ($link as $key2 => $item2) {
                similar_text($key1, $key2, $percent);
                if ($percent > 82) {
                    $wordForms = [
                        'inLink' => array_shift($item2['wordForms']),
                        'inText' => array_shift($item1['wordForms'])
                    ];
                    $result[$key1] = [
                        'text' => $key1,
                        'inText' => $item1['inText'],
                        'inLink' => $item2['inLink'],
                        'total' => $item1['inText'] + $item2['inLink'],
                        'wordForms' => $wordForms
                    ];
                    unset($link[$key2]);
                    unset($text[$key1]);
                    break;
                }
            }
        }

        $result = array_merge($link, $text, $result);

        foreach ($result as $item) {
            $density += $item['total'];
        }

        foreach ($result as $item) {
            $resultWithDensity[] = array_merge($item, [
                'density' => round(100 / $density * $item['total'], 2)
            ]);
        }

        $collect = collect($resultWithDensity);

        return $collect->sortByDesc('total')->toArray();
    }

    public static function calculateTFIDF($array, $textAr, $type): array
    {
        $corpusSize = max(1, count($textAr));

        foreach ($array as &$row) {
            if (!isset($row['wordForms'][$type]) || !is_array($row['wordForms'][$type])) {
                continue;
            }
            foreach ($row['wordForms'][$type] as &$formEntry) {
                $parsed = self::parseWordFormEntry($formEntry);
                if ($parsed['lemma'] === '' || $parsed['count'] === null || (int) $parsed['count'] < 1) {
                    continue;
                }
                $count = (int) $parsed['count'];
                $tf = TfidfMetrics::termFrequency((float) $count, (float) $corpusSize);
                $idf = 0.0;
                $score = $tf;
                $formEntry = [
                    'lemma' => $parsed['lemma'],
                    'count' => $count,
                    'tf' => $tf,
                    'idf' => $idf,
                    'score' => $score,
                ];
            }
            unset($formEntry);
        }
        unset($row);

        return $array;
    }

    /**
     * Нормализация одной словоформы для UI (поддержка старого и нового формата).
     *
     * @param mixed $entry
     * @return array{lemma: string, count: int|null, tf: float|null, idf: float|null, score: float|null}
     */
    public static function parseWordFormEntry($entry): array
    {
        $empty = ['lemma' => '', 'count' => null, 'tf' => null, 'idf' => null, 'score' => null];
        if (!is_array($entry)) {
            return $empty;
        }

        if (isset($entry['lemma']) || isset($entry['count'])) {
            return [
                'lemma' => trim((string) ($entry['lemma'] ?? '')),
                'count' => isset($entry['count']) ? (int) $entry['count'] : null,
                'tf' => isset($entry['tf']) ? (float) $entry['tf'] : null,
                'idf' => isset($entry['idf']) ? (float) $entry['idf'] : null,
                'score' => isset($entry['score']) ? (float) $entry['score'] : null,
            ];
        }

        $tf = isset($entry['tf']) ? (float) $entry['tf'] : null;
        $idf = isset($entry['idf']) ? (float) $entry['idf'] : null;
        $score = isset($entry['score']) ? (float) $entry['score'] : null;

        foreach ($entry as $key => $value) {
            if ($key === 'tf' || $key === 'idf' || $key === 'score') {
                continue;
            }
            if (is_array($value)) {
                foreach ($value as $innerKey => $innerVal) {
                    if (!is_array($innerVal) && (string) $innerKey !== '') {
                        return [
                            'lemma' => (string) $innerKey,
                            'count' => (int) $innerVal,
                            'tf' => $tf,
                            'idf' => $idf,
                            'score' => $score,
                        ];
                    }
                }
                continue;
            }
            if ((string) $key === '') {
                continue;
            }

            return [
                'lemma' => (string) $key,
                'count' => is_numeric($value) ? (int) $value : null,
                'tf' => $tf,
                'idf' => $idf,
                'score' => $score,
            ];
        }

        return $empty;
    }

    public static function shouldCompareCompetitor(array $request): bool
    {
        if (empty($request['compareCompetitor'])) {
            return false;
        }

        return !in_array($request['compareCompetitor'], [false, 0, '0', 'false', 'off', ''], true);
    }

    /**
     * Домен из URL (без www) для подписей «Конкурент · example.com».
     */
    public static function urlHost(?string $url): string
    {
        $url = trim((string) $url);
        if ($url === '') {
            return '';
        }

        if (!preg_match('#^https?://#i', $url)) {
            $url = 'https://' . $url;
        }

        $host = parse_url($url, PHP_URL_HOST);

        if (!is_string($host) || $host === '') {
            return preg_replace('#^www\.#i', '', trim((string) parse_url($url, PHP_URL_PATH), '/'));
        }

        return (string) preg_replace('/^www\./i', '', $host);
    }

    public static function competitorLabel(?string $url): string
    {
        $host = self::urlHost($url);
        if ($host === '') {
            return (string) __('Competitor');
        }

        return __('Competitor') . ' · ' . $host;
    }

    /**
     * @param array $response основной отчёт (мутируется)
     * @param array $competitorResponse
     */
    public static function attachCompetitorComparison(array &$response, array $competitorResponse, string $competitorUrl): void
    {
        $response['competitor'] = array_merge($competitorResponse, [
            'url' => $competitorUrl,
        ]);
        $response['comparison'] = [
            'competitor_url' => $competitorUrl,
            'competitor_host' => self::urlHost($competitorUrl),
            'totalWords' => self::buildComparisonWordRows(
                $response['totalWords'] ?? [],
                $competitorResponse['totalWords'] ?? []
            ),
            'phrases' => self::buildComparisonPhraseRows(
                $response['phrases'] ?? [],
                $competitorResponse['phrases'] ?? []
            ),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $mainWords
     * @param array<int, array<string, mixed>> $competitorWords
     * @return array<int, array<string, mixed>>
     */
    public static function buildComparisonWordRows(array $mainWords, array $competitorWords): array
    {
        $mainByText = [];
        foreach ($mainWords as $word) {
            $key = mb_strtolower((string) ($word['text'] ?? ''));
            if ($key !== '') {
                $mainByText[$key] = $word;
            }
        }

        $competitorByText = [];
        foreach ($competitorWords as $word) {
            $key = mb_strtolower((string) ($word['text'] ?? ''));
            if ($key !== '') {
                $competitorByText[$key] = $word;
            }
        }

        $keys = array_unique(array_merge(array_keys($mainByText), array_keys($competitorByText)));
        $rows = [];

        foreach ($keys as $key) {
            $main = $mainByText[$key] ?? null;
            $competitor = $competitorByText[$key] ?? null;
            $lemma = (string) (($main['text'] ?? null) ?: ($competitor['text'] ?? $key));
            $mainTotal = (int) ($main['total'] ?? 0);
            $competitorTotal = (int) ($competitor['total'] ?? 0);

            $rows[] = [
                'text' => $lemma,
                'main' => $main,
                'competitor' => $competitor,
                'delta_total' => $mainTotal - $competitorTotal,
                'sort_total' => max($mainTotal, $competitorTotal),
            ];
        }

        usort($rows, static function ($a, $b) {
            return ($b['sort_total'] ?? 0) <=> ($a['sort_total'] ?? 0)
                ?: strcmp((string) ($a['text'] ?? ''), (string) ($b['text'] ?? ''));
        });

        return $rows;
    }

    /**
     * @param array<int, array<string, mixed>> $mainPhrases
     * @param array<int, array<string, mixed>> $competitorPhrases
     * @return array<int, array<string, mixed>>
     */
    public static function buildComparisonPhraseRows(array $mainPhrases, array $competitorPhrases): array
    {
        $mainByPhrase = [];
        foreach ($mainPhrases as $phrase) {
            $key = mb_strtolower(trim((string) ($phrase['phrase'] ?? '')));
            if ($key !== '') {
                $mainByPhrase[$key] = $phrase;
            }
        }

        $competitorByPhrase = [];
        foreach ($competitorPhrases as $phrase) {
            $key = mb_strtolower(trim((string) ($phrase['phrase'] ?? '')));
            if ($key !== '') {
                $competitorByPhrase[$key] = $phrase;
            }
        }

        $keys = array_unique(array_merge(array_keys($mainByPhrase), array_keys($competitorByPhrase)));
        $rows = [];

        foreach ($keys as $key) {
            $main = $mainByPhrase[$key] ?? null;
            $competitor = $competitorByPhrase[$key] ?? null;
            $label = trim((string) (($main['phrase'] ?? null) ?: ($competitor['phrase'] ?? $key)));
            $mainCount = (int) ($main['count'] ?? 0);
            $competitorCount = (int) ($competitor['count'] ?? 0);

            $rows[] = [
                'phrase' => $label,
                'main' => $main,
                'competitor' => $competitor,
                'delta_count' => $mainCount - $competitorCount,
                'sort_count' => max($mainCount, $competitorCount),
            ];
        }

        usort($rows, static function ($a, $b) {
            return ($b['sort_count'] ?? 0) <=> ($a['sort_count'] ?? 0)
                ?: strcmp((string) ($a['phrase'] ?? ''), (string) ($b['phrase'] ?? ''));
        });

        return array_slice($rows, 0, 150);
    }
}
