{{-- Подсказка «?» рядом с пунктом формы Site Audit --}}
@php
    $tipText = $tip ?? '';
    $tipSide = $tipSide ?? 'right';
@endphp
@if($tipText !== '')
    <span class="cabinet-sa-tip ui_tooltip_w" tabindex="0" role="button" aria-label="Подсказка">
        <i class="fa fa-question-circle" aria-hidden="true"></i>
        <span class="ui_tooltip __{{ $tipSide }}">
            <span class="ui_tooltip_content">{!! nl2br(e($tipText)) !!}</span>
        </span>
    </span>
@endif
