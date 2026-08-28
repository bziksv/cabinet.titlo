{{-- Подсказка «?» — тёмный пузырь ui_tooltip, не native title --}}
@php
    $tipText = $tip ?? '';
    $tipSide = $tipSide ?? 'right';
@endphp
@if($tipText !== '')
    <span class="cabinet-ta-tip ui_tooltip_w"
          tabindex="0"
          role="button"
          aria-label="Подсказка"
          onclick="event.stopPropagation();"
          onmousedown="event.stopPropagation();">
        <i class="fa fa-question-circle" aria-hidden="true"></i>
        <span class="ui_tooltip __{{ $tipSide }}">
            <span class="ui_tooltip_content">{!! nl2br(e($tipText)) !!}</span>
        </span>
    </span>
@endif
