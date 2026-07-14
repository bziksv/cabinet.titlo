@component('component.card', ['title' =>  __('Detailed analysis') ])
    @slot('css')
        <link rel="stylesheet"
              href="{{ asset('plugins/keyword-generator/css/font-awesome-4.7.0/css/font-awesome.css') }}">
        <link rel="stylesheet" href="{{ asset('plugins/keyword-generator/css/style.css') }}">
        <link rel="stylesheet" href="{{ asset('plugins/common/css/datatable.css') }}">
        @include('layouts.partials.vendor-datatables-css', ['bundle' => 'rb-min'])
        <link rel="stylesheet" href="{{ asset('plugins/toastr/toastr.css') }}">
        <link rel="stylesheet" href="{{ asset('plugins/relevance-analysis/css/style.css') }}?v={{ @filemtime(public_path('plugins/relevance-analysis/css/style.css')) ?: time() }}">
        <style>
            #app > div > div > div.card-header {
                display: block;
            }

            .project-info {
                padding-right: 0.5rem;
                padding-left: 0.5rem;
            }

            .project-info:hover {
                color: #007bff;
            }

            .nav-link {
                padding: .5rem .8rem !important;
            }

            .card-header {
                display: flex;
                justify-content: space-between;
                align-items: baseline;
            }

            .card-header::after {
                display: none;
                content: none !important;
            }

            .first-action::after {
                display: inline;
                content: " {{ __('Go to the landing page') }}";
                font-weight: normal;
                font-family: "Source Sans Pro", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif, "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol" !important;
            }

            .second-action::after {
                display: inline;
                content: " {{ __('Go to the text analyzer') }}";
                font-weight: normal;
                font-family: "Source Sans Pro", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif, "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol" !important;
            }

            #scanned-sites {
                width: 100% !important;
            }

            .RelevanceAnalysis {
                background: oldlace;
            }

            thead th {
                position: sticky;
                top: 0;
            }

            .dataTables_length > label {
                display: flex;
            }

            .dataTables_length > label > select {
                margin: 0 5px !important;
            }

            i.fa.fa-copy {
                cursor: pointer;
            }

            .toast-top-right .toast-message a {
                color: #fff;
                font-weight: 700;
                text-decoration: underline;
                text-underline-offset: 2px;
            }

            .relevance-repeat-queue-status {
                display: flex;
                align-items: center;
                gap: 0.5rem;
                max-width: 26rem;
            }

            .relevance-repeat-queue-status.d-none {
                display: none !important;
            }

            .relevance-repeat-queue-status .loader {
                flex-shrink: 0;
            }

            .relevance-repeat-queue-status.alert-success a,
            .relevance-repeat-queue-status.alert-danger a {
                color: inherit;
                font-weight: 700;
                text-decoration: underline;
                text-underline-offset: 2px;
            }
        </style>
    @endslot

    <div id="toast-container" class="toast-top-right error-message" style="display:none;">
        <div class="toast toast-error" aria-live="polite">
            <div class="toast-message" id="message-error-info"></div>
        </div>
    </div>

    <div id="toast-container" class="toast-top-right success-message" style="display:none;">
        <div class="toast toast-success" aria-live="polite">
            <div class="toast-message" id="toast-message">{{ __('Repeated analysis added to the queue') }}</div>
        </div>
    </div>

    <div id="unigram-copy-toast" class="toast-top-right unigram-copy-success" style="display:none;" aria-live="polite">
        <span class="unigram-copy-success__text"></span>
    </div>

    <div id="params" style="display:none;">
        <div class="d-flex w-100 justify-content-between" style="margin-top: 40px;">
            <div style="cursor:pointer;" class="pl-1 pr-1">
                {{ __('Date') }}:
                <span class="project-info">
                    {{ $object->last_check }}
                </span>
            </div>
            <div style="cursor:pointer;" class="pl-1 pr-1">
                {{ __('Phrase') }}:
                <span class="project-info copyInBuffer" data-target="{{ $object->phrase }}">
                    {{ $object->phrase }}
                    <i class="fa fa-copy"></i>
                </span>
            </div>
            <div style="cursor:pointer;" class="pl-1 pr-1">
                {{ __('Landing page') }}:
                <span class="project-info copyInBuffer" data-target="{{ $object->main_link }}">
                    {{ $object->main_link }}
                    <i class="fa fa-copy"></i>
                </span>
            </div>
        </div>
    </div>

    <div class="alert alert-info mb-3" @if(empty($publicShareToken)) style="display:none;" @endif>
        {{ __('Public project access') }} — {{ __('View-only access without registration. Link expires on') }}
        <strong>{{ $publicShareExpires ?? '' }}</strong>.
        @if(!empty($publicShareToken))
            <a href="{{ route('relevance.public.share.view', $publicShareToken) }}" class="alert-link ms-1" target="_blank" rel="noopener">
                {{ __('Back to project') }}
            </a>
        @endif
    </div>

    <div class="card">
        @if(empty($publicShareToken))
        <div class="border-bottom d-flex p-0 justify-content-between w-100">
            <ul class="nav nav-pills p-2">
                <li class="nav-item">
                    <a class="nav-link" href="{{ route('relevance-analysis') }}">{{ __('Analyzer') }}</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="{{ route('create.queue.view') }}">
                        {{ __('Create page analysis tasks') }}
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="{{ route('relevance.history') }}">{{ __('History') }}</a>
                </li>
                <li class="nav-item">
                    <a href="{{ route('sharing.view') }}" class="nav-link">{{ __('Share your projects') }}</a>
                </li>
                <li class="nav-item">
                    <a href="{{ route('access.project') }}" class="nav-link">{{ __('Projects available to you') }}</a>
                </li>
                @if($admin)
                    <li class="nav-item">
                        <a class="nav-link admin-link"
                           href="{{ route('all.relevance.projects') }}">{{ __('Statistics') }}</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link admin-link"
                           href="{{ route('show.config') }}">{{ __('Module administration') }}</a>
                    </li>
                @endif
                <li class="nav-item">
                    <a class="nav-link active" href="#tab_1" data-bs-toggle="tab"
                       id="firstTab">{{ __('Show details') }}</a>
                </li>
                <li class="nav-item" id="repeat-analyse-item">
                    <a class="nav-link" href="#tab_2" data-bs-toggle="tab">{{ __('Repeat the analysis') }}
                    </a>
                </li>
            </ul>
        </div>
        @else
        <div class="card-header">
            <h3 class="card-title mb-0">{{ __('Show details') }}</h3>
        </div>
        @endif
        <div class="card-body">
            <div class="tab-content">
                <div class="tab-pane active" id="tab_1">
                    <div class="text-center" id="preloaderBlock">
                        <img src="{{ asset('/img/1485.gif') }}" alt="preloader_gif">
                        <p id="preloaderStatus">{{ __("Load..") }}</p>
                        <p class="mt-3 mb-0">
                            <a href="{{ url()->current() }}" class="btn btn-sm btn-outline-secondary">Перезагрузить страницу</a>
                        </p>
                    </div>
                    <div id="tablesPreloader" class="text-center py-3" style="display:none">
                        <img src="{{ asset('/img/1485.gif') }}" alt="preloader_gif" style="max-height:48px">
                        <p id="tablesPreloaderStatus" class="text-muted mb-0 mt-2">{{ __('Processing of received data..') }}</p>
                    </div>

                    <div class="pb-3 pt-3 text" style="display:none">
                        <h2>{{ __('Comparing the amount of text') }}</h2>
                        <table class="table table-bordered table-striped dataTable dtr-inline">
                            <thead>
                            <tr>
                                <th class="col-3"></th>
                                <th class="col-3">{{ __('Average values of competitors') }}</th>
                                <th class="col-3">{{ __('Landing Page Values') }}</th>
                            </tr>
                            </thead>
                            <tbody>
                            <tr>
                                <td>
                                    <b>{{ __('Number of words') }}</b>
                                </td>
                                <td id="avgCountWords"></td>
                                <td id="mainPageCountWords"></td>
                            </tr>
                            <tr>
                                <td>
                                    <b>{{ __('Number of characters') }}</b>
                                </td>
                                <td id="avgCountSymbols"></td>
                                <td id="mainPageCountSymbols"></td>
                            </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="pb-3 clouds" style="display:none;">
                        <h2>{{ __('The clouds') }}</h2>
                        <div class="d-flex flex-column pb-3">
                            <button id="tf-idf-clouds" class="btn btn-secondary col-lg-3 col-md-5 mb-3 click_tracking"
                                    data-click="TF idf clouds of sites from the top and landing page"
                                    style="cursor: pointer">
                                {{ __('TF-idf clouds of sites from the top and landing page') }}
                            </button>
                            <div class="tf-idf-clouds" style="display: none">
                                <div class="d-lg-flex mt-4 justify-content-around">

                                    <div class="col-lg-5 col-md-10">
                                        <span>{{ __('Average tf-idf values of links and competitor text') }}</span>
                                        <div style="height: 400px" id="competitorsTfCloud"
                                             class="generated-cloud"></div>
                                    </div>

                                    <div class="col-lg-5 col-md-10">
                                        <span>{{ __('TF-idf values of links and landing page text') }}</span>
                                        <div style="height: 400px" id="mainPageTfCloud" class="generated-cloud"></div>
                                    </div>

                                </div>
                                <div class="d-lg-flex mt-4 justify-content-around">

                                    <div class="col-lg-5 col-md-10">
                                        <span>{{ __('Average tf-idf values of competitors text') }}</span>
                                        <div style="height: 400px" id="competitorsTextTfCloud"
                                             class="generated-cloud"></div>
                                    </div>

                                    <div class="col-lg-5 col-md-10">
                                        <span>{{ __('TF-idf values of the landing page text') }}</span>
                                        <div style="height: 400px" id="mainPageTextTfCloud"
                                             class="generated-cloud"></div>
                                    </div>

                                </div>
                                <div class="d-lg-flex mt-4 justify-content-around">

                                    <div class="col-lg-5 col-md-10">
                                        <span>{{ __('Average tf-idf values of competitor links') }}</span>
                                        <div style="height: 400px" id="competitorsLinksTfCloud"
                                             class="generated-cloud"></div>
                                    </div>

                                    <div class="col-lg-5 col-md-10">
                                        <span>{{ __('TF-idf values of landing page links') }}</span>
                                        <div style="height: 400px" id="mainPageLinksTfCloud"
                                             class="generated-cloud"></div>
                                    </div>

                                </div>
                            </div>
                            <button id="text-clouds" class="btn btn-secondary col-lg-3 col-md-5 click_tracking"
                                    data-click="Clouds of site text from the top and landing page"
                                    style="cursor: pointer;">
                                {{ __('TF clouds of site text from the top and landing page') }}
                            </button>
                            <div class="text-clouds" style=" display: none">
                                <div class="d-lg-flex mt-4 justify-content-around">
                                    <div class="col-lg-5 col-md-10">
                                        <span>{{ __('Competitors Link Zone') }}</span>
                                        <div style="height: 400px" id="competitorsLinksCloud"
                                             class="generated-cloud"></div>
                                    </div>
                                    <div class="col-lg-5 col-md-10">
                                        <span>{{ __('The link zone of your page') }}</span>
                                        <div style="height: 400px" id="mainPageLinksCloud"
                                             class="generated-cloud"></div>
                                    </div>
                                </div>
                                <div class="d-lg-flex mt-4 justify-content-around">
                                    <div class="col-lg-5 col-md-10">
                                        <span>{{ __('Competitors text area') }}</span>
                                        <div style="height: 400px" id="competitorsTextCloud"
                                             class="generated-cloud"></div>
                                    </div>
                                    <div class="col-lg-5 col-md-10">
                                        <span>{{ __('The text area of your page') }}</span>
                                        <div style="height: 400px" id="mainPageTextCloud" class="generated-cloud"></div>
                                    </div>
                                </div>
                                <div class="d-lg-flex mt-4 justify-content-around">
                                    <div class="col-lg-5 col-md-10">
                                        <span>{{ __('Competitors Link and Text area') }}</span>
                                        <div style="height: 400px" id="competitorsTextAndLinksCloud"
                                             class="generated-cloud"></div>
                                    </div>
                                    <div class="col-lg-5 col-md-10">
                                        <span>{{ __('The zone of links and text of your page') }}</span>
                                        <div style="height: 400px" id="mainPageTextWithLinksCloud"
                                             class="generated-cloud"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div id="rec" style="display: none" class="mb-3">
                        <h2>{{ __('TLP Recommendations and Spam check') }}</h2>
                        <button class="btn btn-secondary click_tracking" data-click="TLP Recommendations and Spam check"
                                id="recButton">{{ __('show') }}</button>
                    </div>

                    <div class="pb-3 recommendations" style="display:none;">
                        <table id="recommendations" class="table table-bordered table-hover dataTable dtr-inline"
                               style="width: 100% !important;">
                            <thead>
                            <tr style="position: relative; z-index: 100">
                                <th class="сol-1">
                                <span class="text-muted" style="font-weight: 400">
                                    {{ __("You can delete a word from the table if it has been worked out") }}
                                </span>
                                </th>
                                <th>{{ __('Word') }}</th>
                                <th>Tf</th>
                                <th>{{ __('Average number of repetitions of competitors') }}</th>
                                <th>{{ __('The number you have on the page') }}</th>
                                <th>{{ __('Recommended range') }}</th>
                                <th>{{ __('Spam level') }}</th>
                                <th>{{ __('Add') }}</th>
                                <th>{{ __('Remove') }}</th>
                            </tr>
                            </thead>
                            <tbody id="recommendationsTBody">
                            </tbody>
                        </table>
                    </div>

                    <div class="pb-3 unigram" style="display: none; margin-top: 50px">
                        <h2>{{ __('Top list of phrases (TLP)') }}</h2>
                        <table id="unigram" class="table table-bordered table-hover dataTable dtr-inline"
                               style="width: 100% !important;">
                            @include('relevance-analysis.partials.unigram-thead')
                            <tbody id="unigramTBody">
                            </tbody>
                        </table>
                    </div>

                    <div class="phrases" style="display:none;">
                        <h2>{{ __('Top list of phrases (TLPs)') }}</h2>
                        <table id="phrases" class="table table-bordered table-hover dataTable dtr-inline w-100">
                            @include('relevance-analysis.partials.phrases-thead')
                            <tbody id="phrasesTBody">
                            </tbody>
                        </table>
                    </div>

                    <div class="sites" style="display:none;">
                        <h2>{{ __('Analyzed sites') }}</h2>
                        <table id="scanned-sites" class="table table-bordered table-hover dataTable dtr-inline w-100">
                            @include('relevance-analysis.partials.scanned-sites-thead')
                            <tbody id="scanned-sites-tbody">
                            </tbody>
                        </table>
                    </div>

                    <div class="pb-3 pt-3" id="competitorsTfClouds" style="display: none !important;">
                        <div class="align-items-end clouds-div">
                            <button class="btn btn-secondary col-lg-3 col-md-5 click_tracking"
                                    id="coverage-clouds-button"
                                    data-click="Clouds of the first 200 important tf idf words from competitors">
                                {{ __('Clouds of the first 200 important (tf-idf) words from competitors') }}
                            </button>
                        </div>
                        <div id="coverage-clouds" class="pt-2">
                            <div class='d-flex w-100'>
                                <div class='__helper-link ui_tooltip_w'>
                                    <div
                                        class='custom-control custom-switch custom-switch-off-danger custom-switch-on-success'>
                                        <input type='checkbox'
                                               class='custom-control-input'
                                               id='showOrHideIgnoredClouds'>
                                        <label class='custom-control-label' for='showOrHideIgnoredClouds'></label>
                                    </div>
                                </div>
                                <p>{{ __('hide ignored domains') }}</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="tab-pane" id="tab_2">
                    @if(isset($access) && $access->access > 1 || !isset($access))
                        <div class="col-5">

                            <input type="hidden" name="hiddenId" id="hiddenId" value="{{ $object->id }}">
                            <input type="hidden" name="type" id="type" value="{{ $object['request']['type'] }}">

                            <div class="form-group required">
                                <label>{{ __('Your landing page') }}</label>
                                {!! Form::text("link", $object['request']['link'] ,["class" => "form-control link", "required"]) !!}
                            </div>

                            <div class="form-group required">
                                <label>{{ __('Keyword') }}</label>
                                {!! Form::text("phrase", $object['request']['phrase'] ,["class" => "form-control phrase", "required"]) !!}
                            </div>

                            <div class="form-group required">
                                <label>{{ __('Region') }}</label>
                                {!! Form::select('region', array_unique([
                                       $object['request']['region'] => $object['request']['region'],
                                       '213' => __('Moscow'),
                                       '1' => __('Moscow and the area'),
                                       '20' => __('Arkhangelsk'),
                                       '37' => __('Astrakhan'),
                                       '197' => __('Barnaul'),
                                       '4' => __('Belgorod'),
                                       '77' => __('Blagoveshchensk'),
                                       '191' => __('Bryansk'),
                                       '24' => __('Veliky Novgorod'),
                                       '75' => __('Vladivostok'),
                                       '33' => __('Vladikavkaz'),
                                       '192' => __('Vladimir'),
                                       '38' => __('Volgograd'),
                                       '21' => __('Vologda'),
                                       '193' => __('Voronezh'),
                                       '1106' => __('Grozny'),
                                       '54' => __('Ekaterinburg'),
                                       '5' => __('Ivanovo'),
                                       '63' => __('Irkutsk'),
                                       '41' => __('Yoshkar-ola'),
                                       '43' => __('Kazan'),
                                       '22' => __('Kaliningrad'),
                                       '64' => __('Kemerovo'),
                                       '7' => __('Kostroma'),
                                       '35' => __('Krasnodar'),
                                       '62' => __('Krasnoyarsk'),
                                       '53' => __('Kurgan'),
                                       '8' => __('Kursk'),
                                       '9' => __('Lipetsk'),
                                       '28' => __('Makhachkala'),
                                       '23' => __('Murmansk'),
                                       '1092' => __('Nazran'),
                                       '30' => __('Nalchik'),
                                       '47' => __('Nizhniy Novgorod'),
                                       '65' => __('Novosibirsk'),
                                       '66' => __('Omsk'),
                                       '10' => __('Eagle'),
                                       '48' => __('Orenburg'),
                                       '49' => __('Penza'),
                                       '50' => __('Perm'),
                                       '25' => __('Pskov'),
                                       '39' => __('Rostov-on-Don'),
                                       '11' => __('Ryazan'),
                                       '51' => __('Samara'),
                                       '42' => __('Saransk'),
                                       '2' => __('Saint-Petersburg'),
                                       '12' => __('Smolensk'),
                                       '239' => __('Sochi'),
                                       '36' => __('Stavropol'),
                                       '10649' => __('Stary Oskol'),
                                       '973' => __('Surgut'),
                                       '13' => __('Tambov'),
                                       '14' => __('Tver'),
                                       '67' => __('Tomsk'),
                                       '15' => __('Tula'),
                                       '195' => __('Ulyanovsk'),
                                       '172' => __('Ufa'),
                                       '76' => __('Khabarovsk'),
                                       '45' => __('Cheboksary'),
                                       '56' => __('Chelyabinsk'),
                                       '1104' => __('Cherkessk'),
                                       '16' => __('Yaroslavl'),
                                       ]), null, ['class' => 'form-select rounded-0 region']) !!}
                            </div>

                            <div id="key-phrase"
                                 @if($object['request']['type'] != 'phrase') style="display: none"@endif>

                                <div class="form-group required">
                                    <label>{{ __('Top 10/20/30') }}</label>
                                    {!! Form::select('count', array_unique([
                                            $object['request']['count'] => $object['request']['count'],
                                            '10' => 10,
                                            '20' => 20,
                                            '30' => 30,
                                            ]), null, ['class' => 'form-select rounded-0 count']) !!}
                                </div>

                                <div class="form-group required" id="ignoredDomainsBlock">
                                    <label id="ignoredDomains">{{ __('Ignored domains') }}</label>
                                    {!! Form::textarea("ignoredDomains", $object['request']['ignoredDomains'],["class" => "form-control ignoredDomains"] ) !!}
                                </div>
                            </div>
                            <div id="site-list" @if($object['request']['type'] != 'list') style="display: none"@endif>
                                <div class="form-group required">
                                    <label>{{ __('List of sites') }}</label>
                                    {!! Form::textarea("siteList", $object['request']['siteList'] ?? null ,["class" => "form-control", 'id'=>'siteList'] ) !!}
                                </div>
                            </div>

                            <div class="form-group required d-flex align-items-center">
                                <span>{{ __('Cut the words shorter') }}</span>
                                <input type="number" class="form form-control col-2 ml-1 mr-1" name="separator"
                                       id="separator"
                                       value="{{ $object['request']['separator'] }}">
                                <span>{{ __('symbols') }}</span>
                            </div>

                            <div class="switch mt-3 mb-3">
                                <div class="d-flex">
                                    <div class="__helper-link ui_tooltip_w">
                                        <div
                                            class="custom-control custom-switch custom-switch-off-danger custom-switch-on-success">
                                            <input type="checkbox"
                                                   class="custom-control-input"
                                                   id="switchNoindex"
                                                   name="noIndex"
                                                   @if($object['request']['noIndex'] == 'true') checked @endif>
                                            <label class="custom-control-label" for="switchNoindex"></label>
                                        </div>
                                    </div>
                                    <p>{{ __('Track the text in the noindex tag') }}</p>
                                </div>

                                <div class="d-flex">
                                    <div class="__helper-link ui_tooltip_w">
                                        <div
                                            class="custom-control custom-switch custom-switch-off-danger custom-switch-on-success">
                                            <input type="checkbox"
                                                   class="custom-control-input"
                                                   id="switchAltAndTitle"
                                                   name="hiddenText"
                                                   @if($object['request']['hiddenText'] == 'true') checked @endif>
                                            <label class="custom-control-label" for="switchAltAndTitle"></label>
                                        </div>
                                    </div>
                                    <p>{{ __('Track words in the alt, title, and data-text attributes') }}</p>
                                </div>

                                <div class="d-flex">
                                    <div class="__helper-link ui_tooltip_w">
                                        <div
                                            class="custom-control custom-switch custom-switch-off-danger custom-switch-on-success">
                                            <input type="checkbox"
                                                   class="custom-control-input"
                                                   id="switchConjunctionsPrepositionsPronouns"
                                                   name="conjunctionsPrepositionsPronouns"
                                                   @if($object['request']['conjunctionsPrepositionsPronouns'] == 'true') checked @endif>
                                            <label class="custom-control-label"
                                                   for="switchConjunctionsPrepositionsPronouns"></label>
                                        </div>
                                    </div>
                                    <p>{{ __('Track conjunctions, prepositions, pronouns') }}</p>
                                </div>

                                <div class="d-flex">
                                    <div class="__helper-link ui_tooltip_w">
                                        <div
                                            class="custom-control custom-switch custom-switch-off-danger custom-switch-on-success">
                                            <input type="checkbox"
                                                   class="custom-control-input"
                                                   id="switchMyListWords"
                                                   name="switchMyListWords"
                                                   @if($object['request']['switchMyListWords'] == 'true') checked @endif>
                                            <label class="custom-control-label" for="switchMyListWords"></label>
                                        </div>
                                    </div>
                                    <span>{{ __('Exclude') }}
                                    <span class="text-muted">{{ __('(your own list of words)') }}</span>
                                </span>
                                </div>

                                <div class="form-group required list-words mt-1"
                                     @if($object['request']['switchMyListWords'] == 'false') style="display:none;" @endif >
                                    {!! Form::textarea('listWords', $object['request']['listWords'],['class' => 'form-control listWords', 'cols' => 8, 'rows' => 5]) !!}
                                </div>
                            </div>

                            @if($admin)
                                <div class="d-flex mt-3">
                                    <div class="__helper-link ui_tooltip_w">
                                        <div
                                            class="custom-control custom-switch custom-switch-off-danger custom-switch-on-success">
                                            <input type="checkbox"
                                                   class="custom-control-input"
                                                   id="searchPassages"
                                                   name="searchPassages"
                                                   @if(isset($object['request']['searchPassages']) ? $object['request']['searchPassages'] : false) checked @endif>
                                            <label class="custom-control-label" for="searchPassages"></label>
                                        </div>
                                    </div>
                                    <span>Поиск пассажей</span>
                                    <span class="__helper-link ui_tooltip_w">
                                        <i class="fa fa-question-circle" style="color: grey"></i>
                                        <span class="ui_tooltip __bottom">
                                            <span class="ui_tooltip_content" style="width: 300px">
                                                {!! __('Relevance search passages hint') !!}
                                            </span>
                                        </span>
                                    </span>
                                </div>
                            @endif

                            <div class="relevance-analyse-actions d-flex flex-column mt-2">
                                <div class="btn-group mb-2">
                                    <button class="btn btn-secondary relevance-analyse-actions__main" id="repeat-queue-scan">
                                        {{ __('Full analysis') }}
                                    </button>
                                    <button type="button" class="btn btn-secondary relevance-analyse-actions__help" tabindex="-1" aria-hidden="true">
                                        <span class="__helper-link ui_tooltip_w" tabindex="0" role="button" aria-label="{{ __('Help') }}">
                                            <i class="fa fa-question-circle"></i>
                                            <span class="ui_tooltip __left">
                                                <span class="ui_tooltip_content relevance-analyse-actions__tooltip">
                                                    {{ __('A survey of the xml service will be conducted in order to get the relevant top sites of competitors. The landing page will also be parsed.') }} <br>
                                                    {{ __('Based on all the data received, an analysis will be performed.') }} <br>
                                                </span>
                                            </span>
                                        </span>
                                    </button>
                                </div>
                                <div class="btn-group mb-2">
                                    <button type="button" class="btn btn-secondary relevance-analyse-actions__main"
                                            id="repeat-queue-competitors-scan"
                                            @if($object['html_main_page'] == '') disabled @endif>
                                        {{ __('Repeated analysis of competitor sites') }}
                                    </button>
                                    <button type="button" class="btn btn-secondary relevance-analyse-actions__help" tabindex="-1" aria-hidden="true">
                                        <span class="__helper-link ui_tooltip_w" tabindex="0" role="button" aria-label="{{ __('Help') }}">
                                            <i class="fa fa-question-circle"></i>
                                            <span class="ui_tooltip __left">
                                                <span class="ui_tooltip_content relevance-analyse-actions__tooltip">
                                                    {{ __('Updating the content of competitors that was received as a result of the last request') }}
                                                </span>
                                            </span>
                                        </span>
                                    </button>
                                </div>
                                <div class="btn-group mb-2">
                                    <button class="btn btn-secondary relevance-analyse-actions__main"
                                            id="repeat-queue-main-page-scan"
                                            @if($object['html_main_page'] == '') disabled @endif>
                                        {{ __('Repeated analysis of the landing page') }}
                                    </button>
                                    <button type="button" class="btn btn-secondary relevance-analyse-actions__help" tabindex="-1" aria-hidden="true">
                                        <span class="__helper-link ui_tooltip_w" tabindex="0" role="button" aria-label="{{ __('Help') }}">
                                            <i class="fa fa-question-circle"></i>
                                            <span class="ui_tooltip __left">
                                                <span class="ui_tooltip_content relevance-analyse-actions__tooltip">
                                                    {{ __('We re-poll the landing page and take data from competitors websites that were received as a result of the last request') }}
                                                </span>
                                            </span>
                                        </span>
                                    </button>
                                </div>
                            </div>

                            <div id="repeat-queue-status"
                                 class="alert alert-info relevance-repeat-queue-status mt-2 mb-0 d-none"
                                 role="status"
                                 aria-live="polite">
                                <span id="repeat-queue-status-text">{{ __('Standing in line for reanalysis') }}</span>
                                <div class="loader d-flex justify-content-center align-items-center" id="loader-repeat-queue"
                                     style="height: 35px; width: 35px"></div>
                            </div>
                        </div>
                    @else
                        <h2>{{ __("You don't have access to this object") }}</h2>
                    @endif
                </div>
            </div>
        </div>
    </div>

    @slot('js')
        <script src="{{ asset('plugins/datatables/jquery.dataTables.min.js') }}"></script>
        @include('layouts.partials.vendor-datatables-js', ['bundle' => 'rb-min'])


        <script src="{{ asset('plugins/datatables/buttons/buttons.min.js') }}"></script>

        <script src="{{ asset('plugins/datatables/buttons/jszip.min.js') }}"></script>

        <script src="{{ asset('plugins/datatables/buttons/vfs_fonts.min.js') }}"></script>
        <script src="{{ asset('plugins/datatables/buttons/html5.min.js') }}"></script>

        <script src="{{ asset('plugins/clipboard/index.min.js') }}"></script>
        <script src="{{ asset('plugins/relevance-analysis/scriptsV6/renderClouds.js') }}?v={{ @filemtime(public_path('plugins/relevance-analysis/scriptsV6/renderClouds.js')) ?: time() }}"></script>
        <script src="{{ asset('plugins/relevance-analysis/scriptsV6/renderUnigramTable.js') }}?v={{ @filemtime(public_path('plugins/relevance-analysis/scriptsV6/renderUnigramTable.js')) ?: time() }}"></script>
        <script src="{{ asset('plugins/relevance-analysis/scriptsV6/renderScannedSitesList.js') }}?v={{ @filemtime(public_path('plugins/relevance-analysis/scriptsV6/renderScannedSitesList.js')) ?: time() }}"></script>
        <script src="{{ asset('plugins/relevance-analysis/scriptsV6/renderTextTable.js') }}?v={{ @filemtime(public_path('plugins/relevance-analysis/scriptsV6/renderTextTable.js')) ?: time() }}"></script>
        <script src="{{ asset('plugins/relevance-analysis/scriptsV6/renderPhrasesTable.js') }}?v={{ @filemtime(public_path('plugins/relevance-analysis/scriptsV6/renderPhrasesTable.js')) ?: time() }}"></script>
        <script src="{{ asset('plugins/relevance-analysis/scriptsV6/renderRecommendationsTable.js') }}?v={{ @filemtime(public_path('plugins/relevance-analysis/scriptsV6/renderRecommendationsTable.js')) ?: time() }}"></script>
        <script src="{{ asset('plugins/relevance-analysis/history/common.js') }}"></script>
        <script>
            $(document).ready(function () {
                $('#app > div > div > div.card-header').append($('#params').html())
                $('#params').remove()
            })

            $('#recButton').click(function () {
                if ($('.pb-3.recommendations').is(':visible')) {
                    $('.pb-3.recommendations').hide()
                    $(this).html("Показать")
                } else {
                    $('.pb-3.recommendations').show()
                    $(this).html("{{ __('Hide') }}")
                }
            });

            $('#check-type').on('change', function () {
                if ($(this).val() === 'list') {
                    $('#key-phrase').hide()
                    $('#site-list').show(300)
                } else {
                    $('#site-list').hide()
                    $('#key-phrase').show(300)
                }
            });

            $('input#switchMyListWords').click(function () {
                if ($(this).is(':checked')) {
                    $('.form-group.required.list-words.mt-1').show(300)
                } else {
                    $('.form-group.required.list-words.mt-1').hide(300)
                }
            })
        </script>
        <script>
            window.relevanceCloudLabels = {
                tfidf: 'tf-idf',
                repetitions: @json(__('Number of repetitions')),
            }
            window.relevanceHybridLabels = {
                text: @json(__('text')),
                links: @json(__('links')),
            }
            var generatedTfIdf = false
            var generatedText = false
            var generatedCompetitorCoverage = false

            function hideHistoryPreloaders() {
                $('#tablesPreloader').hide()
                $('#preloaderBlock').hide()
            }

            function successRequest(history, config) {
                hideHistoryPreloaders()

                let localization = {
                    search: "{{ __('Search') }}",
                    show: "{{ __('show') }}",
                    records: "{{ __('records') }}",
                    noRecords: "{{ __('No records') }}",
                    showing: "{{ __('Showing') }}",
                    from: "{{ __('from') }}",
                    to: "{{ __('to') }}",
                    of: "{{ __('of') }}",
                    entries: "{{ __('entries') }}",
                    ignoredDomain: "{{ __('ignored domain') }}",
                    notGetData: "{{ __('Could not get data from the page') }}",
                    successAnalyse: "{{ __('The page has been successfully analyzed') }}",
                    notTop: "{{ __('the site did not get into the top') }}",
                    hideDomains: "{{ __('hide ignored domains') }}",
                    copyLinks: "{{ __('Copy site links') }}",
                    copy: "{{ __('Copy') }}",
                    csv: "{{ __('CSV') }}",
                    excel: "{{ __('Excel') }}",
                    childWords: "{{ __('Word forms') }}",
                    missingWords: "{{ __('Missing words') }}",
                    success: "{{ __('Successfully') }}",
                    successCopied: "{{ __('Success copied') }}",
                    recommendations: "{{ __('Recommendations for your page') }}",
                };

                if (!history.cleaning) {
                    try {
                        renderTextTable(history.avg, history.main_page)
                        renderRecommendationsTable(history.recommendations, config.recommendations_count, localization)
                        renderUnigramTable(
                            history.unigram_table,
                            config.ltp_count,
                            localization,
                            history.history_id,
                            @json((bool) ($object['request']['searchPassages'] ?? false))
                        );
                        renderPhrasesTable(history.phrases, config.ltps_count, localization)
                        renderClouds(history.clouds_competitors, history.clouds_main_page, history.tf_comp_clouds, false);
                        $('.sites').css({
                            'margin-top': '50px',
                        });
                    } catch (e) {
                        console.error('relevance show-history render', e)
                        $('#preloaderStatus').text("{{ __('An error has occurred, repeat the request.') }}")
                    }
                } else {
                    $('.toast-top-right.success-message').show(300)
                    $('#toast-message').html("{{ __('Your history has been uploaded successfully, but its data has been partially deleted.') }}")
                    setTimeout(function () {
                        $('.toast-top-right.success-message').hide(300)
                    }, 10000)
                }

                renderScannedSitesList(
                    localization,
                    history.sites,
                    history.avg_coverage_percent,
                    config.scanned_sites_count,
                    false,
                    config.boostPercent,
                    history.average_values,
                    {{ $id }},
                    'project_id'
                );

                setTimeout(function () {
                    $('.add-in-ignored-domains').remove()
                    $('.remove-from-ignored-domains').remove()
                    $('.lock-block').remove()
                    $('.dt-button').addClass('btn btn-secondary')
                }, 1500)
            }

            $(document).ready(function () {
                if ($('#main_history_table').length) {
                    $('#main_history_table').DataTable({
                        "order": [[1, "desc"]],
                        "pageLength": 10,
                        "searching": true,
                    });
                }

                if ($('#history_table').length) {
                    $('#history_table').DataTable({
                        "order": [[1, "desc"]],
                        "pageLength": 10,
                        "searching": true,
                    });
                }

                $.ajax({
                    type: "POST",
                    dataType: "json",
                    timeout: 120000,
                    url: @if(!empty($publicShareToken))
                        "{{ route('relevance.public.share.details', $publicShareToken) }}"
                    @else
                        "{{ route('get.details.info') }}"
                    @endif,
                    data: {
                        _token: $('meta[name="csrf-token"]').attr('content'),
                        id: {{ $id }},
                    },
                    success: function (response) {
                        hideHistoryPreloaders()
                        if (response.code === 200) {
                            successRequest(response.history, response.config)
                        } else if (response.code === 415) {
                            $('.toast-top-right.error-message').show(300)
                            $('#message-error-info').html(response.message)
                            $('#preloaderStatus').text(response.message)
                            $('#preloaderBlock').find('img').hide()
                            setTimeout(function () {
                                $('.toast-top-right.error-message').hide(300)
                            }, 10000)
                        }
                    },
                    error: function () {
                        hideHistoryPreloaders()
                        $('#preloaderStatus').text("{{ __('An error has occurred, repeat the request.') }}")
                        $('#preloaderBlock').find('img').hide()
                    },
                });

                $('#repeat-queue-scan').click(function () {
                    $.ajax({
                        type: "POST",
                        dataType: "json",
                        url: '/repeat-scan',
                        data: getData(),
                        success: function (response) {
                            successAjaxRequest(response)
                        },
                        error: function () {
                            $('#toast-container').show(300)
                            setInterval(function () {
                                $('#toast-container').hide(300)
                            }, 3500)
                        }
                    });
                });

                $('#repeat-queue-competitors-scan').click(function () {
                    $.ajax({
                        type: "POST",
                        dataType: "json",
                        url: '/repeat-queue-competitors-scan',
                        data: getData(),
                        success: function (response) {
                            successAjaxRequest(response)
                        },
                        error: function () {
                            $('#toast-container').show(300)
                            setInterval(function () {
                                $('#toast-container').hide(300)
                            }, 3500)
                        }
                    });
                });

                $('#repeat-queue-main-page-scan').click(function () {
                    $.ajax({
                        type: "POST",
                        dataType: "json",
                        url: '/repeat-queue-main-page-scan',
                        data: getData(),
                        success: function (response) {
                            successAjaxRequest(response)
                        },
                        error: function () {
                            $('#toast-container').show(300)
                            setInterval(function () {
                                $('#toast-container').hide(300)
                            }, 3500)
                        }
                    });
                });

                $('.copyInBuffer').click(function () {
                    let area = document.createElement('textarea');
                    area.style.opasity = 0
                    document.body.appendChild(area);
                    area.value = $(this).attr('data-target');
                    area.select();
                    document.execCommand("copy");
                    document.body.removeChild(area);

                    $('.toast-top-right.success-message').show(300)
                    $('#toast-message').html("{{ __('Success') }}")
                    setTimeout(() => {
                        $('.toast-top-right.success-message').hide(300)
                    }, 5000)
                })
            });

            function getData() {
                return {
                    id: $('#hiddenId').val(),
                    type: $('#type').val(),
                    siteList: $('#siteList').val(),
                    link: $('.form-control.link').val(),
                    phrase: $('.form-control.phrase').val(),
                    count: $(".form-select.rounded-0.count").val(),
                    region: $(".form-select.rounded-0.region").val(),
                    ignoredDomains: $(".form-control.ignoredDomains").val(),
                    separator: $("#separator").val(),
                    noIndex: $('#switchNoindex').is(':checked'),
                    hiddenText: $('#switchAltAndTitle').is(':checked'),
                    conjunctionsPrepositionsPronouns: $('#switchConjunctionsPrepositionsPronouns').is(':checked'),
                    switchMyListWords: $('#switchMyListWords').is(':checked'),
                    listWords: $('.form-control.listWords').val(),
                    searchPassages: $('#searchPassages').is(':checked'),
                }
            }

            function showRepeatQueueStatus() {
                var repeatTab = document.querySelector('a[href="#tab_2"]');
                if (repeatTab && typeof bootstrap !== 'undefined') {
                    bootstrap.Tab.getOrCreateInstance(repeatTab).show();
                } else if (repeatTab) {
                    repeatTab.click();
                }

                var $banner = $('#repeat-queue-status');
                $banner.removeClass('d-none alert-success alert-danger').addClass('alert-info');
                $('#repeat-queue-status-text').text('{{ __('Standing in line for reanalysis') }}');
                $('#loader-repeat-queue').show();

                $('html, body').animate({
                    scrollTop: $banner.offset().top - 80
                }, {
                    duration: 370,
                    easing: 'linear'
                });
            }

            function showRepeatQueueOutcome(html, type) {
                var $banner = $('#repeat-queue-status');
                $banner.removeClass('d-none alert-info alert-success alert-danger');

                if (type === 'error') {
                    $banner.addClass('alert-danger');
                } else {
                    $banner.addClass('alert-success');
                }

                $('#repeat-queue-status-text').html(html);
                $('#loader-repeat-queue').hide();
            }

            function hideRepeatQueueStatus() {
                $('#repeat-queue-status').addClass('d-none');
            }

            function showSuccessToast(html, hideAfterMs) {
                var $toast = $('.toast-top-right.success-message');
                $('#toast-message').html(html);
                $toast.stop(true, true).show(300);
                if (window.relevanceSuccessToastTimer) {
                    clearTimeout(window.relevanceSuccessToastTimer);
                }
                if (hideAfterMs !== false) {
                    window.relevanceSuccessToastTimer = setTimeout(function () {
                        $toast.hide(300);
                    }, hideAfterMs || 3500);
                }
            }

            function showErrorToast(html, hideAfterMs) {
                var $toast = $('.toast-top-right.error-message');
                $('#message-error-info').html(html);
                $toast.stop(true, true).show(300);
                if (window.relevanceErrorToastTimer) {
                    clearTimeout(window.relevanceErrorToastTimer);
                }
                window.relevanceErrorToastTimer = setTimeout(function () {
                    $toast.hide(300);
                }, hideAfterMs || 3500);
            }

            function stopAnalysisPolling() {
                if (window.relevanceAnalysisPollTimer) {
                    clearInterval(window.relevanceAnalysisPollTimer);
                    window.relevanceAnalysisPollTimer = null;
                }
            }

            function handleAnalysisPollResponse(response) {
                if (response.message === 'wait') {
                    return;
                }

                if (response.message === 'success') {
                    var completedId = response.completedHistoryId
                        || (response.newObject && response.newObject.id);
                    var hasFreshResult = completedId && completedId > {{ $id }};

                    if (!hasFreshResult) {
                        return;
                    }

                    stopAnalysisPolling();

                    var resultHtml = 'Ваши результаты готовы и размещены по ссылке ' +
                        '<a target="_blank" rel="noopener" href="/show-history/' + completedId + '">Здесь</a>';

                    showRepeatQueueOutcome(resultHtml, 'success');
                    showSuccessToast(resultHtml, false);

                    window.setTimeout(function () {
                        window.location.href = '/show-history/' + completedId;
                    }, 1200);

                    return;
                }

                if (response.message === 'error') {
                    stopAnalysisPolling();
                    var errorHtml = '{{ __('An error has occurred, try again or contact the administrator') }}';
                    showRepeatQueueOutcome(errorHtml, 'error');
                    showErrorToast(errorHtml);
                }
            }

            function pollAnalysisState() {
                $.ajax({
                    type: "POST",
                    dataType: "json",
                    url: '/check-state',
                    data: {
                        id: {{ $id }},
                        since_id: window.relevanceAnalysisSinceId || {{ $id }},
                    },
                    success: function (response) {
                        handleAnalysisPollResponse(response);
                    },
                });
            }

            function startAnalysisPolling() {
                if (window.relevanceAnalysisPollTimer) {
                    clearInterval(window.relevanceAnalysisPollTimer);
                }
                pollAnalysisState();
                window.relevanceAnalysisPollTimer = setInterval(pollAnalysisState, 10000);
            }

            function successAjaxRequest(response) {
                if (response.code === 415) {
                    showErrorToast(response.message);
                } else {
                    window.relevanceAnalysisSinceId = {{ $id }};
                    window.relevanceAnalysisNotifyOnComplete = true;
                    showSuccessToast('{{ __('Repeated analysis added to the queue') }}');
                    showRepeatQueueStatus();
                    startAnalysisPolling();
                }
            }

            @if($object->state == 0)
            $(document).ready(function () {
                window.relevanceAnalysisSinceId = {{ $id }};
                window.relevanceAnalysisNotifyOnComplete = true;
                showRepeatQueueStatus();
                startAnalysisPolling();
            });
            @endif
        </script>
    @endslot
@endcomponent
