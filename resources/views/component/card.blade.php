@extends('layouts.app')

@section('title', $title)

@section('css')
    {{ $css ?? null }}
@stop

@section('content')

    @if(\App\User::isUserAdmin() && count(explode('/', $code)) == 1)
        <a href="{{ route('description.edit', [$code, 'top']) }}" class="btn btn-secondary mb-4">{{ __('Add description') }}</a>
    @endif

    @if(isset($description['top']))
        @include('description.main', ['description' => $description['top']])
    @endif

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">{!! $titleHtml ?? e($title) !!}</h3>
            <div class="card-tools">
                {{ $tools ?? null }}
            </div>
        </div>
        <!-- /.card-header -->

        <div class="card-body">
            {{ $slot }}
        </div>
        <!-- /.card-body -->

        @if(isset($footer))
            <div class="card-footer">
                {{ $footer ?? null }}
            </div>
            <!-- /.card-footer -->
        @endif
    </div>
    <!-- /.card -->
    @if(isset($description['bottom']))
        @include('description.main', ['description' => $description['bottom']])
    @endif
@stop

@section('js')
    {{ $js ?? null }}

    <script>
        let name = window.location.pathname;
        var $moduleCard = $('.app-main .card').first();
        $moduleCard.on('collapsed.lte.card expanded.lte.card collapsed.lte.card-widget expanded.lte.card-widget', function (e) {
            var collapsed = e.type === 'collapsed.lte.card' || e.type === 'collapsed.lte.card-widget';
            cookies.set(name, collapsed ? 'collapse' : 'expand');
        });

        try {
            if (cookies.get(name) === 'collapse') {
                $moduleCard.addClass('collapsed-card');
            }
        } catch (e) {
        }

    </script>
@stop
