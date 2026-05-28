{{-- Скрипты layout после jQuery / AdminLTE 4 --}}
<script>
(function () {
    function cabinetReleaseUiLock() {
        var hasOpenModal = document.querySelector('.modal.show');
        var hasMaximizedCard = document.querySelector('.card.maximized-card');
        if (!hasMaximizedCard) {
            document.documentElement.classList.remove('maximized-card');
        }
        if (!hasOpenModal) {
            document.body.classList.remove('modal-open');
            document.body.style.removeProperty('overflow');
            document.body.style.removeProperty('padding-right');
            document.body.style.removeProperty('pointer-events');
            document.querySelectorAll('.modal-backdrop').forEach(function (el) {
                el.remove();
            });
        }
        document.querySelectorAll('.card > .overlay, .overlay-wrapper > .overlay').forEach(function (el) {
            el.remove();
        });
        document.querySelectorAll('.card').forEach(function (card) {
            if (card.classList.contains('maximized-card')) {
                return;
            }
            var style = card.style;
            if (style.position === 'fixed' || style.zIndex === '1050') {
                style.removeProperty('position');
                style.removeProperty('width');
                style.removeProperty('height');
                style.removeProperty('top');
                style.removeProperty('left');
                style.removeProperty('z-index');
                style.removeProperty('transition');
            }
        });
    }

    window.cabinetReleaseUiLock = cabinetReleaseUiLock;
    cabinetReleaseUiLock();
    document.addEventListener('DOMContentLoaded', cabinetReleaseUiLock);
    window.addEventListener('pageshow', cabinetReleaseUiLock);
    window.addEventListener('pagehide', cabinetReleaseUiLock);
    document.addEventListener('hidden.bs.modal', cabinetReleaseUiLock);
})();

$(function () {
    /* Папки сайдбара: только AdminLTE 4 Treeview (data-lte-toggle="treeview" на .cabinet-sidebar-menu).
       Дублирующий jQuery-toggle давал двойное переключение — после сворачивания снова «открывалось». */

    var $limitsHint = $('#cabinet-header-limits-hint');
    var $used = $('#userModuleUsed');
    var $limit = $('#userModuleLimit');
    var $headerModuleLimit = $('#cabinet-header-module-limit');
    if ($headerModuleLimit.length) {
        var limitCode = $headerModuleLimit.data('limit-code');
        if (limitCode) {
            $('#header-nav-bar .cabinet-header-limits-menu tr.' + limitCode)
                .addClass('cabinet-header-limits-menu__row--current');
        }
        return;
    }
    $('#header-nav-bar .cabinet-header-limits-menu table tbody tr').each(function () {
        var bg = $(this).css('background-color');
        if (bg === 'rgb(253, 245, 230)' || bg === 'rgb(255, 243, 205)') {
            var limitCell = $.trim($(this).children('td').eq(1).text());
            var leftCell = $.trim($(this).children('td').eq(2).text());
            if (limitCell === 'Без ограничений' || limitCell === "{{ __('No restrictions') }}") {
                $limit.text("{{ __('No restrictions') }}");
                $used.text('');
            } else {
                $limit.text("{{ __('from') }} " + limitCell);
                $used.text("{{ __('Left') }} " + leftCell);
            }
            $limitsHint.removeClass('is-empty');
            return false;
        }
    });
});
</script>
