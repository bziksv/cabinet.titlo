@php
    $request = $request ?? [];
    $mode = $request['type'] ?? (isset($url) ? 'url' : 'text');
    if (! in_array($mode, ['text', 'url', 'batch'], true)) {
        $mode = 'text';
    }
    $checkUniqueness = !empty($request['checkUniqueness']) || $mode === 'batch';
    $canCheckEsenin = !empty($canCheckEsenin);
    $checkEsenin = $canCheckEsenin && !empty($request['checkEsenin']);
    $batchMax = (int) ($batchMax ?? config('cabinet-text-analyzer.batch_max', 20));
    $canSaveUniquenessHistory = !empty($canSaveUniquenessHistory);
    $uniquenessLimit = $uniquenessLimit ?? null;
    $uniquenessRemaining = $uniquenessRemaining ?? null;
    $eseninRemaining = $eseninRemaining ?? null;
    $eseninLimit = $eseninLimit ?? null;
@endphp

<div class="card shadow-sm mb-3" id="cabinetTaFormCard"
     data-batch-url="{{ route('text.analyzer.batch.item') }}"
     data-estimate-url="{{ route('text.analyzer.uniqueness.estimate') }}"
     data-history-url="{{ url('/text-analyzer/uniqueness-history') }}"
     data-batch-max="{{ $batchMax }}"
     data-csrf="{{ csrf_token() }}">
    <div class="card-header py-2">
        <h3 class="card-title h6 mb-0">
            <i class="bi bi-sliders me-1 text-primary"></i>{{ __('Analysis settings') }}
        </h3>
    </div>
    <div class="card-body">
        {!! Form::open(['action' => 'TextAnalyzerController@analyze', 'method' => 'POST', 'class' => 'cabinet-ta-form', 'id' => 'cabinet-ta-form']) !!}
        <input type="hidden" name="type" value="{{ $mode === 'batch' ? 'url' : $mode }}" id="cabinet-ta-type">

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
            <li class="nav-item" role="presentation">
                <button type="button"
                        class="nav-link {{ $mode === 'batch' ? 'active' : '' }}"
                        id="cabinet-ta-mode-batch"
                        data-mode="batch">
                    <i class="bi bi-list-check me-1"></i>{{ __('Text analyzer batch mode') }}
                </button>
            </li>
        </ul>

        <div class="mb-3" id="cabinet-ta-task-name-wrap">
            <label class="form-label fw-semibold mb-1" for="cabinet-ta-task-name">{{ __('Text analyzer task name label') }}</label>
            <input type="text"
                   name="taskName"
                   id="cabinet-ta-task-name"
                   class="form-control"
                   maxlength="120"
                   value="{{ (string) ($request['taskName'] ?? '') }}"
                   placeholder="{{ __('Text analyzer task name placeholder') }}">
            <p class="form-text mb-0 mt-1">{{ __('Text analyzer task name hint') }}</p>
        </div>

        <div id="cabinet-ta-panel-text" class="cabinet-ta-panel {{ $mode !== 'text' ? 'd-none' : '' }}">
            <label class="form-label fw-semibold" for="cabinet-ta-textarea">{{ __('Your text') }}</label>
            <div class="cabinet-ta-editor-wrap" data-ta-editor-wrap>
                <textarea name="textarea"
                          id="cabinet-ta-textarea"
                          class="form-control cabinet-ta-textarea"
                          rows="12"
                          placeholder="{{ __('Paste at least 200 characters of text…') }}">@isset($request['textarea']){{ $request['textarea'] }}@endisset</textarea>
            </div>
            <div class="form-text mb-0">{{ __('Text analyzer visual editor hint') }}</div>
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

        <div id="cabinet-ta-panel-batch" class="cabinet-ta-panel {{ $mode !== 'batch' ? 'd-none' : '' }}">
            <label class="form-label fw-semibold" for="cabinet-ta-batch-input">{{ __('Text analyzer batch input') }}</label>
            <textarea id="cabinet-ta-batch-input"
                      class="form-control font-monospace"
                      rows="10"
                      placeholder="{{ __('Text analyzer batch placeholder') }}">@isset($request['batchInput']){{ $request['batchInput'] }}@endisset</textarea>
            <div class="form-text mb-0">{{ __('Text analyzer batch hint', ['max' => $batchMax]) }}</div>
            <div class="cabinet-ta-batch-progress d-none mt-2" id="cabinet-ta-batch-progress">
                <div class="small text-secondary" id="cabinet-ta-batch-progress-text"></div>
            </div>
            <div class="table-responsive mt-3 d-none" id="cabinet-ta-batch-results-wrap">
                <table class="table table-sm table-bordered mb-0" id="cabinet-ta-batch-results">
                    <thead>
                    <tr>
                        <th>{{ __('Text analyzer batch col source') }}</th>
                        <th>{{ __('Number of words') }}</th>
                        <th>{{ __('Number of stop words') }}</th>
                        <th>{{ __('Text uniqueness') }}</th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
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

        <div class="cabinet-ta-switch-row mt-2" id="cabinet-ta-uniqueness-switch-row">
            <div class="cabinet-ta-switch-row__toggle">
                <input type="hidden" name="checkUniqueness" value="0">
                <div class="custom-control custom-switch custom-switch-off-danger custom-switch-on-success">
                    <input class="custom-control-input"
                           type="checkbox"
                           id="switchCheckUniqueness"
                           name="checkUniqueness"
                           value="1"
                           @if($checkUniqueness) checked @endif>
                    <label class="custom-control-label" for="switchCheckUniqueness"></label>
                </div>
            </div>
            <span class="cabinet-ta-switch-row__text">{{ __('Text analyzer check uniqueness') }}</span>
        </div>

        <div id="cabinet-ta-uniqueness-panel" class="mt-2 {{ $checkUniqueness ? '' : 'd-none' }}">
            <p class="form-text mb-2">{{ __('Text analyzer uniqueness hint') }}</p>
            <label class="form-label small fw-semibold" for="cabinet-ta-exclude-domain">{{ __('Text analyzer own page url') }}</label>
            <input type="url"
                   class="form-control form-control-sm"
                   name="excludeOwnDomain"
                   id="cabinet-ta-exclude-domain"
                   placeholder="https://yoursite.ru/page-with-this-text"
                   value="{{ $request['excludeOwnDomain'] ?? '' }}">
            <p class="form-text mb-2">{{ __('Text analyzer own page url hint') }}</p>
            <p class="cabinet-ta-cost-line mb-1 mt-2" id="cabinet-ta-uniq-cost-line">
                {{ __('Text analyzer uniqueness will charge') }}:
                <strong id="cabinet-ta-uniq-cost-value">—</strong>
                <span class="text-muted" id="cabinet-ta-uniq-cost-hint"></span>
            </p>
            @if($uniquenessRemaining !== null)
                <p class="form-text mb-0">
                    {{ __('Text analyzer uniqueness remaining') }}:
                    <strong>{{ (int) $uniquenessRemaining }}</strong>
                    @if($uniquenessLimit !== null)/ {{ (int) $uniquenessLimit }}@endif
                </p>
            @endif
        </div>

        @if($canCheckEsenin)
            <div class="cabinet-ta-switch-row mt-2" id="cabinet-ta-esenin-switch-row">
                <div class="cabinet-ta-switch-row__toggle">
                    <input type="hidden" name="checkEsenin" value="0">
                    <div class="custom-control custom-switch custom-switch-off-danger custom-switch-on-success">
                        <input class="custom-control-input"
                               type="checkbox"
                               id="switchCheckEsenin"
                               name="checkEsenin"
                               value="1"
                               @if($checkEsenin) checked @endif>
                        <label class="custom-control-label" for="switchCheckEsenin"></label>
                    </div>
                </div>
                <span class="cabinet-ta-switch-row__text">{{ __('Esenin text check') }}</span>
            </div>
            <div id="cabinet-ta-esenin-panel" class="mt-1 {{ $checkEsenin ? '' : 'd-none' }}">
                <p class="form-text mb-1">{{ __('Text analyzer esenin hint') }}</p>
                <p class="cabinet-ta-cost-line mb-1">
                    {{ __('Text analyzer esenin will charge') }}:
                    <strong id="cabinet-ta-esenin-cost-value">1</strong>
                </p>
                @if($eseninRemaining !== null)
                    <p class="form-text mb-0">
                        {{ __('Text analyzer esenin remaining') }}:
                        <strong>{{ (int) $eseninRemaining }}</strong>
                        @if($eseninLimit !== null)/ {{ (int) $eseninLimit }}@endif
                    </p>
                @endif
            </div>
        @endif

        <div class="cabinet-ta-cost-summary alert alert-light border py-2 px-3 mb-0 mt-3 d-none" id="cabinet-ta-cost-summary"
             data-label-analyzer="{{ __('Text analyzer cost analyzer') }}"
             data-label-uniqueness="{{ __('Text analyzer cost uniqueness') }}"
             data-label-esenin="{{ __('Text analyzer cost esenin') }}"
             data-label-total="{{ __('Text analyzer cost total') }}"
             data-unit-one="{{ __('Site types cost unit one') }}"
             data-unit-few="{{ __('Site types cost unit few') }}"
             data-unit-many="{{ __('Site types cost unit many') }}"
             data-approx="{{ __('Text analyzer uniqueness cost approx') }}"
             data-probe-hint="{{ __('Text analyzer uniqueness probe hint') }}">
            <div class="small fw-semibold mb-1">{{ __('Text analyzer cost summary') }}</div>
            <ul class="small mb-0 pl-3" id="cabinet-ta-cost-summary-list"></ul>
        </div>

        @if($canSaveUniquenessHistory)
            <div class="cabinet-ta-save-row mt-3 mb-2">
                <label class="cabinet-ta-save mb-0" for="cabinet-ta-save-uniqueness">
                    <input type="hidden" name="saveUniqueness" value="0">
                    <input type="checkbox" name="saveUniqueness" id="cabinet-ta-save-uniqueness" value="1"
                           @if(!isset($request['saveUniqueness']) || !empty($request['saveUniqueness'])) checked @endif>
                    <span>{{ __('Text uniqueness save to history') }}</span>
                </label>
            </div>
        @endif

        <div class="cabinet-ta-form-actions mt-2">
            <button type="submit" class="btn btn-primary" id="cabinet-ta-submit">
                <i class="bi bi-search me-1"></i>{{ __('Analyse') }}
            </button>
            <button type="button" class="btn btn-primary d-none" id="cabinet-ta-batch-run">
                <i class="bi bi-play-fill me-1"></i>{{ __('Text analyzer batch run') }}
            </button>
            <span class="small text-muted ml-2" id="cabinet-ta-form-status"></span>
        </div>

        <div class="cabinet-ta-progress d-none mt-3" id="cabinet-ta-progress" aria-live="polite">
            <div class="cabinet-ta-progress__head">
                <span class="cabinet-ta-progress__spinner" aria-hidden="true"></span>
                <div class="cabinet-ta-progress__text">
                    <div class="cabinet-ta-progress__title" id="cabinet-ta-progress-title">{{ __('Text analyzer progress title') }}</div>
                    <div class="cabinet-ta-progress__sub small text-secondary" id="cabinet-ta-progress-sub">{{ __('Text analyzer progress sub') }}</div>
                </div>
            </div>
            <div class="progress cabinet-ta-progress__bar mt-2">
                <div class="progress-bar progress-bar-striped progress-bar-animated"
                     id="cabinet-ta-progress-bar"
                     role="progressbar"
                     style="width: 35%"
                     aria-valuenow="35"
                     aria-valuemin="0"
                     aria-valuemax="100"></div>
            </div>
        </div>

        {!! Form::close() !!}
    </div>
</div>
