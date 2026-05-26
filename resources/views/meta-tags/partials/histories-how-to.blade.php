@php
    $steps = [
        ['n' => 1, 'title' => __('Meta tags histories step 1 title'), 'active' => true],
        ['n' => 2, 'title' => __('Meta tags histories step 2 title')],
        ['n' => 3, 'title' => __('Meta tags histories step 3 title')],
    ];
@endphp
<p class="text-secondary small mb-3 cabinet-mt-lead">{{ __('Meta tags histories lead') }}</p>
@include('meta-tags.partials.steps-nav', [
    'navLabel' => __('Meta tags histories steps nav'),
    'steps' => $steps,
])
