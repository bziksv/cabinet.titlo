<?php

namespace App\Support;

/**
 * Служебные слова для фильтрации словосочетаний (предлоги, союзы, частицы, местоимения и т.п.).
 */
final class TextAnalyzerStopWords
{
    /** @var array<string, true>|null */
    private static $phraseLookup;

    /**
     * Множество однословных служебных лексем (ключ — слово в нижнем регистре).
     *
     * @return array<string, true>
     */
    public static function phraseLookup(): array
    {
        if (self::$phraseLookup !== null) {
            return self::$phraseLookup;
        }

        $words = array_merge(
            self::pronouns(),
            self::prepositions(),
            self::conjunctions(),
            self::particles(),
            self::miscServiceWords()
        );

        $lookup = [];
        foreach ($words as $word) {
            $word = mb_strtolower(trim($word));
            if ($word === '' || mb_strpos($word, ' ') !== false) {
                continue;
            }
            $lookup[$word] = true;
        }

        self::$phraseLookup = $lookup;

        return self::$phraseLookup;
    }

    public static function isPhraseStopWord(string $word): bool
    {
        $word = mb_strtolower(trim($word));
        if ($word === '') {
            return true;
        }
        if (preg_match('/^\d+$/u', $word)) {
            return true;
        }

        return isset(self::phraseLookup()[$word]);
    }

    /** @return string[] */
    private static function pronouns(): array
    {
        return [
            'я', 'мы', 'ты', 'вы', 'он', 'она', 'оно', 'они', 'себя', 'мой', 'наш', 'твой', 'ваш', 'свой',
            'моя', 'моё', 'мое', 'мои', 'наша', 'наше', 'наши', 'твоя', 'твоё', 'твое', 'твои', 'ваша', 'ваше', 'ваши',
            'своя', 'своё', 'свое', 'свои', 'его', 'её', 'ее', 'их', 'ему', 'ей', 'им', 'нем', 'ней', 'них',
            'кто', 'что', 'какой', 'каков', 'который', 'чей', 'сколько', 'этот', 'тот', 'такой', 'таков',
            'эта', 'это', 'эти', 'этих', 'этим', 'этой', 'этому', 'того', 'тому', 'той', 'тех', 'тем', 'ту', 'те',
            'столько', 'сам', 'сама', 'само', 'сами', 'самый', 'самая', 'самое', 'самые',
            'весь', 'вся', 'всё', 'все', 'всего', 'всем', 'всей', 'всю',
            'всякий', 'каждый', 'каждая', 'каждое', 'каждые', 'любой', 'любая', 'любое', 'любые', 'иной', 'иная', 'иное', 'иные',
            'никто', 'ничто', 'никакой', 'ничей', 'никоторый', 'некого', 'нечего', 'некто', 'нечто', 'некоторый',
            'кое-кто', 'кое-что', 'кое-какой', 'кто-то', 'что-то', 'что-либо', 'что-нибудь', 'какой-то', 'какой-либо', 'какой-нибудь',
            'чей-то', 'чей-нибудь',
            'i', 'you', 'she', 'he', 'it', 'we', 'they', 'me', 'her', 'him', 'us', 'them',
            'my', 'your', 'our', 'their', 'mine', 'yours', 'hers', 'his', 'its', 'ours', 'theirs',
            'myself', 'yourself', 'herself', 'himself', 'itself', 'ourselves', 'yourselves', 'themselves',
            'this', 'that', 'these', 'those', 'who', 'whom', 'whose', 'which', 'what',
        ];
    }

    /** @return string[] */
    private static function prepositions(): array
    {
        return [
            'без', 'безо', 'близ', 'в', 'во', 'вместо', 'вне', 'для', 'до', 'за', 'из', 'изо', 'из-за', 'из-под',
            'к', 'ко', 'кроме', 'между', 'меж', 'на', 'над', 'о', 'об', 'обо', 'от', 'ото', 'перед', 'передо',
            'пред', 'по', 'под', 'подо', 'при', 'про', 'ради', 'с', 'со', 'сквозь', 'среди', 'у', 'через', 'чрез',
            'около', 'возле', 'вдоль', 'поперёк', 'поперек', 'мимо', 'внутри', 'снаружи', 'вокруг', 'после', 'перед',
            'aboard', 'about', 'above', 'across', 'after', 'against', 'along', 'among', 'around', 'at', 'before',
            'behind', 'below', 'beneath', 'beside', 'besides', 'between', 'beyond', 'by', 'despite', 'down', 'during',
            'except', 'for', 'from', 'in', 'inside', 'into', 'near', 'of', 'off', 'on', 'onto', 'opposite', 'out',
            'outside', 'over', 'past', 'per', 'plus', 'since', 'than', 'through', 'till', 'to', 'toward', 'towards',
            'under', 'underneath', 'unlike', 'until', 'up', 'via', 'with', 'within', 'without',
        ];
    }

    /** @return string[] */
    private static function conjunctions(): array
    {
        return [
            'и', 'а', 'но', 'или', 'либо', 'да', 'ни', 'же', 'что', 'чтоб', 'чтобы', 'как', 'когда', 'если', 'хотя',
            'пока', 'покуда', 'покамест', 'поскольку', 'потому', 'оттого', 'отчего', 'зато', 'однако', 'притом',
            'причём', 'причем', 'также', 'тоже', 'лишь', 'только', 'будто', 'словно', 'точно', 'едва', 'ежели',
            'коли', 'раз', 'пусть', 'пускай', 'ибо', 'зачем', 'почему', 'чем', 'нежели', 'как-будто',
            'also', 'and', 'as', 'because', 'but', 'however', 'if', 'nor', 'or', 'so', 'than', 'that', 'though',
            'unless', 'until', 'when', 'where', 'whereas', 'while', 'yet',
        ];
    }

    /** @return string[] */
    private static function particles(): array
    {
        return [
            'не', 'ни', 'бы', 'б', 'ли', 'ль', 'вот', 'вон', 'ведь', 'уж', 'уже', 'ещё', 'еще', 'даже', 'именно',
            'почти', 'чуть', 'вовсе', 'совсем', 'прямо', 'просто', 'едва', 'еле', 'разве', 'неужели', 'дескать',
            'якобы', 'вроде', 'лишь', 'только', 'даже', 'ну', 'вот', 'ка', 'де', 'дескать', 'мол', 'дескать',
            'нибудь', 'нибудь', 'либо', 'любо', 'кое', 'как-нибудь', 'кое-как', 'никак', 'ничего', 'никуда',
            'нигде', 'никогда', 'нисколько', 'несколько',
        ];
    }

    /** @return string[] */
    private static function miscServiceWords(): array
    {
        return [
            'тут', 'там', 'здесь', 'сюда', 'туда', 'откуда', 'куда', 'где', 'отсюда', 'оттуда', 'повсюду',
            'теперь', 'тогда', 'потом', 'затем', 'вдруг', 'вновь', 'снова', 'опять', 'уже', 'всегда', 'никогда',
            'иногда', 'часто', 'редко', 'очень', 'слишком', 'совсем', 'почти', 'чуть', 'крайне', 'весьма',
            'the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'of', 'for', 'with', 'by', 'from',
            'is', 'are', 'was', 'were', 'be', 'been', 'being', 'have', 'has', 'had', 'do', 'does', 'did',
            'will', 'would', 'shall', 'should', 'may', 'might', 'must', 'can', 'could', 'not', 'no', 'yes',
        ];
    }
}
