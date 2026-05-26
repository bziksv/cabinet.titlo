@component('component.card', ['title' => __('History')])

    @slot('css')
        <link rel="stylesheet" href="{{ asset('plugins/toastr/toastr.min.css') }}">
        <link rel="stylesheet" href="{{ asset('css/cabinet-meta-tags.css') }}?v={{ @filemtime(public_path('css/cabinet-meta-tags.css')) ?: time() }}">
    @endslot

    <div class="cabinet-mt-page">
        <meta-tags-history :history-id="{{ $historyId }}" :lang='@json($lang)'></meta-tags-history>
    </div>

    @slot('js')
        <!-- Toastr -->
        <script src="{{ asset('plugins/toastr/toastr.min.js') }}"></script>

        <script>
            toastr.options = {
                "timeOut": "1000"
            };
        </script>


    @endslot


@endcomponent
