@component('component.card', [
    'title' => __('Meta tags'),
    'titleHtml' => e(__('Meta tags')) . view('partials.cabinet-module-version-badge', ['configKey' => 'cabinet-meta-tags'])->render(),
])
    @slot('css')
        <link rel="stylesheet" href="{{ asset('plugins/toastr/toastr.min.css') }}">
        <link rel="stylesheet" href="{{ asset('css/cabinet-meta-tags.css') }}?v={{ @filemtime(public_path('css/cabinet-meta-tags.css')) ?: time() }}">
    @endslot

    <div class="cabinet-mt-page">
        @include('meta-tags.partials.module-nav', ['active' => 'module'])
        @include('meta-tags.partials.how-to-steps')

        <meta-tags :lang='@json($lang)' :tags-options='@json($tagsOptions)'></meta-tags>
    </div>

    @slot('js')
        <script src="{{ asset('plugins/toastr/toastr.min.js') }}"></script>
        <script src="{{ asset('plugins/datatables/search.js') }}"></script>
        <script>
            toastr.options = {
                preventDuplicates: true,
                timeOut: 1500
            };
            search(null, false);
        </script>
    @endslot
@endcomponent
