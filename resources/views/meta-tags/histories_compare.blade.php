@component('component.card', [
    'title' => __('Meta tags compare page title', ['left' => $history->id, 'right' => $historyCompare->id]),
    'titleHtml' => e(__('Meta tags compare page title', ['left' => $history->id, 'right' => $historyCompare->id]))
        . view('partials.cabinet-module-version-badge', ['configKey' => 'cabinet-meta-tags'])->render(),
])
    @slot('css')
        <link rel="stylesheet" href="{{ asset('plugins/toastr/toastr.min.css') }}">
        <link rel="stylesheet" href="{{ asset('css/cabinet-meta-tags.css') }}?v={{ @filemtime(public_path('css/cabinet-meta-tags.css')) ?: time() }}">
    @endslot

    <div class="cabinet-mt-page cabinet-mt-compare-page">
        <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
            <a href="{{ url('/meta-tags/histories/' . $history->meta_tag_id) }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1" aria-hidden="true"></i>{{ __('Meta tags compare back') }}
            </a>
            <span class="text-secondary small ms-auto">
                {{ $history->created_at->format('d.m.Y H:i') }} (#{{ $history->id }})
                ↔
                {{ $historyCompare->created_at->format('d.m.Y H:i') }} (#{{ $historyCompare->id }})
            </span>
        </div>

        <p class="text-secondary small mb-3">{{ __('Meta tags compare lead') }}</p>
        @php
            $metaDiffFields = $metaDiffFields ?? config('cabinet-meta-tags.compare_meta_fields', []);
        @endphp

        <div class="mb-3 cabinet-mt-filter">
            <label class="form-label" for="cabinet-mt-compare-filter">{{ __('Filter') }}</label>
            <select class="form-select form-select-sm" id="cabinet-mt-compare-filter">
                <optgroup label="{{ __('Meta tags filter group general') }}">
                    <option value="all">{{ __('All') }}</option>
                    <option value="__diff__">{{ __('Meta tags filter changed only') }}</option>
                </optgroup>
                @if(count($filterDiff))
                    <optgroup label="{{ __('Meta tags filter group diff') }}">
                        @foreach($filterDiff as $tag => $label)
                            <option value="diff:{{ $tag }}">{{ __('Meta tags filter diff in', ['tag' => $label]) }}</option>
                        @endforeach
                    </optgroup>
                @endif
                @if(count($filterErrors))
                    <optgroup label="{{ __('Meta tags filter group errors') }}">
                        @foreach($filterErrors as $tag => $label)
                            <option value="err:{{ $tag }}">{{ $label }}</option>
                        @endforeach
                    </optgroup>
                @endif
            </select>
            <div class="form-text">{{ __('Meta tags compare filter hint') }}</div>
        </div>

        <div id="cabinet-mt-compare-accordion" class="cabinet-mt-compare-list">
            @foreach($collection as $url => $item)
                @php
                    $errorTags = isset($item['error_tags']) ? implode(',', array_keys($item['error_tags'])) : '';
                    $diffTagKeys = isset($item['diff_tags']) ? array_keys($item['diff_tags']) : [];
                    $diffTags = implode(',', $diffTagKeys);
                    $hasDiff = ! empty($diffTagKeys);
                    $hasMetaDiff = (bool) array_intersect($diffTagKeys, $metaDiffFields);
                    $hasOtherDiff = $hasDiff && count(array_diff($diffTagKeys, $metaDiffFields)) > 0;
                    $cardDiffClass = $hasMetaDiff
                        ? ' cabinet-mt-compare-card--diff-meta'
                        : ($hasOtherDiff ? ' cabinet-mt-compare-card--diff-other' : '');
                @endphp
                <div class="card shadow-sm border-0 mb-2 cabinet-mt-compare-card{{ $cardDiffClass }}"
                     data-error-tags="{{ $errorTags }}"
                     data-diff-tags="{{ $diffTags }}"
                     data-has-diff="{{ $hasDiff ? '1' : '0' }}">
                    <div class="card-header py-2 d-flex flex-wrap align-items-start gap-2">
                        <h4 class="card-title h6 mb-0 flex-grow-1">
                            <a class="d-block accordion-title collapsed text-break"
                               data-bs-toggle="collapse"
                               href="#collapse{{ $loop->index }}"
                               role="button"
                               aria-expanded="false">
                                <i class="bi bi-chevron-right cabinet-mt-caret me-1" aria-hidden="true"></i>{{ $url }}
                            </a>
                        </h4>
                        <div class="d-flex flex-wrap gap-1 align-items-center">
                            @if(!empty($item['diff_tags']))
                                @foreach($item['diff_tags'] as $diffTag)
                                    @php $diffBadgeMeta = in_array($diffTag, $metaDiffFields, true); @endphp
                                    <span class="badge {{ $diffBadgeMeta ? 'cabinet-mt-diff-badge--meta' : 'cabinet-mt-diff-badge--other' }}">
                                        {{ __('Meta tags filter diff in', ['tag' => $filterDiff[$diffTag] ?? $diffTag]) }}
                                    </span>
                                @endforeach
                            @endif
                            @if(!empty($item['badge']))
                                @foreach($item['badge'] as $snapshotLabel => $errors)
                                    @foreach($errors as $tag => $errorHtml)
                                        <span class="cabinet-mt-compare-badge" data-tag="{{ $tag }}">{!! implode('', $errorHtml) !!}</span>
                                    @endforeach
                                @endforeach
                            @endif
                        </div>
                    </div>

                    <div id="collapse{{ $loop->index }}" class="collapse" data-bs-parent="#cabinet-mt-compare-accordion">
                        <div class="card-body pt-0">
                            <div class="row g-3">
                                <div class="col-lg-6">
                                    <div class="cabinet-mt-compare-pane">
                                        <div class="cabinet-mt-compare-pane__title">
                                            {{ $item['card']['date'] ?? '' }} (#{{ $item['card']['id'] ?? '' }})
                                        </div>
                                        @include('meta-tags.partials.compare-tags-table', [
                                            'tags' => $item['card']['tags'],
                                            'errors' => $item['card']['error'],
                                            'diffTags' => $item['diff_tags'] ?? [],
                                            'peerTags' => $item['card_compare']['tags'] ?? null,
                                            'compareMode' => true,
                                            'metaDiffFields' => $metaDiffFields,
                                        ])
                                    </div>
                                </div>
                                @if(isset($item['card_compare']))
                                    <div class="col-lg-6">
                                        <div class="cabinet-mt-compare-pane">
                                            <div class="cabinet-mt-compare-pane__title">
                                                {{ $item['card_compare']['date'] ?? '' }} (#{{ $item['card_compare']['id'] ?? '' }})
                                            </div>
                                            @include('meta-tags.partials.compare-tags-table', [
                                                'tags' => $item['card_compare']['tags'],
                                                'errors' => $item['card_compare']['error'],
                                                'diffTags' => $item['diff_tags'] ?? [],
                                                'peerTags' => $item['card']['tags'] ?? null,
                                                'compareMode' => true,
                                                'metaDiffFields' => $metaDiffFields,
                                            ])
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <a href="{{ route('meta.history.export_compare', [$history->id, $historyCompare->id]) }}"
           class="btn btn-outline-primary btn-sm mt-3">
            <i class="bi bi-download me-1" aria-hidden="true"></i>{{ __('Export') }}
        </a>
    </div>

    @slot('js')
        <script src="{{ asset('plugins/toastr/toastr.min.js') }}"></script>
        <script>
            toastr.options = { timeOut: 1500 };

            (function ($) {
                function parseTags(raw) {
                    return String(raw || '')
                        .split(',')
                        .map(function (t) { return $.trim(t); })
                        .filter(Boolean);
                }

                function applyCompareFilter() {
                    var selected = $('#cabinet-mt-compare-filter').val();
                    var $cards = $('#cabinet-mt-compare-accordion .cabinet-mt-compare-card');

                    if (selected === 'all') {
                        $cards.removeClass('d-none');
                        return;
                    }

                    if (selected === '__diff__') {
                        $cards.each(function () {
                            var show = $(this).attr('data-has-diff') === '1';
                            $(this).toggleClass('d-none', !show);
                        });
                        return;
                    }

                    if (selected.indexOf('diff:') === 0) {
                        var diffTag = selected.slice(5);
                        $cards.each(function () {
                            var tags = parseTags($(this).attr('data-diff-tags'));
                            $(this).toggleClass('d-none', tags.indexOf(diffTag) === -1);
                        });
                        return;
                    }

                    if (selected.indexOf('err:') === 0) {
                        var errTag = selected.slice(4);
                        $cards.each(function () {
                            var tags = parseTags($(this).attr('data-error-tags'));
                            $(this).toggleClass('d-none', tags.indexOf(errTag) === -1);
                        });
                        return;
                    }

                    $cards.each(function () {
                        var tags = parseTags($(this).attr('data-error-tags'));
                        $(this).toggleClass('d-none', tags.indexOf(selected) === -1);
                    });
                }

                $('#cabinet-mt-compare-filter').on('change', applyCompareFilter);

                $('#cabinet-mt-compare-accordion .cabinet-mt-compare-card').each(function () {
                    var $card = $(this);
                    if ($card.find('tbody tr.cabinet-mt-compare-row--diff').length) {
                        $card.addClass('cabinet-mt-compare-card--diff');
                    }
                });
            })(jQuery);
        </script>
    @endslot
@endcomponent
