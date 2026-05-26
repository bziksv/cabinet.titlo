@php
    $steps = [
        ['n' => 1, 'title' => __('Domain information form section domain'), 'anchor' => 'cabinet-di-step-1'],
        ['n' => 2, 'title' => __('Domain information form section dns'), 'anchor' => 'cabinet-di-step-2'],
        ['n' => 3, 'title' => __('Domain information form section finish'), 'anchor' => 'cabinet-di-step-3'],
    ];
@endphp
<nav class="cabinet-di-steps-nav mb-3" aria-label="{{ __('Domain information create steps nav') }}">
    <ol class="cabinet-di-steps-nav__list list-unstyled mb-0">
        @foreach($steps as $index => $step)
            <li class="cabinet-di-steps-nav__item">
                <a href="#{{ $step['anchor'] }}" class="cabinet-di-steps-nav__link text-decoration-none">
                    <span class="cabinet-di-step-badge" aria-hidden="true">{{ $step['n'] }}</span>
                    <span class="cabinet-di-steps-nav__text">
                        <span class="cabinet-di-steps-nav__step">{{ __('Domain information step label', ['n' => $step['n']]) }}</span>
                        <span class="cabinet-di-steps-nav__title">{{ $step['title'] }}</span>
                    </span>
                </a>
            </li>
            @if($index < count($steps) - 1)
                <li class="cabinet-di-steps-nav__sep" aria-hidden="true"><i class="bi bi-chevron-right"></i></li>
            @endif
        @endforeach
    </ol>
</nav>
