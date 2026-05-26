@php
    $steps = [
        ['n' => 1, 'title' => __('Site monitoring form section main'), 'anchor' => 'cabinet-sm-step-1'],
        ['n' => 2, 'title' => __('Site monitoring form section page check'), 'anchor' => 'cabinet-sm-step-2'],
        ['n' => 3, 'title' => __('Site monitoring form section notify'), 'anchor' => 'cabinet-sm-step-3'],
    ];
@endphp
<nav class="cabinet-sm-steps-nav mb-3" aria-label="{{ __('Site monitoring create steps nav') }}">
    <ol class="cabinet-sm-steps-nav__list list-unstyled mb-0">
        @foreach($steps as $index => $step)
            <li class="cabinet-sm-steps-nav__item">
                <a href="#{{ $step['anchor'] }}" class="cabinet-sm-steps-nav__link text-decoration-none">
                    <span class="cabinet-sm-step-badge" aria-hidden="true">{{ $step['n'] }}</span>
                    <span class="cabinet-sm-steps-nav__text">
                        <span class="cabinet-sm-steps-nav__step">{{ __('Site monitoring step label', ['n' => $step['n']]) }}</span>
                        <span class="cabinet-sm-steps-nav__title">{{ $step['title'] }}</span>
                    </span>
                </a>
            </li>
            @if($index < count($steps) - 1)
                <li class="cabinet-sm-steps-nav__sep" aria-hidden="true"><i class="bi bi-chevron-right"></i></li>
            @endif
        @endforeach
    </ol>
</nav>
