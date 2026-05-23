@php
    $request = $request ?? [];
    $mode = $request['type'] ?? (isset($url) ? 'url' : 'text');
@endphp

<div class="card shadow-sm mb-3">
    <div class="card-header py-2">
        <h3 class="card-title h6 mb-0">
            <i class="bi bi-sliders me-1 text-primary"></i>{{ __('Analysis settings') }}
        </h3>
    </div>
    <div class="card-body">
        {!! Form::open(['action' => 'TextAnalyzerController@analyze', 'method' => 'POST', 'class' => 'cabinet-ta-form', 'id' => 'cabinet-ta-form']) !!}
        <input type="hidden" name="type" value="{{ $mode }}" id="cabinet-ta-type">

        <ul class="nav nav-pills cabinet-ta-mode mb-3" role="tablist">
            <li class="nav-item" role="presentation">
                <button type="button"
                        class="nav-link {{ $mode === 'text' ? 'active' : '' }}"
                        id="cabinet-ta-mode-text"
                        data-mode="text">
                    <i class="bi bi-file-text me-1"></i>{{ __('Text Analysis') }}
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button type="button"
                        class="nav-link {{ $mode === 'url' ? 'active' : '' }}"
                        id="cabinet-ta-mode-url"
                        data-mode="url">
                    <i class="bi bi-link-45deg me-1"></i>{{ __('URL Analysis') }}
                </button>
            </li>
        </ul>

        <div id="cabinet-ta-panel-text" class="cabinet-ta-panel {{ $mode !== 'text' ? 'd-none' : '' }}">
            <label class="form-label fw-semibold" for="cabinet-ta-textarea">{{ __('Your text') }}</label>
            <textarea name="textarea"
                      id="cabinet-ta-textarea"
                      class="form-control cabinet-ta-textarea"
                      rows="10"
                      placeholder="{{ __('Paste at least 200 characters of text…') }}">@isset($request['textarea']){{ $request['textarea'] }}@endisset</textarea>
            <div class="form-text mb-0">{{ __('Minimum 200 characters for text analysis.') }}</div>
        </div>

        <div id="cabinet-ta-panel-url" class="cabinet-ta-panel {{ $mode !== 'url' ? 'd-none' : '' }}">
            <label class="form-label fw-semibold" for="cabinet-ta-url">{{ __('Page URL') }}</label>
            <input type="url"
                   class="form-control"
                   name="url"
                   id="cabinet-ta-url"
                   placeholder="https://example.com/page"
                   value="@isset($request['url']){{ $request['url'] }}@elseif(isset($url)){{ $url }}@endisset">
        </div>

        @if ($errors->any())
            <div class="alert alert-danger small mt-3 mb-0">
                <ul class="mb-0 ps-3">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="cabinet-ta-options cabinet-ta-switches mt-2">
            <div class="cabinet-ta-switch-row">
                <div class="cabinet-ta-switch-row__toggle">
                    <div class="custom-control custom-switch custom-switch-off-danger custom-switch-on-success">
                        <input class="custom-control-input click_tracking"
                               type="checkbox"
                               id="switchNoindex"
                               name="noIndex"
                               value="1"
                               data-click="Track the text in the noindex tag"
                               @if(!empty($request['noIndex'])) checked @endif>
                        <label class="custom-control-label" for="switchNoindex"></label>
                    </div>
                </div>
                <span class="cabinet-ta-switch-row__text">{{ __('Track the text in the noindex tag') }}</span>
            </div>
            <div class="cabinet-ta-switch-row">
                <div class="cabinet-ta-switch-row__toggle">
                    <div class="custom-control custom-switch custom-switch-off-danger custom-switch-on-success">
                        <input class="custom-control-input click_tracking"
                               type="checkbox"
                               id="switchAltAndTitle"
                               name="hiddenText"
                               value="1"
                               data-click="Track words in the alt title and data text attributes"
                               @if(!empty($request['hiddenText'])) checked @endif>
                        <label class="custom-control-label" for="switchAltAndTitle"></label>
                    </div>
                </div>
                <span class="cabinet-ta-switch-row__text">{{ __('Track words in the alt, title, and data-text attributes') }}</span>
            </div>
            <div class="cabinet-ta-switch-row">
                <div class="cabinet-ta-switch-row__toggle">
                    <input type="hidden" name="conjunctionsPrepositionsPronouns" value="0">
                    <div class="custom-control custom-switch custom-switch-off-danger custom-switch-on-success">
                        <input class="custom-control-input click_tracking"
                               type="checkbox"
                               id="switchConjunctionsPrepositionsPronouns"
                               name="conjunctionsPrepositionsPronouns"
                               value="1"
                               data-click="Track conjunctions prepositions pronouns"
                               @if(\App\TextAnalyzer::shouldExcludeConjunctionsPrepositionsPronouns($request ?? [])) checked @endif>
                        <label class="custom-control-label" for="switchConjunctionsPrepositionsPronouns"></label>
                    </div>
                </div>
                <span class="cabinet-ta-switch-row__text">{{ __('Exclude conjunctions, prepositions, pronouns') }}</span>
            </div>
            <div class="cabinet-ta-switch-row">
                <div class="cabinet-ta-switch-row__toggle">
                    <div class="custom-control custom-switch custom-switch-off-danger custom-switch-on-success">
                        <input class="custom-control-input click_tracking"
                               type="checkbox"
                               id="removeWords"
                               name="removeWords"
                               value="1"
                               data-click="Exclude words"
                               @if(!empty($request['removeWords'])) checked @endif>
                        <label class="custom-control-label" for="removeWords"></label>
                    </div>
                </div>
                <span class="cabinet-ta-switch-row__text">{{ __('Exclude') }} <span class="text-muted">{{ __('(your own list of words)') }}</span></span>
            </div>
        </div>

        <div class="cabinet-ta-switch-row mt-1">
            <div class="cabinet-ta-switch-row__toggle">
                <input type="hidden" name="compareCompetitor" value="0">
                <div class="custom-control custom-switch custom-switch-off-danger custom-switch-on-success">
                    <input class="custom-control-input click_tracking"
                           type="checkbox"
                           id="switchCompareCompetitor"
                           name="compareCompetitor"
                           value="1"
                           data-click="Compare with competitor"
                           @if(\App\TextAnalyzer::shouldCompareCompetitor($request ?? [])) checked @endif>
                    <label class="custom-control-label" for="switchCompareCompetitor"></label>
                </div>
            </div>
            <span class="cabinet-ta-switch-row__text">{{ __('Compare with competitor') }}</span>
        </div>

        <div id="cabinet-ta-competitor-url"
             class="mt-2 {{ \App\TextAnalyzer::shouldCompareCompetitor($request ?? []) ? '' : 'd-none' }}">
            <label class="form-label fw-semibold" for="cabinet-ta-competitor-url-input">{{ __('Competitor page URL') }}</label>
            <input type="url"
                   class="form-control"
                   name="competitorUrl"
                   id="cabinet-ta-competitor-url-input"
                   placeholder="https://competitor.example/page"
                   value="{{ $request['competitorUrl'] ?? '' }}"
                   @if(\App\TextAnalyzer::shouldCompareCompetitor($request ?? [])) required @endif>
            <p class="form-text mb-0">{{ __('Competitor URL compare hint') }}</p>
        </div>

        <div id="cabinet-ta-list-words" class="mt-2 {{ empty($request['removeWords']) ? 'd-none' : '' }}">
            <label class="form-label" for="listWords">{{ __('Words to exclude') }}</label>
            <textarea class="form-control font-monospace"
                      name="listWords"
                      id="listWords"
                      rows="4"
                      placeholder="{{ __('One word per line or separated by spaces') }}">@if(!empty($request['listWords'])){{ $request['listWords'] }}@endif</textarea>
            <p class="form-text mb-0">{{ __('Words to exclude hint') }}</p>
        </div>

        <div class="cabinet-ta-form-actions mt-3">
            <button type="submit" class="btn btn-primary" id="cabinet-ta-submit">
                <i class="bi bi-search me-1"></i>{{ __('Analyse') }}
            </button>
        </div>

        {!! Form::close() !!}
    </div>
</div>
