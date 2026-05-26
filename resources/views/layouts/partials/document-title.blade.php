@php
    $cabinetDocumentTitlePage = trim($__env->yieldContent('title'));
@endphp
<title>{{ cabinet_page_title($cabinetDocumentTitlePage !== '' ? $cabinetDocumentTitlePage : null) }}</title>
