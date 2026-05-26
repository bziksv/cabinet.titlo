@php
    $steps = [
        ['n' => 1, 'title' => __('Meta tags step 1 title'), 'anchor' => 'cabinet-mt-step-1', 'active' => true],
        ['n' => 2, 'title' => __('Meta tags step 2 title'), 'anchor' => 'cabinet-mt-step-2'],
        ['n' => 3, 'title' => __('Meta tags step 3 title'), 'anchor' => 'cabinet-mt-step-3'],
    ];
@endphp
<p class="text-secondary small mb-3 cabinet-mt-lead">{{ __('Meta tags index lead') }}</p>
@include('meta-tags.partials.steps-nav', [
    'navLabel' => __('Meta tags index steps nav'),
    'steps' => $steps,
])
