@extends('layouts.app')

@section('title', __('Edit tariff value'))

@section('css')
    <link rel="stylesheet" href="{{ asset('css/cabinet-tariff-settings.css') }}">
@endsection

@section('content')
    <div class="cabinet-tariff-settings-page">
        <div class="mb-3">
            <h2 class="h4 mb-1">
                <i class="bi bi-pencil me-2 text-primary"></i>{{ __('Edit tariff value') }}
            </h2>
            <p class="text-secondary small mb-0">
                {{ __('Property') }}: <strong>{{ $setting->name ?: $setting->code }}</strong>
                · <code>{{ $setting->code }}</code>
            </p>
        </div>

        <div class="card shadow-sm">
            <div class="card-body">
                {!! Form::model($settingValue, ['method' => 'PATCH', 'route' => ['tariff-setting-values.update', $settingValue->id]]) !!}
                {!! Form::hidden('tariff_setting_id', $setting->id) !!}
                @include('tariff-setting-values.partials._form', ['settingValue' => $settingValue])
                <div class="d-flex flex-wrap gap-2 mt-4">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-lg me-1"></i>{{ __('Update') }}
                    </button>
                    <a href="{{ route('tariff-settings.index') }}#{{ $setting->code }}" class="btn btn-outline-secondary">{{ __('Back') }}</a>
                </div>
                {!! Form::close() !!}
            </div>
        </div>
    </div>
@endsection
