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
            <div class="form-text">{{ __('Minimum 200 characters for text analysis.') }}</div>
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

        <div class="cabinet-ta-options row g-3 mt-3">
            <div class="col-12 col-lg-6">
                <div class="form-check form-switch">
                    <input class="form-check-input click_tracking"
                           type="checkbox"
                           id="switchNoindex"
                           name="noIndex"
                           value="1"
                           data-click="Track the text in the noindex tag"
                           @if(!empty($request['noIndex'])) checked @endif>
                    <label class="form-check-label" for="switchNoindex">
                        {{ __('Track the text in the noindex tag') }}
                    </label>
                </div>
            </div>
            <div class="col-12 col-lg-6">
                <div class="form-check form-switch">
                    <input class="form-check-input click_tracking"
                           type="checkbox"
                           id="switchAltAndTitle"
                           name="hiddenText"
                           value="1"
                           data-click="Track words in the alt title and data text attributes"
                           @if(!empty($request['hiddenText'])) checked @endif>
                    <label class="form-check-label" for="switchAltAndTitle">
                        {{ __('Track words in the alt, title, and data-text attributes') }}
                    </label>
                </div>
            </div>
            <div class="col-12 col-lg-6">
                <div class="form-check form-switch">
                    <input class="form-check-input click_tracking"
                           type="checkbox"
                           id="switchConjunctionsPrepositionsPronouns"
                           name="conjunctionsPrepositionsPronouns"
                           value="1"
                           data-click="Track conjunctions prepositions pronouns"
                           @if(!empty($request['conjunctionsPrepositionsPronouns'])) checked @endif>
                    <label class="form-check-label" for="switchConjunctionsPrepositionsPronouns">
                        {{ __('Exclude conjunctions, prepositions, pronouns') }}
                    </label>
                </div>
            </div>
            <div class="col-12 col-lg-6">
                <div class="form-check form-switch">
                    <input class="form-check-input click_tracking"
                           type="checkbox"
                           id="removeWords"
                           name="removeWords"
                           value="1"
                           data-click="Exclude words"
                           @if(!empty($request['removeWords'])) checked @endif>
                    <label class="form-check-label" for="removeWords">
                        {{ __('Exclude custom word list') }}
                    </label>
                </div>
            </div>
        </div>

        <div id="cabinet-ta-list-words" class="mt-3 {{ empty($request['removeWords']) ? 'd-none' : '' }}">
            <label class="form-label" for="listWords">{{ __('Words to exclude') }}</label>
            <textarea class="form-control font-monospace"
                      name="listWords"
                      id="listWords"
                      rows="4"
                      placeholder="{{ __('One word per line or separated by spaces') }}">@if(!empty($request['listWords'])){{ $request['listWords'] }}@endif</textarea>
        </div>

        <div class="d-flex flex-wrap gap-2 mt-4">
            <button type="submit" class="btn btn-primary" id="cabinet-ta-submit">
                <i class="bi bi-search me-1"></i>{{ __('Analyse') }}
            </button>
        </div>

        {!! Form::close() !!}
    </div>
</div>
