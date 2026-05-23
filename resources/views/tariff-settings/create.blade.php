@extends('layouts.app')

@section('title', __('Add limit'))

@section('css')
    <link rel="stylesheet" href="{{ asset('css/cabinet-tariff-settings.css') }}">
@endsection

@section('content')
    <div class="cabinet-tariff-settings-page">
        <div class="mb-3">
            <h2 class="h4 mb-1">
                <i class="bi bi-plus-circle me-2 text-primary"></i>{{ __('Add limit') }}
            </h2>
            <p class="text-secondary small mb-0">{{ __('Create a property (code), then add numeric limits per tariff on the list page.') }}</p>
        </div>

        <div class="alert alert-light border small mb-3">
            <p class="mb-2">
                <i class="bi bi-lightbulb me-1 text-primary"></i>
                {{ __('Need to change limits for an existing module? Do not create a new code — open') }}
                <a href="{{ route('tariff-settings.index') }}">{{ __('Tariffs settings') }}</a>{{ __(', find the property, use + or the pencil in the table.') }}
            </p>
            <p class="mb-2 fw-semibold">{{ __('Code is taken from PHP, not invented arbitrarily') }}</p>
            <p class="text-secondary mb-2">
                {{ __('It must exactly match the key in the application, e.g. TextAnalyzer, DomainInformation, MetaTagsProject. Wrong code = limits will not apply to the module.') }}
            </p>
            @if($existingCodes->isNotEmpty())
                <p class="mb-1 fw-semibold">{{ __('Codes already in the database (reuse when editing values only)') }}</p>
                <ul class="mb-0 ps-3 font-monospace small">
                    @foreach($existingCodes as $row)
                        <li>
                            <a href="{{ route('tariff-settings.index') }}#{{ $row->code }}">{{ $row->code }}</a>
                            @if($row->name)
                                <span class="text-secondary font-sans-serif">— {{ $row->name }}</span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        @if(!empty($suggestedEntry))
            <div class="alert alert-info small mb-3">
                {{ __('Suggested module') }}: <strong>{{ $suggestedEntry['module'] }}</strong>
                · <code>{{ $suggestedEntry['code'] }}</code>
                <div class="text-secondary mt-1">{{ $suggestedEntry['hint'] }}</div>
            </div>
        @endif

        <div class="card shadow-sm">
            <div class="card-body">
                {!! Form::open(['method' => 'POST', 'route' => ['tariff-settings.store']]) !!}
                @include('tariff-settings.partials._form')
                <div class="d-flex flex-wrap gap-2 mt-4">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-lg me-1"></i>{{ __('Save') }}
                    </button>
                    <a href="{{ route('tariff-settings.index') }}" class="btn btn-outline-secondary">{{ __('Back') }}</a>
                </div>
                {!! Form::close() !!}
            </div>
        </div>
    </div>
@endsection
