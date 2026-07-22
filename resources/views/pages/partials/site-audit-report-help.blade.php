@php
    $help = \App\Services\SiteAudit\SiteAuditFindingHelp::forCode($code, $meta ?? []);
@endphp
<div class="cabinet-sa-help mb-3">
    <div class="cabinet-sa-help__row">
        <span class="cabinet-sa-help__label">Что это</span>
        <span class="cabinet-sa-help__text">{{ $help['what'] }}</span>
    </div>
    <div class="cabinet-sa-help__row">
        <span class="cabinet-sa-help__label">Почему плохо</span>
        <span class="cabinet-sa-help__text">{{ $help['why'] }}</span>
    </div>
    <div class="cabinet-sa-help__row">
        <span class="cabinet-sa-help__label">Как исправить</span>
        <span class="cabinet-sa-help__text">{{ $help['fix'] }}</span>
    </div>
</div>
