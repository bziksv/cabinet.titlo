@extends('layouts.app')

@section('title', __('Profile'))

@section('css')
    <link rel="stylesheet" href="{{ asset('plugins/select2/css/select2.css') }}">
    <link rel="stylesheet" href="{{ asset('plugins/select2-bootstrap4-theme/select2-bootstrap4.css') }}">
    <link rel="stylesheet" href="{{ asset('css/cabinet-profile.css') }}">
@endsection

@section('content')
    @php
        $displayName = $user->fullName ?: $user->email;
        $initials = mb_strtoupper(
            mb_substr(trim((string) $user->name), 0, 1)
            . mb_substr(trim((string) $user->last_name), 0, 1)
        ) ?: mb_strtoupper(mb_substr($user->email, 0, 1));
        $balanceFormatted = number_format((float) $user->balance, 0, '.', ' ');
        $emailPending = $user->email_verified_at === null;
        $avatarUrl = $user->image ?: asset('img/user-icon.svg');
    @endphp

    <div class="cabinet-profile-page">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
            <h2 class="h4 mb-0">
                <i class="bi bi-person-circle me-2 text-primary" aria-hidden="true"></i>{{ __('Profile') }}
            </h2>
        </div>

        @include('profile.partials.stats')

        <div class="row g-3">
            <div class="col-lg-3">
                @include('profile.partials.aside')
            </div>

            <div class="col-lg-9">
                <div class="card cabinet-profile-tabs-card">
                    <div class="card-header p-0 border-bottom-0">
                        <ul class="nav nav-tabs card-header-tabs px-2 pt-2 flex-nowrap overflow-auto" id="cabinet-profile-tabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="profile-data-tab" data-bs-toggle="tab"
                                        data-bs-target="#profile-data" type="button" role="tab"
                                        aria-controls="profile-data" aria-selected="true">
                                    <i class="bi bi-person me-1"></i>{{ __('Profile') }}
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="profile-password-tab" data-bs-toggle="tab"
                                        data-bs-target="#profile-password" type="button" role="tab"
                                        aria-controls="profile-password" aria-selected="false">
                                    <i class="bi bi-shield-lock me-1"></i>{{ __('Password') }}
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="profile-telegram-tab" data-bs-toggle="tab"
                                        data-bs-target="#profile-telegram" type="button" role="tab"
                                        aria-controls="profile-telegram" aria-selected="false">
                                    <i class="bi bi-telegram me-1"></i>{{ __('Telegram bot') }}
                                    @if($telegramConnected ?? false)
                                        <span class="badge text-bg-success ms-1">OK</span>
                                    @endif
                                </button>
                            </li>
                            @hasanyrole('Super Admin|admin')
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="profile-tariff-tab" data-bs-toggle="tab"
                                            data-bs-target="#profile-tariff" type="button" role="tab"
                                            aria-controls="profile-tariff" aria-selected="false">
                                        <i class="bi bi-sliders me-1"></i>{{ __('Tariff') }}
                                    </button>
                                </li>
                            @endhasanyrole
                        </ul>
                    </div>
                    <div class="card-body">
                        <div class="tab-content">
                            <div class="tab-pane fade show active" id="profile-data" role="tabpanel" aria-labelledby="profile-data-tab" tabindex="0">
                                @if($emailPending)
                                    <div class="alert alert-warning d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                                        <span class="small mb-0">
                                            <i class="bi bi-envelope-exclamation me-1"></i>
                                            {{ __('Before proceeding, please check your email for a verification link.') }}
                                        </span>
                                        <form method="POST" action="{{ route('verification.resend') }}" class="mb-0">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-warning">
                                                {{ __('click here to request another') }}
                                            </button>
                                        </form>
                                    </div>
                                @endif

                                {!! Form::model($user, ['method' => 'POST', 'enctype' => 'multipart/form-data', 'route' => ['profile.update'], 'class' => 'row g-3', 'id' => 'cabinet-profile-form']) !!}
                                <div class="col-12">
                                    <label class="form-label">{{ __('Image') }}</label>
                                    <div class="card bg-body-tertiary border">
                                        <div class="card-body d-flex flex-wrap align-items-center gap-3">
                                            <img src="{{ $avatarUrl }}"
                                                 alt="{{ $displayName }}"
                                                 class="cabinet-profile-preview rounded-circle border"
                                                 id="cabinet-profile-avatar-preview"
                                                 width="80"
                                                 height="80">
                                            <div class="flex-grow-1 min-w-0">
                                                {!! Form::file('image', [
                                                    'class' => 'form-control' . ($errors->has('image') ? ' is-invalid' : ''),
                                                    'accept' => '.jpg,.jpeg,.png,image/jpeg,image/png',
                                                    'id' => 'cabinet-profile-image-input',
                                                ]) !!}
                                                <div class="form-text">{{ __('JPG or PNG, up to 2 MB, from 200×200 px.') }}</div>
                                                @error('image') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                                            </div>
                                            @if($user->image)
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" name="remove_avatar" value="1" id="remove_avatar">
                                                    <label class="form-check-label" for="remove_avatar">{{ __('Remove photo') }}</label>
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    {!! Form::label('name', __('Name'), ['class' => 'form-label']) !!}
                                    {!! Form::text('name', null, ['class' => 'form-control' . ($errors->has('name') ? ' is-invalid' : ''), 'required' => true]) !!}
                                    @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-md-6">
                                    {!! Form::label('last_name', __('Last name'), ['class' => 'form-label']) !!}
                                    {!! Form::text('last_name', null, ['class' => 'form-control' . ($errors->has('last_name') ? ' is-invalid' : ''), 'required' => true]) !!}
                                    @error('last_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-md-6">
                                    {!! Form::label('email', __('Email'), ['class' => 'form-label']) !!}
                                    {!! Form::email('email', null, ['class' => 'form-control' . ($errors->has('email') ? ' is-invalid' : ''), 'required' => true]) !!}
                                    @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    @if($emailPending)
                                        <div class="form-text text-warning">{{ __('After changing email you will need to verify it again.') }}</div>
                                    @endif
                                </div>
                                <div class="col-md-6">
                                    {!! Form::label('lang', __('Lang'), ['class' => 'form-label']) !!}
                                    {!! Form::select('lang', $lang, null, ['class' => 'form-select flags' . ($errors->has('lang') ? ' is-invalid' : '')]) !!}
                                    @error('lang') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-12">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-check-lg me-1"></i>{{ __('Save') }}
                                    </button>
                                </div>
                                {!! Form::close() !!}
                            </div>

                            <div class="tab-pane fade" id="profile-password" role="tabpanel" aria-labelledby="profile-password-tab" tabindex="0">
                                {!! Form::model($user, ['method' => 'PATCH', 'route' => ['profile.password'], 'class' => 'row g-3']) !!}
                                <div class="col-12">
                                    <div class="alert alert-light border small mb-0">
                                        <i class="bi bi-info-circle me-1 text-primary"></i>
                                        {{ __('Use at least 8 characters. After saving you will stay logged in.') }}
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    {!! Form::label('password', __('New password'), ['class' => 'form-label']) !!}
                                    <div class="input-group">
                                        {!! Form::password('password', [
                                            'id' => 'password',
                                            'class' => 'form-control cabinet-profile-password' . ($errors->has('password') ? ' is-invalid' : ''),
                                            'autocomplete' => 'new-password',
                                        ]) !!}
                                        <button type="button" class="btn btn-outline-secondary cabinet-profile-toggle-pwd" data-target="password" title="{{ __('Show password') }}">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                        <button type="button" class="btn btn-outline-secondary" id="generate-password">
                                            {{ __('Generate password') }}
                                        </button>
                                    </div>
                                    @error('password') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-md-6">
                                    {!! Form::label('password_confirmation', __('Confirm password'), ['class' => 'form-label']) !!}
                                    <div class="input-group">
                                        {!! Form::password('password_confirmation', [
                                            'id' => 'password_confirmation',
                                            'class' => 'form-control cabinet-profile-password' . ($errors->has('password_confirmation') ? ' is-invalid' : ''),
                                            'autocomplete' => 'new-password',
                                        ]) !!}
                                        <button type="button" class="btn btn-outline-secondary cabinet-profile-toggle-pwd" data-target="password_confirmation" title="{{ __('Show password') }}">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                    </div>
                                    @error('password_confirmation') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-12">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-shield-check me-1"></i>{{ __('Save') }}
                                    </button>
                                </div>
                                {!! Form::close() !!}
                            </div>

                            <div class="tab-pane fade" id="profile-telegram" role="tabpanel" aria-labelledby="profile-telegram-tab" tabindex="0">
                                @if (session('status'))
                                    <div class="alert alert-info">{{ session('status') }}</div>
                                @endif
                                <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                                    <span class="fw-semibold">{{ __('Connection status') }}:</span>
                                    @if($telegramConnected ?? false)
                                        <span class="badge text-bg-success">{{ __('Telegram connected') }}</span>
                                    @else
                                        <span class="badge text-bg-secondary">{{ __('Not connected') }}</span>
                                    @endif
                                </div>
                                <p class="text-secondary">{{ __('Get notifications in Telegram about monitoring and other events.') }}</p>
                                <p class="small text-secondary mb-3">
                                    {{ __('Site monitoring telegram profile hint') }}
                                    <a href="{{ route('site.monitoring') }}">{{ __('Monitored domains') }}</a>
                                    @if(\App\User::isUserAdmin())
                                        · <a href="{{ route('site.monitoring.config') }}">{{ __('Global notification settings') }}</a>
                                    @endif
                                </p>
                                <div class="d-flex flex-wrap gap-2">
                                    <a href="https://t.me/RedBoxServiceBot?start={{ base64_encode($user->email) }}"
                                       target="_blank"
                                       rel="noopener noreferrer"
                                       class="btn btn-primary">
                                        <i class="bi bi-telegram me-1"></i>{{ __('Subscribe to notifications') }}
                                    </a>
                                    @if ($telegramConnected ?? false)
                                        <a href="{{ route('profile.test-telegram-notify') }}" class="btn btn-outline-secondary">
                                            <i class="bi bi-bell me-1"></i>{{ __('Send test notification') }}
                                        </a>
                                    @endif
                                </div>
                                @if (!($telegramConnected ?? false))
                                    <p class="small text-secondary mt-3 mb-0">{{ __('After subscribing in Telegram, refresh this page.') }}</p>
                                @endif
                            </div>

                            @hasanyrole('Super Admin|admin')
                                <div class="tab-pane fade" id="profile-tariff" role="tabpanel" aria-labelledby="profile-tariff-tab" tabindex="0">
                                    @include('profile._tariff', ['embedded' => true])
                                </div>
                            @endhasanyrole
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('js')
    <script src="{{ asset('plugins/select2/js/select2.js') }}"></script>
    <script>
        (function ($) {
            function generatePassword(length) {
                var chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*',
                    result = '',
                    i;
                for (i = 0; i < length; i++) {
                    result += chars.charAt(Math.floor(Math.random() * chars.length));
                }
                return result;
            }

            function showProfileTabFromHash() {
                var hash = (window.location.hash || '').replace('#', '');
                var map = {password: 'profile-password-tab', telegram: 'profile-telegram-tab', tariff: 'profile-tariff-tab'};
                var id = map[hash];
                if (!id) {
                    return;
                }
                var el = document.getElementById(id);
                if (el && window.bootstrap) {
                    bootstrap.Tab.getOrCreateInstance(el).show();
                }
            }

            function syncAvatarPreview(src) {
                $('#cabinet-profile-avatar-preview, #cabinet-profile-avatar-main').attr('src', src);
            }

            $(function () {
                showProfileTabFromHash();
                window.addEventListener('hashchange', showProfileTabFromHash);

                document.querySelectorAll('#cabinet-profile-tabs [data-bs-toggle="tab"]').forEach(function (btn) {
                    btn.addEventListener('shown.bs.tab', function () {
                        var target = btn.getAttribute('data-bs-target');
                        if (target === '#profile-password') {
                            history.replaceState(null, '', '#password');
                        } else if (target === '#profile-telegram') {
                            history.replaceState(null, '', '#telegram');
                        } else if (target === '#profile-tariff') {
                            history.replaceState(null, '', '#tariff');
                        } else {
                            history.replaceState(null, '', window.location.pathname);
                        }
                    });
                });

                var $lang = $('.flags');
                if ($lang.length && $.fn.select2) {
                    $lang.select2({
                        minimumResultsForSearch: Infinity,
                        theme: 'bootstrap4',
                        width: '100%',
                        templateResult: function (state) {
                            if (!state.id) {
                                return state.text;
                            }
                            return $('<span><img src="/img/flags/' + state.id.toLowerCase() + '.png" class="img-flag" alt=""> ' + state.text + '</span>');
                        },
                        templateSelection: function (state) {
                            if (!state.id) {
                                return state.text;
                            }
                            return $('<span><img src="/img/flags/' + state.id.toLowerCase() + '.png" class="img-flag" alt=""> ' + state.text + '</span>');
                        }
                    });
                }

                $('#generate-password').on('click', function () {
                    var pwd = generatePassword(12);
                    $('#password, #password_confirmation').attr('type', 'text').val(pwd);
                });

                $('.cabinet-profile-toggle-pwd').on('click', function () {
                    var $input = $('#' + $(this).data('target'));
                    var isPwd = $input.attr('type') === 'password';
                    $input.attr('type', isPwd ? 'text' : 'password');
                    $(this).find('i').toggleClass('bi-eye bi-eye-slash');
                });

                $('#cabinet-profile-image-input').on('change', function () {
                    var file = this.files && this.files[0];
                    if (!file || !file.type.match(/^image\//)) {
                        return;
                    }
                    var reader = new FileReader();
                    reader.onload = function (e) {
                        syncAvatarPreview(e.target.result);
                        $('#remove_avatar').prop('checked', false);
                    };
                    reader.readAsDataURL(file);
                });

                @if($errors->has('password') || $errors->has('password_confirmation'))
                    var pwdTab = document.getElementById('profile-password-tab');
                    if (pwdTab && window.bootstrap) {
                        bootstrap.Tab.getOrCreateInstance(pwdTab).show();
                    }
                @endif
            });
        })(jQuery);
    </script>
@endsection
