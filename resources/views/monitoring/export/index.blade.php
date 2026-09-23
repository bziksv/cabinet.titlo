<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Document</title>
</head>

<body>
    <table style="width: 100%">
        <thead>
            <tr>
                <th>№</th>
                @foreach($data['columns'] as $col)
                    <th> {!! trim(strip_tags($col)) !!} </th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @php $wordNum = 0; @endphp
            @foreach($data['data'] as $ek => $query)
                @php
                    // Итоговые строки финансового отчёта — без №, нумеруем только запросы.
                    $isWordRow = is_object($query) && (
                        (method_exists($query, 'has') && $query->has('query'))
                        || (isset($query['query']))
                    );
                @endphp
                <tr>
                    <td>{{ $isWordRow ? (++$wordNum) : '' }}</td>
                    @foreach($query as $fk => $field)
                        @if(is_array($field))
                            <td style="background-color: {{ $field['color'] }}">
                                @if(count($field) > 2) {{ $field[0] }} [{{ $field[1] }}] @else {{ $field[0] }} @endif
                            </td>
                        @elseif($fk === 'url_links')
                            {{-- Excel из HTML глотает \\n как пробел; <br> даёт реальный перенос в ячейке --}}
                            <td style="text-align:left;vertical-align:top;white-space:normal;">{!! nl2br(e((string) $field), false) !!}</td>
                        @else
                            <td>{{ $field }}</td>
                        @endif
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
