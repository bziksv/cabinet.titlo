@component('component.card', [
    'title' => __('Add tracking the registration period'),
    'titleHtml' => e(__('Add tracking the registration period')) . view('partials.cabinet-module-version-badge', ['configKey' => 'cabinet-domain-information'])->render(),
])
    @slot('css')
        <link rel="stylesheet" href="{{ asset('css/cabinet-domain-information.css') }}?v={{ @filemtime(public_path('css/cabinet-domain-information.css')) ?: time() }}">
    @endslot

    <div class="cabinet-di-page cabinet-di-create">
        @include('domain-information.partials.free-tariff-email-notice')

        <p class="text-secondary small mb-2">{{ __('Domain information create lead short') }}</p>
        @include('domain-information.partials.create-steps-nav')

        {!! Form::open(['action' => 'DomainInformationController@store', 'method' => 'POST', 'class' => 'single', 'id' => 'cabinet-di-create-form']) !!}

        <div class="cabinet-di-form-sections d-flex flex-column gap-3">
            <section class="cabinet-di-form-section" id="cabinet-di-step-1">
                <h3 class="cabinet-di-form-section__title">
                    <span class="cabinet-di-step-badge" aria-hidden="true">1</span>
                    <span class="cabinet-di-form-section__title-text">
                        <span class="cabinet-di-form-section__step">{{ __('Domain information step label', ['n' => 1]) }}</span>
                        {{ __('Domain information form section domain') }}
                    </span>
                </h3>
                <div class="cabinet-di-form-section__body">
                    <p class="small text-secondary mb-3">{{ __('Domain information create step1 body') }}</p>
                    <div class="mb-3">
                        {!! Form::label('domain', __('Domain'), ['class' => 'form-label']) !!}
                        {!! Form::text('domain', old('domain'), [
                            'class' => 'form-control',
                            'required' => true,
                            'placeholder' => 'example.com',
                            'autocomplete' => 'off',
                        ]) !!}
                        <div class="form-text">{{ __('Domain information create domain hint') }}</div>
                    </div>
                    <p class="mb-0">
                        <a href="#" class="list fw-semibold">{{ __('Domain information add bulk link') }}</a>
                        <span class="text-secondary small ms-1">— {{ __('Domain information create bulk teaser') }}</span>
                    </p>
                </div>
            </section>

            <section class="cabinet-di-form-section" id="cabinet-di-step-2">
                <h3 class="cabinet-di-form-section__title">
                    <span class="cabinet-di-step-badge" aria-hidden="true">2</span>
                    <span class="cabinet-di-form-section__title-text">
                        <span class="cabinet-di-form-section__step">{{ __('Domain information step label', ['n' => 2]) }}</span>
                        {{ __('Domain information form section dns') }}
                    </span>
                </h3>
                <div class="cabinet-di-form-section__body">
                    <p class="small text-secondary mb-3">{{ __('Domain information create step2 body') }}</p>
                    <div class="mb-0">
                        {!! Form::label('check_dns', __('Check DNS'), ['class' => 'form-label']) !!}
                        {!! Form::select('check_dns', ['1' => __('yes'), '0' => __('no')], old('check_dns', '0'), ['class' => 'form-select']) !!}
                        <div class="form-text">{{ __('Domain information notify dns hint') }}</div>
                    </div>
                    @if($domainInformationEmailAvailable ?? true)
                        <div class="alert alert-light border small py-2 px-3 mt-3 mb-0" role="note">
                            {{ __('Domain information create email dns hint') }}
                        </div>
                    @endif
                </div>
            </section>

            <section class="cabinet-di-form-section" id="cabinet-di-step-3">
                <h3 class="cabinet-di-form-section__title">
                    <span class="cabinet-di-step-badge" aria-hidden="true">3</span>
                    <span class="cabinet-di-form-section__title-text">
                        <span class="cabinet-di-form-section__step">{{ __('Domain information step label', ['n' => 3]) }}</span>
                        {{ __('Domain information form section finish') }}
                    </span>
                </h3>
                <div class="cabinet-di-form-section__body">
                    <p class="small text-secondary mb-3">{{ __('Domain information create step3 body') }}</p>
                    <div class="mb-3">
                        {!! Form::label('check_registration_date', __('Check registration Date'), ['class' => 'form-label']) !!}
                        {!! Form::select('check_registration_date', ['1' => __('yes'), '0' => __('no')], old('check_registration_date', '0'), ['class' => 'form-select']) !!}
                        <div class="form-text">{{ __('Domain information notify expiry hint') }}</div>
                    </div>
                    <ul class="small text-secondary mb-0 ps-3">
                        <li>{{ __('Domain information create step3 bullet cron') }}</li>
                        <li>{{ __('Domain information create step3 bullet columns') }}</li>
                        <li>{{ __('Domain information dns compare hint') }}</li>
                    </ul>
                </div>
            </section>
        </div>

        <div class="cabinet-di-create-submit d-flex flex-wrap align-items-center gap-2 pt-4 mt-1 border-top">
            <button class="btn btn-primary" type="submit">
                <i class="bi bi-plus-lg me-1" aria-hidden="true"></i>{{ __('Add to Tracking') }}
            </button>
            <a href="{{ route('domain.information') }}" class="btn btn-outline-secondary">{{ __('To my projects') }}</a>
        </div>

        {!! Form::close() !!}

        <div class="much mt-4" style="display: none">
            <section class="cabinet-di-form-section" id="cabinet-di-step-bulk">
                <h3 class="cabinet-di-form-section__title">
                    <span class="cabinet-di-step-badge" aria-hidden="true">1</span>
                    <span class="cabinet-di-form-section__title-text">
                        {{ __('Domain information form section bulk') }}
                    </span>
                </h3>
                <div class="cabinet-di-form-section__body">
                    <p class="small text-secondary">{{ __('Domain information create bulk body') }}</p>
                    {!! Form::open(['action' => 'DomainInformationController@store', 'method' => 'POST']) !!}
                    <div class="mb-3">
                        {!! Form::label('domains', __('Domains'), ['class' => 'form-label']) !!}
                        <div class="font-monospace small bg-body-tertiary border rounded p-2 mb-2">
                            domain.ru:1:0<br>
                            <span class="text-secondary">domain · Telegram DNS · Telegram срок</span>
                        </div>
                        {!! Form::textarea('domains', old('domains'), [
                            'class' => 'form-control font-monospace',
                            'rows' => 8,
                            'required' => true,
                            'placeholder' => "domain.com:1:1\ndomain2.com:0:1",
                        ]) !!}
                        <div class="form-text">{{ __('Domain information create bulk format hint') }}</div>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <button class="btn btn-primary" type="submit">{{ __('Add to Tracking') }}</button>
                        <a href="#" class="btn btn-outline-secondary multi">{{ __('Add one domain') }}</a>
                    </div>
                    {!! Form::close() !!}
                </div>
            </section>
        </div>

        @if(!auth()->user()->isTelegramConnected())
            <p class="small text-secondary mt-3 mb-0">
                {{ __('Want to') }}
                <a href="{{ route('profile.index') }}" target="_blank" rel="noopener noreferrer">
                    {{ __('receive notifications from our telegram bot') }}
                </a>?
            </p>
        @endif
    </div>

    @slot('js')
        <script>
            $('.list').on('click', function (e) {
                e.preventDefault();
                $('.much').show();
                $('.single').hide();
                $('.cabinet-di-form-sections').hide();
                $('.cabinet-di-create-submit').hide();
                $('.cabinet-di-steps-nav').addClass('d-none');
            });
            $('.multi').on('click', function (e) {
                e.preventDefault();
                $('.much').hide();
                $('.single').show();
                $('.cabinet-di-form-sections').show();
                $('.cabinet-di-create-submit').show();
                $('.cabinet-di-steps-nav').removeClass('d-none');
            });
        </script>
    @endslot
@endcomponent
