@extends('layouts.auth')

@section('title', __('Register page'))

@section('content')
    <div class="register-box">
        <div class="card card-outline card-primary">
            <div class="card-header text-center">
                <h1><b id="register-header">{{ __('Register') }}</b></h1>
            </div>

            <div class="card-body">
                <form action="{{ route('register') }}" method="POST">
                    @csrf
                    <input type="hidden" name="utm_metrics" id="utm-metrics">
                    <p class="login-box-msg">{{ __('Select your language') }}</p>

                    <div class="input-group mb-3">
                        <select id="select-language" name="lang"
                                class="form-select flags @error('lang') is-invalid @enderror">
                            @foreach($lang as $l)
                                <option value="{{ $l }}">
                                    @if($l == 'ru')
                                        Русский
                                    @else
                                        English
                                    @endif
                                </option>
                            @endforeach
                        </select>
                        @error('lang')
                        <span class="invalid-feedback" role="alert">
                            <strong>{{ $message }}</strong>
                        </span>
                        @enderror
                    </div>

                    <p class="login-box-msg">{{ __('Register a new membership') }}</p>
                    @csrf

                    <div class="input-group mb-3">
                        <input id="name" type="text" class="form-control @error('name') is-invalid @enderror"
                               name="name" value="{{ old('name') }}" placeholder="{{ __('Name') }}" autocomplete="name"
                               autofocus required>
                        <span class="input-group-text"><i class="fas fa-user"></i></span>
                        @error('name')
                        <span class="invalid-feedback" role="alert">
                            <strong>{{ $message }}</strong>
                        </span>
                        @enderror
                    </div>

                    <div class="input-group mb-3">
                        <input id="last_name" type="text" class="form-control @error('last_name') is-invalid @enderror"
                               name="last_name" value="{{ old('last_name') }}" placeholder="{{ __('Last name') }}"
                               autocomplete="last_name" autofocus required>
                        <span class="input-group-text"><i class="fas fa-user"></i></span>
                        @error('last_name')
                        <span class="invalid-feedback" role="alert">
                            <strong>{{ $message }}</strong>
                        </span>
                        @enderror
                    </div>

                    <div class="input-group mb-3">
                        <input id="email" type="email" class="form-control @error('email') is-invalid @enderror"
                               name="email" value="{{ old('email') }}" placeholder="{{ __('E-Mail') }}"
                               autocomplete="email" required>
                        <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                        @error('email')
                        <span class="invalid-feedback" role="alert">
                            <strong>{{ $message }}</strong>
                        </span>
                        @enderror
                    </div>
                    <div id="signup-email-policy-slot" class="mb-3">
                        @if($errors->has('email_policy_html'))
                            {!! $errors->first('email_policy_html') !!}
                        @endif
                    </div>

                    <div class="input-group mb-3">
                        <input id="password" type="password"
                               class="form-control @error('password') is-invalid @enderror" name="password"
                               placeholder="{{ __('Password') }}" autocomplete="new-password" required pattern=".{8,}"
                               title="the password must be at least 8 characters long">
                        <span class="input-group-text"><i class="fas fa-lock"></i></span>
                        @error('password')
                        <span class="invalid-feedback" role="alert">
                            <strong>{{ $message }}</strong>
                        </span>
                        @enderror
                    </div>
                    <div class="input-group mb-3">
                        <input id="password-confirm" type="password" class="form-control" name="password_confirmation"
                               placeholder="{{ __('Confirm Password') }}" autocomplete="new-password" required
                               pattern=".{8,}" title="the password must be at least 8 characters long">
                        <span class="input-group-text"><i class="fas fa-lock"></i></span>
                    </div>
                    <ul id="validate-messages">
                    </ul>
                    <div class="row">
                        <div class="col-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="agreeTerms" name="terms" value="agree" required>
                                <label class="form-check-label" for="agreeTerms">
                                    <span>{{ __('I give my consent to the processing') }}</span>
                                    <a href="/personal-data/ru" target="_blank">{{ __('personal data') }}</a>

                                    <span>{{ __('and agree to the terms') }}</span>
                                    <a href="/privacy-policy/ru" target="_blank">{{ __('privacy policy') }}</a>
                                </label>
                            </div>
                        </div>

                        <div class="col-12 mt-2">
                            <button type="button" class="btn btn-primary btn-block" id="fakeButton" disabled>
                                <i class="fas fa-user-plus"></i> {{ __('Register') }}
                            </button>
                            <button type="button" class="btn btn-primary btn-block" id="sendFormButton"
                                    onclick="ym(54591493, 'reachGoal', 'novaja_registracija_1231'); ym(89500732, 'reachGoal', 'novaja_registracija_1231')"
                                    style="display: none">
                                <i class="fas fa-user-plus"></i> {{ __('Register') }}
                            </button>
                        </div>
                    </div>
                </form>

                <div class="social-auth-links text-center mt-2">
                    @if (Route::has('password.request'))
                        <a href="{{ route('password.request') }}" target="_blank" class="btn btn-block btn-primary">
                            <i class="fas fa-key mr-2"></i> {{ __('Forgot Your Password?') }}
                        </a>
                    @endif
                    @if (Route::has('login'))
                        <a href="{{ route('login') }}" class="btn btn-block btn-danger mt-2">
                            <i class="fas fa-user mr-2"></i> {{ __('Login membership') }}
                        </a>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection

@section('js')
    <script>
        if (navigator.language === 'en') {
            $('#select-language').val('en')
            $('body > div > div > div.card-body > form > div.row > div:nth-child(1) > div > label > a:nth-child(2)').attr('href', '/personal-data/en')
            $('body > div > div > div.card-body > form > div.row > div:nth-child(1) > div > label > a:nth-child(4)').attr('href', '/privacy-policy/en')
        } else {
            $('#select-language').val('ru')
            $('body > div > div > div.card-body > form > div.row > div:nth-child(1) > div > label > a:nth-child(4)').attr('href', '/privacy-policy/ru')
            $('body > div > div > div.card-body > form > div.row > div:nth-child(1) > div > label > a:nth-child(2)').attr('href', '/personal-data/ru')
        }

        @if(config('app.env') !== 'local')
        $(".flags").select2({
            minimumResultsForSearch: Infinity,
            templateResult: function (state) {
                if (!state.id) {
                    return state.text;
                }
                var baseUrl = "/img/flags";
                var $state = $(
                    '<span><img src="' + baseUrl + '/' + state.element.value.toLowerCase() + '.png" class="img-flag" /> ' + state.text + '</span>'
                );
                return $state;
            }
        });
        @endif

        $(document).ready(function () {
            $('#select-language').on('change', function () {
                if ($(this).val() === 'en') {
                    $('#register-header').html('Register')
                    $('body > div > div > div.card-body > form > p:nth-child(2)').html('Select yor language')
                    $('body > div > div > div.card-body > form > p:nth-child(4)').html('Register a new membership')
                    $('#name').attr('placeholder', 'Name')
                    $('#last_name').attr('placeholder', 'Last name')
                    $('#password').attr('placeholder', 'Password')
                    $('#password-confirm').attr('placeholder', 'Confirm password')
                    $('body > div > div > div.card-body > form > div.row > div:nth-child(1) > div > label > span:nth-child(1)').html('I give my consent to the processing')
                    $('body > div > div > div.card-body > form > div.row > div:nth-child(1) > div > label > a:nth-child(2)').html('personal data')
                    $('body > div > div > div.card-body > form > div.row > div:nth-child(1) > div > label > a:nth-child(2)').attr('href', '/personal-data/en')
                    $('body > div > div > div.card-body > form > div.row > div:nth-child(1) > div > label > span:nth-child(3)').html('and agree to the terms')
                    $('body > div > div > div.card-body > form > div.row > div:nth-child(1) > div > label > a:nth-child(4)').html('privacy policy')
                    $('body > div > div > div.card-body > form > div.row > div:nth-child(1) > div > label > a:nth-child(4)').attr('href', '/privacy-policy/en')
                    $('body > div > div > div.card-body > form > div.row > div.col-12.mt-2 > button').html('<i class="fas fa-user-plus"></i> Registration')
                    $('body > div > div > div.card-body > div > a.btn.btn-block.btn-primary').html('<i class="fas fa-key mr-2"></i> Forgot your password?')
                    $('body > div > div > div.card-body > div > a.btn.btn-block.btn-danger').html('<i class="fas fa-user mr-2"></i> Login membership')

                } else {
                    $('#register-header').html('Регистрация')
                    $('body > div > div > div.card-body > form > p:nth-child(2)').html('Выберите ваш язык')
                    $('body > div > div > div.card-body > form > p:nth-child(4)').html('Зарегистрировать нового пользователя')
                    $('#name').attr('placeholder', 'Имя')
                    $('#last_name').attr('placeholder', 'Фамилия')
                    $('#password').attr('placeholder', 'Пароль')
                    $('#password-confirm').attr('placeholder', 'Подтвердить пароль')
                    $('body > div > div > div.card-body > form > div.row > div:nth-child(1) > div > label > span:nth-child(1)').html('Я даю свое согласие на обработку')
                    $('body > div > div > div.card-body > form > div.row > div:nth-child(1) > div > label > a:nth-child(2)').html('персональных данных')
                    $('body > div > div > div.card-body > form > div.row > div:nth-child(1) > div > label > a:nth-child(2)').attr('href', '/personal-data/ru')
                    $('body > div > div > div.card-body > form > div.row > div:nth-child(1) > div > label > span:nth-child(3)').html('и соглашаюсь с условиями')
                    $('body > div > div > div.card-body > form > div.row > div:nth-child(1) > div > label > a:nth-child(4)').html('политики конфиденциальности')
                    $('body > div > div > div.card-body > form > div.row > div:nth-child(1) > div > label > a:nth-child(4)').attr('href', '/privacy-policy/ru')
                    $('body > div > div > div.card-body > form > div.row > div.col-12.mt-2 > button').html('<i class="fas fa-user-plus"></i> Регистрация')
                    $('body > div > div > div.card-body > div > a.btn.btn-block.btn-primary').html('<i class="fas fa-key mr-2"></i> Забыли пароль?')
                    $('body > div > div > div.card-body > div > a.btn.btn-block.btn-danger').html('<i class="fas fa-user mr-2"></i> Уже зарегистрирован')
                }
            })

            if (localStorage.getItem('_user_metrics_redbox') != '' && localStorage.getItem('_user_metrics_redbox') != undefined) {
                $('#utm-metrics').val(localStorage.getItem('_user_metrics_redbox'))
            } else if (new URL(window.location.href)['search'] != '' && new URL(window.location.href)['search'] != undefined) {
                $('#utm-metrics').val(new URL(window.location.href)['search'])
            }
        })
    </script>

    <script>
        let messages = [];

        $('body > div.register-box > div > div.card-body').on('keyup', function () {
            checkValid()
        });

        $('#agreeTerms').on('click', function () {
            checkValid()
        })

        function isDataValid() {
            let boolean = true;
            boolean = $('#agreeTerms').is(':checked');

            if (boolean) {
                $.each(getData(), function (key, value) {
                    if (value === '') {
                        boolean = false;
                    }
                });
            }

            return boolean;
        }

        function getData() {
            return {
                _token: $('meta[name="csrf-token"]').attr('content'),
                lang: $('#select-language').val(),
                name: $('#name').val(),
                last_name: $('#last_name').val(),
                email: $('#email').val(),
                password: $('#password').val(),
                password_confirmation: $('#password-confirm').val(),
            };
        }

        function checkValid() {
            if (isDataValid()) {
                $.ajax({
                    method: 'post',
                    dataType: 'json',
                    data: getData(),
                    url: "{{ route('validate.registration.form') }}",
                    error: function (response) {
                        console.log(response)
                        if (messages !== JSON.stringify(response.responseJSON.errors)) {
                            messages = JSON.stringify(response.responseJSON.errors);
                            $(".render-li").remove()
                            $.each(response.responseJSON.errors, function (key, value) {
                                if (key === 'email_policy_html') {
                                    $('#signup-email-policy-slot').html(value.join(''));
                                    return;
                                }
                                $('#validate-messages').append('<li class="render-li alert p-0">' + value.join() + '</li>')
                            })
                        }

                        $('#fakeButton').show();
                        $('#sendFormButton').hide();
                        $('#sendFormButton').attr('type', 'button')
                    },
                    success: function () {
                        $('.render-li').remove()
                        $('#signup-email-policy-slot').empty()
                        $('#fakeButton').hide();
                        $('#sendFormButton').show();
                        $('#sendFormButton').attr('type', 'submit')
                    }
                })
            } else {
                $('#fakeButton').show();
                $('#sendFormButton').hide();
                $('#sendFormButton').attr('type', 'button')
            }
        }
    </script>

    @if(!empty($emailPolicy))
        <script>
            (function () {
                var policy = @json($emailPolicy);

                function emailDomain(email) {
                    email = $.trim(String(email || '')).toLowerCase();
                    var at = email.lastIndexOf('@');
                    return at >= 0 ? email.slice(at + 1) : '';
                }

                function isAllowedSignupEmail(email) {
                    var domain = emailDomain(email);
                    if (!domain) {
                        return false;
                    }
                    var tldRe = new RegExp('\\.(' + policy.allowedTlds.join('|') + ')$', 'u');
                    if (tldRe.test(domain)) {
                        return true;
                    }
                    for (var i = 0; i < policy.allowedProviders.length; i++) {
                        var provider = policy.allowedProviders[i];
                        if (domain === provider || domain.slice(-('.' + provider).length) === '.' + provider) {
                            return true;
                        }
                    }
                    return false;
                }

                function showPolicyNotice() {
                    $('#signup-email-policy-slot').html(policy.noticeHtml);
                    $('#fakeButton').show();
                    $('#sendFormButton').hide().attr('type', 'button');
                }

                $('#email').on('blur keyup', function () {
                    var email = $(this).val();
                    if (email && !isAllowedSignupEmail(email)) {
                        showPolicyNotice();
                    } else {
                        $('#signup-email-policy-slot').empty();
                    }
                });
            })();
        </script>
    @endif
@endsection
