@php
    $backUrl = $backUrl ?? route('HTML.editor');
    $backLabel = $backLabel ?? __('My projects');
    $breadcrumbs = $breadcrumbs ?? [];
@endphp

<nav class="cabinet-he-nav mb-3" aria-label="{{ __('HTML editor navigation') }}">
    <a href="{{ $backUrl }}" class="btn btn-sm btn-outline-secondary cabinet-he-nav-back">
        <i class="bi bi-arrow-left me-1" aria-hidden="true"></i>{{ $backLabel }}
    </a>
    @if(!empty($breadcrumbs))
        <ol class="breadcrumb cabinet-he-breadcrumb mb-0 mt-2">
            <li class="breadcrumb-item">
                <a href="{{ route('HTML.editor') }}">{{ __('HTML editor') }}</a>
            </li>
            @foreach($breadcrumbs as $item)
                @if(!empty($item['url']))
                    <li class="breadcrumb-item">
                        <a href="{{ $item['url'] }}">{{ $item['label'] }}</a>
                    </li>
                @else
                    <li class="breadcrumb-item active" aria-current="page">{{ $item['label'] }}</li>
                @endif
            @endforeach
        </ol>
    @endif
</nav>
