@component('component.card', ['title' =>  __('Result through analyse') ])
    @slot('css')
        <link rel="stylesheet"
              href="{{ asset('plugins/keyword-generator/css/font-awesome-4.7.0/css/font-awesome.css') }}">
        <link rel="stylesheet" href="{{ asset('plugins/keyword-generator/css/style.css') }}">
        <link rel="stylesheet" href="{{ asset('plugins/jqcloud/css/jqcloud.css') }}">
        <link rel="stylesheet" href="{{ asset('plugins/common/css/datatable.css') }}">
        <link rel="stylesheet" href="{{ asset('plugins/toastr/toastr.css') }}">
        <link rel="stylesheet" href="{{ asset('plugins/relevance-analysis/css/style.css') }}?v={{ @filemtime(public_path('plugins/relevance-analysis/css/style.css')) ?: time() }}">

        <style>
            .fa {
                color: grey;
            }

            tr:hover .fa {
                color: black;
            }

            .sticky {
                position: sticky;
                top: 0;
                background: white
            }

            .render-child {
                background: #f5f7ff !important;
            }

            .child-table {
                z-index: 100 !important;
                word-break: break-word
            }

            .dt-buttons {
                margin-left: 20px;
                float: left;
            }

            .fixed-width {
                z-index: 1003 !important;
                min-width: 400px !important;
                max-width: 400px !important;
            }

            #though-table > tbody > tr > td:nth-child(7),
            #though-table > tbody > tr > td:nth-child(9) {
                background: #ebf0f5;
            }

            #though-table > tbody > tr > td:nth-child(10)::after {
                content: " / {{ $countUniqueScanned }}";
            }

            .RelevanceAnalysis {
                background: oldlace;
            }

            .ui_tooltip_content {
                z-index: 9999;
                width: 350px;
            }

            .dataTables_length > label {
                display: flex;
            }

            .dataTables_length > label > select{
                margin: 0 5px !important;
            }

            .nav-link {
                padding: .5rem .8rem !important;
            }
        </style>
    @endslot

    <div class="card mb-0">
        @include('relevance-analysis.partials.module-top-nav', [
            'active' => 'though',
            'admin' => $admin ?? false,
            'project' => $project ?? null,
        ])

        <div class="card-body">
    @if(count(json_decode($though->cleaning_projects ?? '[]') ?: []) > 0 && $though->cleaning_state == 0)
        <div id="toast-container" class="toast-top-right success-message" style="display:none;">
            <div class="toast toast-success" aria-live="polite">
                <div class="toast-message"
                     id="message-info">{{ __('Projects have been successfully added to the reanalysis queue') }}</div>
            </div>
        </div>

        <div id="thoughId" data-target="{{ $though->id }}"></div>
        <div class="card" id="rescanBlock">
            <div class="card-body">
                {{ __('You have projects whose information has been cleared.') }} <br>
                {{ __('In order to get more detailed information, you can reshoot them, and then re-run the end-to-end analysis.') }}
                <br>

                <div class="btn-group col-lg-3 col-md-5 pl-0">
                    <button type="button" class="btn btn-secondary" data-bs-toggle="modal" data-bs-target="#rescanModal">
                        {{ __('Reshoot all cleaned projects') }}
                    </button>
                    <button type="button" class="btn btn-secondary col-2">
                    <span class="__helper-link ui_tooltip_w">
                        <i class="fa fa-question-circle"></i>
                        <span class="ui_tooltip __right">
                            <span class="ui_tooltip_content">
                                мы удаляем старые результаты сканирования которым больше {{ \App\RelevanceAnalysisConfig::first()->cleaning_interval }} дней.
                            </span>
                        </span>
                    </span>
                    </button>
                </div>

                <div class="modal fade" id="rescanModal" tabindex="-1" aria-labelledby="rescanModalLabel"
                     aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title"
                                    id="rescanModalLabel">{{ __('Reshoot all cleaned projects') }}</h5>
                                <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>
                            <div class="modal-body">
                                {{ __("You're going to reshoot all the cleaned up projects, are you sure?") }}
                                <div id="targetIds" data-target="{{ $though->cleaning_projects }}"></div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"
                                        id="rescanProjects">{{ __('Reshoot') }}</button>
                                <button type="button" class="btn btn-default"
                                        data-bs-dismiss="modal">{{ __('Close') }}</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <div class="text-center" id="preloaderBlock">
        <img src="{{ asset('/img/1485.gif') }}" alt="preloader_gif" width="40" height="40">
        <p>Получено <b id="getCount">{{ $count }}</b> из <b>{{ $allCount }}</b></p>
    </div>
    <div style="display: none" id="though-block">
        <table class="table table-bordered table-striped dtr-inline" id="though-table">
            <thead>
            <tr>
                <th class="sticky"></th>
                <th class="sticky">{{ __('Word') }}</th>
                <th class="sticky fixed-width">{{ __('Intersections') }}</th>
                <th class="sticky">tf</th>
                <th class="sticky">idf</th>
                <th class="sticky">{{ __('How many times the word was included in the text part of the competitors in the analysis') }}</th>
                <th class="sticky">{{ __('How many times the word appeared in the text part of the landing page') }}</th>
                <th class="sticky">{{ __('How many times the word was included in the reference part of competitors in the analysis') }}</th>
                <th class="sticky">{{ __('How many times the word appeared in the link part of the landing page') }}</th>
                <th class="sticky">{{ __('The sum of the number of occurrences') }}</th>
            </tr>
            </thead>
            <tbody>
            @foreach($though->result as $key => $item)
                <tr>
                    <td>
                        <i class="fa fa-plus show-more" data-target="{{ $key }}"
                           data-ra-tip="{{ e(__('Relevance though expand tip')) }}"></i>
                    </td>
                    <td>{{ $key }}</td>
                    <td>
                        <a data-bs-toggle="collapse" href="#collapseExample{{ $key }}"
                           role="button" aria-expanded="false" aria-controls="collapseExample{{ $key }}">
                            {{ __('show') }}
                        </a>
                        <div class="collapse" id="collapseExample{{ $key }}">
                            <table class="child-table">
                                <thead>
                                <tr>
                                    <th class="col-9">{{ __('Link') }}</th>
                                    <th class="col-3">{{ __('Number of entries') }}</th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach($item[$key]['throughLinks'] as $keyLink => $link)
                                    <tr>
                                        <td>
                                            <a href="{{ $keyLink }}" target="_blank">
                                                {{ $keyLink }}
                                            </a>
                                        </td>
                                        <td>{{ $link }}</td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    </td>
                    <td>{{ $item[$key]['tf'] }}</td>
                    <td>{{ $item[$key]['idf'] }}</td>
                    <td>{{ $item[$key]['repeatInText'] }}</td>
                    <td>{{ $item[$key]['repeatInLink'] }}</td>
                    <td>{{ $item[$key]['repeatInTextMainPage'] }}</td>
                    <td>{{ $item[$key]['repeatInLinkMainPage'] }}</td>
                    <td>{{ $item[$key]['throughCount'] }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
        </div>{{-- /.card-body --}}
    </div>{{-- /.card --}}

    @slot('js')
        <script src="{{ asset('plugins/datatables/jquery.dataTables.min.js') }}"></script>
        @include('layouts.partials.vendor-datatables-js', ['bundle' => 'rb-min'])
        <script src="{{ asset('plugins/datatables/buttons/buttons.min.js') }}"></script>
        <script src="{{ asset('plugins/datatables/buttons/jszip.min.js') }}"></script>
        <script src="{{ asset('plugins/datatables/buttons/vfs_fonts.min.js') }}"></script>
        <script src="{{ asset('plugins/datatables/buttons/html5.min.js') }}"></script>
        <script src="{{ asset('js/cabinet-relevance-tooltips.js') }}?v={{ @filemtime(public_path('js/cabinet-relevance-tooltips.js')) ?: time() }}"></script>
        <script>
            let count = {{ $count }};
            let allCount = {{ $allCount }};
            let iterator = count;
            let recordId = "{{ $though->id }}";
            let wordGroupUrl = @json(route('get.though.word.group'));
            let wordGroupCache = {};

            $(document).ready(function () {
                if (typeof window.initRelevanceActionTips === 'function') {
                    window.initRelevanceActionTips(document);
                }
                let thoughTable = $('#though-table').DataTable({
                    "order": [[3, "desc"]],
                    "pageLength": 50,
                    "searching": true,
                    dom: 'lBfrtip',
                    buttons: [
                        { extend: 'copy', text: @json(__('Copy button')) },
                        { extend: 'csv', text: 'CSV' },
                        { extend: 'excel', text: 'Excel' }
                    ],
                    language: {
                        processing: @json(__('Loading records')),
                        search: @json(__('Search') . ':'),
                        lengthMenu: @json(__('show') . ' _MENU_ ' . __('records')),
                        emptyTable: @json(__('No records')),
                        zeroRecords: @json(__('No records')),
                        info: @json(__('Showing') . ' ' . __('from') . ' _START_ ' . __('to') . ' _END_ ' . __('of') . ' _TOTAL_ ' . __('entries')),
                        infoEmpty: @json(__('Showing') . ' ' . __('from') . ' 0 ' . __('to') . ' 0 ' . __('of') . ' 0 ' . __('entries')),
                        infoFiltered: @json(__('DataTables info filtered')),
                        paginate: {
                            first: '«',
                            last: '»',
                            next: '»',
                            previous: '«'
                        },
                        buttons: {
                            copyTitle: @json(__('Copy button')),
                            copySuccess: {
                                _: @json(__('DataTables copy success many')),
                                1: @json(__('DataTables copy success one'))
                            }
                        }
                    }
                });
                $('.dt-button').addClass('btn btn-secondary')

                setTimeout(() => {
                    getNextItems(recordId, thoughTable, count, iterator, allCount)
                }, 3000)
            });

            function getNextItems(recordId, table, count, iterator, allCount) {
                $.ajax({
                    type: "POST",
                    dataType: "json",
                    url: "{{ route('get.slice.result') }}",
                    data: {
                        id: recordId,
                        count: count
                    },
                    success: function (response) {
                        $.each(response.elems, function (key, value) {
                            let tbody = ''

                            $.each(value[key]['throughLinks'], function (keyLink, link) {
                                tbody +=
                                    '<tr> ' +
                                    '   <td> ' +
                                    '       <a href="' + keyLink + '" target="_blank"> ' + keyLink + '</a> ' +
                                    '   </td>' +
                                    '   <td>' + link + '</td>' +
                                    '</tr>'
                            })

                            let ChildTable = '<td> ' +
                                '<a data-bs-toggle="collapse" href="#collapseExample' + key + '" role="button" aria-expanded="false" aria-controls="collapseExample' + key + '">' +
                                'Показать</a> ' +
                                '<div class="collapse" id="collapseExample' + key + '"> ' +
                                '<table class="child-table"> ' +
                                '   <thead> ' +
                                '       <tr> ' +
                                '           <th class="col-9">Ссылка</th> ' +
                                '           <th class="col-3">Кол-во вхождений</th> ' +
                                '       </tr> ' +
                                '   </thead> ' +
                                '<tbody> ' +
                                '</tbody> ' +
                                tbody +
                                '</table> ' +
                                '</div> ' +
                                '</td>'

                            table.row.add({
                                0: '<i class="fa fa-plus show-more" data-target="' + key + '"' +
                                    (typeof window.relevanceActionTipAttr === 'function' ? window.relevanceActionTipAttr(@json(__('Relevance though expand tip'))) : '') +
                                    '></i> ',
                                1: key,
                                2: ChildTable,
                                3: (value[key]['tf']).toFixed(5),
                                4: (value[key]['idf']).toFixed(5),
                                5: value[key]['repeatInText'],
                                6: value[key]['repeatInTextMainPage'],
                                7: value[key]['repeatInLink'],
                                8: value[key]['repeatInLinkMainPage'],
                                9: value[key]['throughCount']
                            });
                        })
                        $('#getCount').html(count)
                        count += iterator
                        if (count < allCount) {
                            $(document).ready(function () {
                                setTimeout(() => {
                                    getNextItems(recordId, table, count, iterator, allCount)
                                }, 1000)
                            })
                        } else {
                            $(document).ready(function () {
                                $('#preloaderBlock').hide(300);
                                $('#though-block').show()

                                $('.sticky').click(function () {
                                    $('.render-child').remove()
                                });
                                if (typeof window.initRelevanceActionTips === 'function') {
                                    window.initRelevanceActionTips(document);
                                }
                            })
                        }
                    },
                });
            }

            function loadWordGroup(word, done) {
                if (wordGroupCache[word]) {
                    done(wordGroupCache[word]);
                    return;
                }
                $.ajax({
                    type: 'POST',
                    dataType: 'json',
                    url: wordGroupUrl,
                    data: { id: recordId, word: word },
                    success: function (response) {
                        var group = (response && response.group) ? response.group : {};
                        wordGroupCache[word] = group;
                        done(group);
                    },
                    error: function () {
                        done({});
                    }
                });
            }

            function renderWordChildren(tr, target, group) {
                $("tr[data-target='" + target + "']").remove();
                $.each(group, function (key, value) {
                    if (key !== target) {
                        let childRows = ''

                        $.each(value['throughLinks'] || {}, function (key2, value2) {
                            childRows +=
                                '<tr>' +
                                '   <td>' + key2 + '</td>' +
                                '   <td>' + value2 + '</td>' +
                                '</tr>'
                        })

                        let childTable =
                            '<table class="child-table">' +
                            '   <thead>' +
                            '       <tr>' +
                            '           <th class="col-9">Ссылка</th>' +
                            '           <th class="col-3">Кол-во вхождений</th>' +
                            '       </tr>' +
                            '   </thead>' +
                            '   <tbody>' +
                            childRows +
                            '   </tbody>' +
                            '</table>'
                        tr.after(
                            '<tr class="render-child" data-target="' + target + '">' +
                            '   <td class="remove-child" data-target="' + target + '"' +
                            (typeof window.relevanceActionTipAttr === 'function' ? window.relevanceActionTipAttr(@json(__('Relevance though collapse tip'))) : '') +
                            '> <i class="fa fa-minus" ></i></td>' +
                            '   <td>' + key + '</td>' +
                            '   <td>' +
                            '       <a data-bs-toggle="collapse" href="#childTable' + key + '" role="button" aria-expanded="false" aria-controls="childTable' + key + '">' +
                            '           {{ __('show') }}</a>' +
                            '       <div class="collapse" id="childTable' + key + '">' +
                            childTable +
                            '       </div>' +
                            '   </td>' +
                            '   <td>' + (Number(value['tf'] || 0)).toFixed(6) + '</td>' +
                            '   <td>' + (Number(value['idf'] || 0)).toFixed(6) + '</td>' +
                            '   <td>' + value['repeatInText'] + '</td>' +
                            '   <td>' + value['repeatInTextMainPage'] + '</td>' +
                            '   <td>' + value['repeatInLink'] + '</td>' +
                            '   <td>' + value['repeatInLinkMainPage'] + '</td>' +
                            '   <td>' + value['throughCount'] + '</td>' +
                            '</tr>'
                        )
                    }
                })
                removeElems()
                if (typeof window.initRelevanceActionTips === 'function') {
                    window.initRelevanceActionTips(document);
                }
            }

            setInterval(() => {
                $('.show-more').unbind().on('click', function () {
                    let tr = $(this).parent().parent()
                    let target = $(this).attr('data-target')
                    loadWordGroup(target, function (group) {
                        renderWordChildren(tr, target, group);
                    });
                })
            }, 100)

            function removeElems() {
                $('.remove-child').click(function () {
                    let target = $(this).attr('data-target')
                    $("tr[data-target='" + target + "']").remove();
                })
            }

            $('#rescanProjects').click(function () {
                $.ajax({
                    type: "POST",
                    dataType: "json",
                    url: "{{ route('rescan.projects') }}",
                    data: {
                        ids: $('#targetIds').attr('data-target'),
                        thoughId: $('#thoughId').attr('data-target')
                    },
                    success: function () {
                        $('#toast-container').show()
                        setTimeout(() => {
                            $('#rescanBlock').remove()
                        }, 5000)
                    },
                });
            })
        </script>
    @endslot
@endcomponent
