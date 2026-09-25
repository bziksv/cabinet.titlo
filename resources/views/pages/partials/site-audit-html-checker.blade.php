@php
    $vnuReady = \App\Services\SiteAudit\SiteAuditHtmlChecker::vnuEnabled();
    $htmlCheckerDefault = \App\Services\SiteAudit\SiteAuditHtmlChecker::defaultChecker();
    if (! $vnuReady) {
        $htmlCheckerDefault = \App\Services\SiteAudit\SiteAuditHtmlChecker::LIBXML;
    }
    $html5Const = \App\Services\SiteAudit\SiteAuditHtmlChecker::HTML5;
@endphp
<details class="cabinet-sa-section" data-sa-pro data-sa-tour="html-checker" @if($htmlCheckerDefault === $html5Const) open @endif>
    <summary class="cabinet-sa-section__summary">
        <span class="cabinet-sa-section__icon" aria-hidden="true"><i class="bi bi-code-slash"></i></span>
        <span class="cabinet-sa-section__meta">
            <span class="cabinet-sa-section__title">Проверка HTML</span>
            <span class="cabinet-sa-section__desc">чекер разметки</span>
        </span>
        <i class="bi bi-chevron-down cabinet-sa-section__chev" aria-hidden="true"></i>
    </summary>
    <div class="cabinet-sa-section__body">
        <div class="mb-0 cabinet-sa-field">
            <div class="form-label fw-medium">
                Какой чекер использовать
                @include('pages.partials.site-audit-tip', ['tip' => "Быстрая (libxml) — встроенный PHP-парсер, ловит битые пары тегов и кривые атрибуты, но путает HTML5/SVG с ошибками (их мы глушим).\nHTML5 (Nu) — тот же движок, что validator.w3.org/nu: корректно понимает details/svg и строже к реальной разметке. Нужен сервис vnu на воркерах.\nВыбор действует на эту проверку."])
            </div>
            <div class="cabinet-sa-html-checker" role="radiogroup" aria-label="Чекер HTML">
                <label class="cabinet-sa-html-checker__opt">
                    <input type="radio" name="html_checker" id="sa-html-checker-libxml" value="libxml"
                           @if($htmlCheckerDefault === 'libxml') checked @endif>
                    <span class="cabinet-sa-html-checker__card">
                        <span class="cabinet-sa-html-checker__name">Быстрая (libxml)</span>
                        <span class="cabinet-sa-html-checker__note">Встроенный · быстрее · HTML4-таблица</span>
                    </span>
                </label>
                <label class="cabinet-sa-html-checker__opt @if(! $vnuReady) is-disabled @endif">
                    <input type="radio" name="html_checker" id="sa-html-checker-html5" value="html5"
                           @if($htmlCheckerDefault === 'html5') checked @endif
                           @if(! $vnuReady) disabled @endif>
                    <span class="cabinet-sa-html-checker__card">
                        <span class="cabinet-sa-html-checker__name">HTML5 (Nu)</span>
                        <span class="cabinet-sa-html-checker__note">
                            @if($vnuReady)
                                Nu Html Checker · как W3C
                            @else
                                Сервис vnu не настроен на сервере
                            @endif
                        </span>
                    </span>
                </label>
            </div>
            @if(! $vnuReady)
                <div class="form-text text-secondary">HTML5 станет доступен после установки Nu Html Checker (SITE_AUDIT_VNU_URL).</div>
            @endif
        </div>
    </div>
</details>
