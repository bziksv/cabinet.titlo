/**
 * Кнопка «копировать URL» в таблицах Site Audit (.cabinet-sa-url-copy[data-sa-copy-url]).
 */
(function () {
    'use strict';

    if (window.__saCopyUrlInit) {
        return;
    }
    window.__saCopyUrlInit = true;

    function fallbackCopy(text) {
        var ta = document.createElement('textarea');
        ta.value = text;
        ta.setAttribute('readonly', '');
        ta.style.position = 'fixed';
        ta.style.left = '-9999px';
        document.body.appendChild(ta);
        ta.select();
        try {
            document.execCommand('copy');
        } catch (e) {}
        document.body.removeChild(ta);
    }

    function markCopied(btn) {
        var tip = btn.querySelector('.cabinet-sa-url-copy__tip');
        var icon = btn.querySelector('.fa');
        btn.classList.add('is-copied');
        if (tip) {
            tip.textContent = 'Скопировано';
        }
        if (icon) {
            icon.classList.remove('fa-copy');
            icon.classList.add('fa-check');
        }
        window.clearTimeout(btn.__saCopyTimer);
        btn.__saCopyTimer = window.setTimeout(function () {
            btn.classList.remove('is-copied');
            if (tip) {
                tip.textContent = 'Копировать';
            }
            if (icon) {
                icon.classList.remove('fa-check');
                icon.classList.add('fa-copy');
            }
        }, 1400);
    }

    function copyText(text, btn) {
        if (!text) {
            return;
        }
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(function () {
                markCopied(btn);
            }).catch(function () {
                fallbackCopy(text);
                markCopied(btn);
            });
            return;
        }
        fallbackCopy(text);
        markCopied(btn);
    }

    document.addEventListener('click', function (e) {
        var btn = e.target && e.target.closest ? e.target.closest('[data-sa-copy-url]') : null;
        if (!btn) {
            return;
        }
        e.preventDefault();
        e.stopPropagation();
        copyText(btn.getAttribute('data-sa-copy-url') || '', btn);
    });
})();
