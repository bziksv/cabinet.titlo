@php
    $part = $part ?? 'both';
@endphp
@if($part === 'css' || $part === 'both')
    <link rel="stylesheet" href="{{ asset('plugins/codemirror/codemirror.css') }}">
    <link rel="stylesheet" href="{{ asset('plugins/codemirror/theme/neo.css') }}">
    <link rel="stylesheet" href="{{ asset('plugins/codemirror/addon/fold/foldgutter.css') }}">
@endif
@if($part === 'js' || $part === 'both')
    <script src="{{ asset('plugins/codemirror/codemirror.js') }}"></script>
    <script src="{{ asset('plugins/codemirror/mode/xml/xml.js') }}"></script>
    <script src="{{ asset('plugins/codemirror/mode/css/css.js') }}"></script>
    <script src="{{ asset('plugins/codemirror/mode/javascript/javascript.js') }}"></script>
    <script src="{{ asset('plugins/codemirror/mode/htmlmixed/htmlmixed.js') }}"></script>
    <script src="{{ asset('plugins/codemirror/addon/edit/matchbrackets.js') }}"></script>
    <script src="{{ asset('plugins/codemirror/addon/edit/matchtags.js') }}"></script>
    <script src="{{ asset('plugins/codemirror/addon/edit/closetag.js') }}"></script>
    <script src="{{ asset('plugins/codemirror/addon/edit/closebrackets.js') }}"></script>
    <script src="{{ asset('plugins/codemirror/addon/fold/foldcode.js') }}"></script>
    <script src="{{ asset('plugins/codemirror/addon/fold/foldgutter.js') }}"></script>
    <script src="{{ asset('plugins/codemirror/addon/fold/xml-fold.js') }}"></script>
    <script src="{{ asset('plugins/codemirror/addon/fold/brace-fold.js') }}"></script>
    <script src="{{ asset('plugins/codemirror/addon/fold/indent-fold.js') }}"></script>
    <script src="{{ asset('plugins/codemirror/addon/selection/active-line.js') }}"></script>
    <script src="{{ asset('plugins/codemirror/addon/display/autorefresh.js') }}"></script>
@endif
