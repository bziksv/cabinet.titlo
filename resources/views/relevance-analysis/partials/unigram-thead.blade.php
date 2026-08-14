<thead class="unigram-thead">
<tr class="unigram-thead__filters">
    <th class="unigram-expand-col"></th>
    <th class="unigram-ranges-label font-weight-normal text-muted">{{ __('Ranges for filtering the table') }}</th>
    <th class="unigram-filter-cell">
        <div class="unigram-filter-box">
            <input class="w-100" type="number" name="minTfidfTop" id="minTfidfTop" placeholder="{{ __('min') }}">
            <input class="w-100" type="number" name="maxTfidfTop" id="maxTfidfTop" placeholder="{{ __('max') }}">
        </div>
    </th>
    <th class="unigram-filter-cell">
        <div class="unigram-filter-box">
            <input class="w-100" type="number" name="minTfidfSite" id="minTfidfSite" placeholder="{{ __('min') }}">
            <input class="w-100" type="number" name="maxTfidfSite" id="maxTfidfSite" placeholder="{{ __('max') }}">
        </div>
    </th>
    <th class="unigram-filter-cell">
        <div class="unigram-filter-box">
            <input class="w-100" type="number" name="minBm25Top" id="minBm25Top" placeholder="{{ __('min') }}">
            <input class="w-100" type="number" name="maxBm25Top" id="maxBm25Top" placeholder="{{ __('max') }}">
        </div>
    </th>
    <th class="unigram-filter-cell">
        <div class="unigram-filter-box">
            <input class="w-100" type="number" name="minBm25Site" id="minBm25Site" placeholder="{{ __('min') }}">
            <input class="w-100" type="number" name="maxBm25Site" id="maxBm25Site" placeholder="{{ __('max') }}">
        </div>
    </th>
    <th class="unigram-filter-cell">
        <div class="unigram-filter-box">
            <input class="w-100" type="number" name="minInter" id="minInter" placeholder="{{ __('min') }}">
            <input class="w-100" type="number" name="maxInter" id="maxInter" placeholder="{{ __('max') }}">
        </div>
    </th>
    <th class="unigram-filter-cell">
        <div class="unigram-filter-box">
            <input class="w-100" type="number" name="minReSpam" id="minReSpam" placeholder="{{ __('min') }}">
            <input class="w-100" type="number" name="maxReSpam" id="maxReSpam" placeholder="{{ __('max') }}">
        </div>
    </th>
    <th class="unigram-filter-cell">
        <div class="unigram-filter-box">
            <input class="w-100" type="number" name="minAVG" id="minAVG" placeholder="{{ __('min') }}">
            <input class="w-100" type="number" name="maxAVG" id="maxAVG" placeholder="{{ __('max') }}">
        </div>
    </th>
    <th class="unigram-filter-cell">
        <div class="unigram-filter-box">
            <input class="w-100" type="number" name="minAVGText" id="minAVGText" placeholder="{{ __('min') }}">
            <input class="w-100" type="number" name="maxAVGText" id="maxAVGText" placeholder="{{ __('max') }}">
        </div>
    </th>
    <th class="unigram-filter-cell">
        <div class="unigram-filter-box">
            <input class="w-100" type="number" name="minInYourPage" id="minInYourPage" placeholder="{{ __('min') }}">
            <input class="w-100" type="number" name="maxInYourPage" id="maxInYourPage" placeholder="{{ __('max') }}">
        </div>
    </th>
    <th class="unigram-filter-cell">
        <div class="unigram-filter-box">
            <input class="w-100" type="number" name="minTextIYP" id="minTextIYP" placeholder="{{ __('min') }}">
            <input class="w-100" type="number" name="maxTextIYP" id="maxTextIYP" placeholder="{{ __('max') }}">
        </div>
    </th>
    <th class="unigram-filter-cell">
        <div class="unigram-filter-box">
            <input class="w-100" type="number" name="minAVGLink" id="minAVGLink" placeholder="{{ __('min') }}">
            <input class="w-100" type="number" name="maxAVGLink" id="maxAVGLink" placeholder="{{ __('max') }}">
        </div>
    </th>
    <th class="unigram-filter-cell">
        <div class="unigram-filter-box">
            <input class="w-100" type="number" name="minLinkIYP" id="minLinkIYP" placeholder="{{ __('min') }}">
            <input class="w-100" type="number" name="maxLinkIYP" id="maxLinkIYP" placeholder="{{ __('max') }}">
        </div>
    </th>
</tr>
<tr class="unigram-thead__divider" aria-hidden="true">
    <th colspan="14"></th>
</tr>
<tr class="unigram-thead__titles">
    <th class="unigram-expand-col"></th>
    <th class="unigram-th-words">
        {{ __('Words') }}
        <span class="__helper-link ui_tooltip_w">
            <i class="fa fa-question-circle"></i>
            <span class="ui_tooltip __bottom relevance-tlp-col-tip">
                <span class="ui_tooltip_content">{{ __('Words and their word forms that are present on competitors websites.') }}</span>
            </span>
        </span>
    </th>
    <th class="unigram-th-metric">
        {{ __('TF-IDF TOP') }}
        <span class="__helper-link ui_tooltip_w">
            <i class="fa fa-question-circle"></i>
            <span class="ui_tooltip __bottom relevance-tlp-col-tip">
                <span class="ui_tooltip_content">{{ __('Hybrid TF-IDF in the competitor corpus: frequency × corpus IDF × coverage across TOP sites.') }}</span>
            </span>
        </span>
    </th>
    <th class="unigram-th-metric">
        {{ __('TF-IDF your site') }}
        <span class="__helper-link ui_tooltip_w">
            <i class="fa fa-question-circle"></i>
            <span class="ui_tooltip __bottom relevance-tlp-col-tip">
                <span class="ui_tooltip_content">{{ __('Hybrid TF-IDF on your landing page using the same competitor IDF and coverage.') }}</span>
            </span>
        </span>
    </th>
    <th class="unigram-th-metric">
        {{ __('BM25 TOP') }}
        <span class="__helper-link ui_tooltip_w">
            <i class="fa fa-question-circle"></i>
            <span class="ui_tooltip __bottom relevance-tlp-col-tip">
                <span class="ui_tooltip_content">{{ __('BM25 weight in the aggregated competitor corpus.') }}</span>
            </span>
        </span>
    </th>
    <th class="unigram-th-metric">
        {{ __('BM25 your site') }}
        <span class="__helper-link ui_tooltip_w">
            <i class="fa fa-question-circle"></i>
            <span class="ui_tooltip __bottom relevance-tlp-col-tip">
                <span class="ui_tooltip_content">{{ __('BM25 weight on your landing page.') }}</span>
            </span>
        </span>
    </th>
    <th class="unigram-th-narrow">{{ __('Intersection') }}
        <span class="__helper-link ui_tooltip_w">
            <i class="fa fa-question-circle"></i>
            <span class="ui_tooltip __bottom relevance-tlp-col-tip">
                <span class="ui_tooltip_content">{{ __('The number of sites in which the word is present.') }}</span>
            </span>
        </span>
    </th>
    <th class="unigram-th-narrow">{{ __('Re - spam') }}
        <span class="__helper-link ui_tooltip_w">
            <i class="fa fa-question-circle"></i>
            <span class="ui_tooltip __bottom relevance-tlp-col-tip">
                <span class="ui_tooltip_content">{{ __('The maximum number of repetitions found on the competitors website.') }}</span>
            </span>
        </span>
    </th>
    <th class="unigram-th-wide">{{ __('Average number of repetitions in the text and links') }}
        <span class="unigram-th-help __helper-link ui_tooltip_w">
            <i class="fa fa-question-circle"></i>
            <span class="ui_tooltip __bottom relevance-tlp-col-tip">
                <span class="ui_tooltip_content">{{ __('The average value of the number of repetitions in the text and links of your competitors.') }}</span>
            </span>
        </span>
    </th>
    <th class="unigram-th-wide">{{ __('The total number of repetitions in the text and links') }}
        <span class="unigram-th-help __helper-link ui_tooltip_w">
            <i class="fa fa-question-circle"></i>
            <span class="ui_tooltip __bottom relevance-tlp-col-tip">
                <span class="ui_tooltip_content">{{ __('The total number of repetitions on your page in links and text.') }}</span>
            </span>
        </span>
    </th>
    <th class="unigram-th-wide">{{ __('Average number of repetitions in the text') }}
        <span class="unigram-th-help __helper-link ui_tooltip_w">
            <i class="fa fa-question-circle"></i>
            <span class="ui_tooltip __bottom relevance-tlp-col-tip">
                <span class="ui_tooltip_content">{{ __('The average value of the number of repetitions in the text of your competitors.') }}</span>
            </span>
        </span>
    </th>
    <th class="unigram-th-wide">{{ __('Number of repetitions in text') }}
        <span class="unigram-th-help __helper-link ui_tooltip_w">
            <i class="fa fa-question-circle"></i>
            <span class="ui_tooltip __bottom relevance-tlp-col-tip">
                <span class="ui_tooltip_content">{{ __('The number of repetitions in the text on your page') }}</span>
            </span>
        </span>
    </th>
    <th class="unigram-th-wide">{{ __('Average number of repetitions in links') }}
        <span class="unigram-th-help __helper-link ui_tooltip_w">
            <i class="fa fa-question-circle"></i>
            <span class="ui_tooltip __bottom relevance-tlp-col-tip">
                <span class="ui_tooltip_content">{{ __('The average value of the number of repetitions in the links of your competitors.') }}</span>
            </span>
        </span>
    </th>
    <th class="unigram-th-wide">{{ __('Number of repetitions in links') }}
        <span class="unigram-th-help __helper-link ui_tooltip_w">
            <i class="fa fa-question-circle"></i>
            <span class="ui_tooltip __bottom relevance-tlp-col-tip">
                <span class="ui_tooltip_content">{{ __('The number of repetitions in the links on your page.') }}</span>
            </span>
        </span>
    </th>
</tr>
</thead>
