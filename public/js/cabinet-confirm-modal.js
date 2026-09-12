/**
 * Bootstrap confirm modal (replaces window.confirm).
 *
 * Markup: #cabinet-confirm-modal (see partials/cabinet-confirm-modal.blade.php)
 *
 * API:
 *   CabinetConfirm.show({ title, text, okLabel, cancelLabel, danger, onConfirm, onCancel })
 *
 * Forms with data-cabinet-confirm="…" are intercepted automatically.
 * Optional: data-cabinet-confirm-title, data-cabinet-confirm-ok,
 *           data-cabinet-confirm-cancel, data-cabinet-confirm-danger="1"
 */
(function (global) {
    'use strict';

    var modalEl = null;
    var titleEl = null;
    var textEl = null;
    var okBtn = null;
    var cancelBtn = null;
    var pending = null;
    var bound = false;

    function ensureEls() {
        if (modalEl) {
            return !!modalEl;
        }
        modalEl = document.getElementById('cabinet-confirm-modal');
        if (!modalEl) {
            return false;
        }
        titleEl = document.getElementById('cabinet-confirm-title');
        textEl = modalEl.querySelector('[data-cabinet-confirm-text]');
        okBtn = modalEl.querySelector('[data-cabinet-confirm-ok]');
        cancelBtn = modalEl.querySelector('[data-cabinet-confirm-cancel]');
        return true;
    }

    function showBs() {
        if (!modalEl) {
            return;
        }
        if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
            bootstrap.Modal.getOrCreateInstance(modalEl).show();
            return;
        }
        if (typeof global.$ !== 'undefined' && global.$.fn && global.$.fn.modal) {
            global.$(modalEl).modal('show');
        }
    }

    function hideBs() {
        if (!modalEl) {
            return;
        }
        if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
            var inst = bootstrap.Modal.getInstance(modalEl);
            if (inst) {
                inst.hide();
            }
            return;
        }
        if (typeof global.$ !== 'undefined' && global.$.fn && global.$.fn.modal) {
            global.$(modalEl).modal('hide');
        }
    }

    function bindOnce() {
        if (bound || !ensureEls()) {
            return;
        }
        bound = true;

        if (okBtn) {
            okBtn.addEventListener('click', function () {
                var action = pending;
                pending = null;
                hideBs();
                if (action && typeof action.onConfirm === 'function') {
                    action.onConfirm();
                }
            });
        }

        modalEl.addEventListener('hidden.bs.modal', function () {
            if (!pending) {
                return;
            }
            var action = pending;
            pending = null;
            if (typeof action.onCancel === 'function') {
                action.onCancel();
            }
        });
    }

    function show(opts) {
        opts = opts || {};
        bindOnce();
        if (!ensureEls()) {
            var ok = global.confirm(opts.text || opts.title || 'Подтвердить?');
            if (ok && typeof opts.onConfirm === 'function') {
                opts.onConfirm();
            } else if (!ok && typeof opts.onCancel === 'function') {
                opts.onCancel();
            }
            return;
        }

        pending = opts;
        if (titleEl) {
            titleEl.textContent = opts.title || 'Подтвердите действие';
        }
        if (textEl) {
            textEl.textContent = opts.text || '';
        }
        if (okBtn) {
            okBtn.textContent = opts.okLabel || 'Подтвердить';
            okBtn.className = 'btn ' + (opts.danger ? 'btn-danger' : 'btn-primary');
        }
        if (cancelBtn && opts.cancelLabel) {
            cancelBtn.textContent = opts.cancelLabel;
        }
        showBs();
    }

    function submitForm(form) {
        form.setAttribute('data-cabinet-confirm-passed', '1');
        try {
            if (typeof form.requestSubmit === 'function') {
                form.requestSubmit();
            } else {
                HTMLFormElement.prototype.submit.call(form);
            }
        } finally {
            form.removeAttribute('data-cabinet-confirm-passed');
        }
    }

    function onSubmitCapture(e) {
        var form = e.target;
        if (!form || form.tagName !== 'FORM') {
            return;
        }
        if (form.getAttribute('data-cabinet-confirm-passed') === '1') {
            return;
        }

        var submitter = e.submitter || null;
        var msg = form.getAttribute('data-cabinet-confirm')
            || (submitter && submitter.getAttribute('data-cabinet-confirm'))
            || '';
        if (!msg) {
            return;
        }

        e.preventDefault();
        e.stopImmediatePropagation();

        var title = form.getAttribute('data-cabinet-confirm-title')
            || (submitter && submitter.getAttribute('data-cabinet-confirm-title'))
            || 'Подтвердите действие';
        var okLabel = form.getAttribute('data-cabinet-confirm-ok')
            || (submitter && submitter.getAttribute('data-cabinet-confirm-ok'))
            || 'Подтвердить';
        var cancelLabel = form.getAttribute('data-cabinet-confirm-cancel')
            || (submitter && submitter.getAttribute('data-cabinet-confirm-cancel'))
            || 'Отмена';
        var danger = form.getAttribute('data-cabinet-confirm-danger') === '1'
            || (submitter && submitter.getAttribute('data-cabinet-confirm-danger') === '1');

        show({
            title: title,
            text: msg,
            okLabel: okLabel,
            cancelLabel: cancelLabel,
            danger: danger,
            onConfirm: function () {
                // Пока уходит POST — не даём жать повторно.
                // Иконки / кнопки в ряду действий истории нельзя затирать текстом
                // («Запуск…» раздувает квадрат и наезжает на «Сводку»).
                if (submitter) {
                    submitter.disabled = true;
                    submitter.setAttribute('aria-busy', 'true');
                    var inRowActions = !!submitter.closest('.cabinet-sa-row-actions, .cabinet-sa-actions');
                    var isIconBtn = inRowActions
                        || submitter.classList.contains('cabinet-sa-icon-btn')
                        || !!submitter.querySelector('.bi, .fa, [class*="icon"]');
                    if (isIconBtn) {
                        submitter.classList.add('is-busy');
                        // Иконку не трогаем — только disabled + лёгкий CSS busy.
                    } else {
                        if (!submitter.getAttribute('data-original-label')) {
                            submitter.setAttribute('data-original-label', submitter.textContent || '');
                        }
                        var busy = '…';
                        if (okLabel === 'Удалить') {
                            busy = 'Удаление…';
                        } else if (okLabel === 'Остановить') {
                            busy = 'Остановка…';
                        } else if (okLabel === 'Возобновить') {
                            busy = 'Возобновление…';
                        } else if (okLabel === 'Повторить') {
                            busy = 'Повтор…';
                        } else if (okLabel === 'Пометить' || okLabel === 'В игнор') {
                            busy = '…';
                        } else if (/запуст/i.test(okLabel || '')) {
                            busy = 'Запуск…';
                        }
                        submitter.textContent = busy;
                    }
                }
                submitForm(form);
            }
        });
    }

    document.addEventListener('submit', onSubmitCapture, true);

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bindOnce);
    } else {
        bindOnce();
    }

    global.CabinetConfirm = {
        show: show,
        submitForm: submitForm
    };
})(window);
