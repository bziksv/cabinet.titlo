@php
    $steps = $steps ?? [];
    $navLabel = $navLabel ?? '';
@endphp
<nav class="cabinet-mt-steps-nav mb-3" aria-label="{{ $navLabel }}">
    <ol class="cabinet-mt-steps-nav__list list-unstyled mb-0">
        @foreach($steps as $index => $step)
            <li class="cabinet-mt-steps-nav__item{{ !empty($step['active']) ? ' is-active' : '' }}">
                @if(!empty($step['anchor']))
                    <a href="#{{ $step['anchor'] }}" class="cabinet-mt-steps-nav__link text-decoration-none">
                        <span aria-hidden="true">{{ $step['n'] }}</span>
                        {{ $step['title'] }}
                    </a>
                @else
                    <span class="cabinet-mt-steps-nav__link">
                        <span aria-hidden="true">{{ $step['n'] }}</span>
                        {{ $step['title'] }}
                    </span>
                @endif
            </li>
        @endforeach
    </ol>
</nav>
