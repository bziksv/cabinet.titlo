/**
 * Подсчёт длины текста — клиентская обработка (LTE4).
 */
(function (window, document) {
    'use strict';

    var STORAGE_KEY = 'cabinet-text-length-state';
    var root = document.querySelector('.cabinet-text-length-page');
    if (!root) {
        return;
    }

    var textEl = root.querySelector('#cabinet-tl-text');
    var titleEl = root.querySelector('#cabinet-tl-title');
    var descriptionEl = root.querySelector('#cabinet-tl-description');
    var h1El = root.querySelector('#cabinet-tl-h1');
    var charCountEl = root.querySelector('[data-tl-char-count]');
    var overLimitEl = root.querySelector('[data-tl-over-limit]');
    var calcBtn = root.querySelector('[data-tl-calculate]');
    var clearBtn = root.querySelector('[data-tl-clear]');
    var kpiRoot = root.querySelector('.cabinet-tl-kpi');
    var configEl = document.getElementById('cabinet-text-length-config');
    var config = {};
    var saveTimer = null;

    if (configEl && configEl.textContent) {
        try {
            config = JSON.parse(configEl.textContent);
        } catch (e) {
            config = {};
        }
    }

    var maxChars = config.maxChars || 38600;
    var titleMax = config.titleMax || 60;
    var descriptionMax = config.descriptionMax || 160;

    function analyzeSummary(text) {
        var trimmed = String(text).trim();
        if (!trimmed) {
            return {
                chars_with_spaces: 0,
                chars_no_spaces: 0,
                words: 0,
                lines: 0,
                spaces: 0
            };
        }
        var charsWithSpaces = text.length;
        var charsNoSpaces = text.replace(/\s/g, '').length;
        var words = trimmed.split(/\s+/).filter(Boolean).length;
        var lines = text.split('\n').length;
        var spaces = (text.match(/\s/g) || []).length;
        return {
            chars_with_spaces: charsWithSpaces,
            chars_no_spaces: charsNoSpaces,
            words: words,
            lines: lines,
            spaces: spaces
        };
    }

    function analyzeSeo(fields) {
        var title = (fields.title || '').trim();
        var description = (fields.description || '').trim();
        var h1 = (fields.h1 || '').trim();
        var titleChars = title ? title.length : null;
        var descriptionChars = description ? description.length : null;
        var h1Chars = h1 ? h1.length : null;
        return {
            title_chars: titleChars,
            description_chars: descriptionChars,
            h1_chars: h1Chars,
            title_ok: titleChars !== null ? titleChars <= titleMax : null,
            description_ok: descriptionChars !== null ? descriptionChars <= descriptionMax : null
        };
    }

    function analyzeExtended(text) {
        var trimmed = String(text).trim();
        if (!trimmed) {
            return { sentences: 0, paragraphs: 0, reading_time_min: 0 };
        }
        var sentences = trimmed.split(/[.!?…]+/).filter(function (s) {
            return s.trim() !== '';
        });
        var sentenceCount = sentences.length || 1;
        var paragraphs = trimmed.split(/\n\s*\n/).filter(function (p) {
            return p.trim() !== '';
        });
        var paragraphCount = paragraphs.length || 1;
        var words = trimmed.split(/\s+/).filter(Boolean).length;
        var readingTimeMin = Math.max(1, Math.ceil(words / 200));
        return {
            sentences: sentenceCount,
            paragraphs: paragraphCount,
            reading_time_min: readingTimeMin
        };
    }

    function formatNum(n) {
        return Number(n).toLocaleString('ru-RU');
    }

    function updateCharCounter() {
        var len = textEl ? textEl.value.length : 0;
        var over = len > maxChars;
        if (charCountEl) {
            charCountEl.textContent = formatNum(len);
            charCountEl.classList.toggle('is-over', over);
        }
        if (overLimitEl) {
            overLimitEl.hidden = !over;
        }
        if (calcBtn) {
            calcBtn.disabled = over || !String(textEl.value).trim();
        }
    }

    function setSeoMetric(valueEl, hintEl, chars, ok, recommendedText) {
        if (!valueEl) {
            return;
        }
        if (chars === null) {
            valueEl.textContent = '—';
            if (hintEl) {
                hintEl.textContent = config.notFilledText || 'Not filled';
                hintEl.className = 'small mb-0 text-muted';
            }
            return;
        }
        valueEl.textContent = formatNum(chars);
        if (hintEl) {
            hintEl.textContent = (ok ? (config.withinLimitText || 'OK') : (config.overLimitText || 'Over')) + ' · ' + recommendedText;
            hintEl.className = 'small mb-0 ' + (ok ? 'cabinet-tl-seo-ok' : 'cabinet-tl-seo-over');
        }
    }

    function renderReport(summary, seo, extended) {
        root.classList.add('cabinet-tl-has-result');
        if (kpiRoot) {
            kpiRoot.classList.remove('is-empty');
        }

        var map = {
            '[data-tl-kpi-chars]': summary.chars_with_spaces,
            '[data-tl-kpi-chars-no-sp]': summary.chars_no_spaces,
            '[data-tl-kpi-words]': summary.words,
            '[data-tl-kpi-lines]': summary.lines,
            '[data-tl-kpi-spaces]': summary.spaces
        };
        Object.keys(map).forEach(function (sel) {
            var el = root.querySelector(sel);
            if (el) {
                el.textContent = formatNum(map[sel]);
            }
        });

        setSeoMetric(
            root.querySelector('[data-tl-seo-title]'),
            root.querySelector('[data-tl-seo-title-hint]'),
            seo.title_chars,
            seo.title_ok,
            config.recommendedTitleText || ''
        );
        setSeoMetric(
            root.querySelector('[data-tl-seo-description]'),
            root.querySelector('[data-tl-seo-description-hint]'),
            seo.description_chars,
            seo.description_ok,
            config.recommendedDescriptionText || ''
        );

        var h1Val = root.querySelector('[data-tl-seo-h1]');
        if (h1Val) {
            h1Val.textContent = seo.h1_chars === null ? '—' : formatNum(seo.h1_chars);
        }

        var sentEl = root.querySelector('[data-tl-ext-sentences]');
        var parEl = root.querySelector('[data-tl-ext-paragraphs]');
        var readEl = root.querySelector('[data-tl-ext-reading]');
        if (sentEl) {
            sentEl.textContent = formatNum(extended.sentences);
        }
        if (parEl) {
            parEl.textContent = formatNum(extended.paragraphs);
        }
        if (readEl) {
            readEl.textContent = formatNum(extended.reading_time_min) + ' ' + (config.readingMinText || 'min');
        }
    }

    function calculate() {
        var text = textEl ? textEl.value : '';
        if (!String(text).trim() || text.length > maxChars) {
            return;
        }
        var fields = {
            title: titleEl ? titleEl.value : '',
            description: descriptionEl ? descriptionEl.value : '',
            h1: h1El ? h1El.value : ''
        };
        renderReport(analyzeSummary(text), analyzeSeo(fields), analyzeExtended(text));
        scheduleSave();
    }

    function clearAll() {
        if (textEl) {
            textEl.value = '';
        }
        if (titleEl) {
            titleEl.value = '';
        }
        if (descriptionEl) {
            descriptionEl.value = '';
        }
        if (h1El) {
            h1El.value = '';
        }
        root.classList.remove('cabinet-tl-has-result');
        if (kpiRoot) {
            kpiRoot.classList.add('is-empty');
        }
        updateCharCounter();
        try {
            localStorage.removeItem(STORAGE_KEY);
        } catch (e) {
            /* ignore */
        }
    }

    function scheduleSave() {
        if (saveTimer) {
            clearTimeout(saveTimer);
        }
        saveTimer = setTimeout(saveState, 400);
    }

    function saveState() {
        try {
            localStorage.setItem(STORAGE_KEY, JSON.stringify({
                text: textEl ? textEl.value : '',
                title: titleEl ? titleEl.value : '',
                description: descriptionEl ? descriptionEl.value : '',
                h1: h1El ? h1El.value : ''
            }));
        } catch (e) {
            /* ignore */
        }
    }

    function restoreState() {
        try {
            var raw = localStorage.getItem(STORAGE_KEY);
            if (!raw) {
                return;
            }
            var state = JSON.parse(raw);
            if (textEl && state.text) {
                textEl.value = state.text;
            }
            if (titleEl && state.title) {
                titleEl.value = state.title;
            }
            if (descriptionEl && state.description) {
                descriptionEl.value = state.description;
            }
            if (h1El && state.h1) {
                h1El.value = state.h1;
            }
            if (textEl && String(textEl.value).trim()) {
                calculate();
            }
        } catch (e) {
            /* ignore */
        }
    }

    if (textEl) {
        textEl.addEventListener('input', updateCharCounter);
    }
    [titleEl, descriptionEl, h1El].forEach(function (el) {
        if (el) {
            el.addEventListener('input', scheduleSave);
        }
    });
    if (textEl) {
        textEl.addEventListener('input', scheduleSave);
    }
    if (calcBtn) {
        calcBtn.addEventListener('click', calculate);
    }
    if (clearBtn) {
        clearBtn.addEventListener('click', clearAll);
    }

    document.addEventListener('keydown', function (e) {
        if ((e.ctrlKey || e.metaKey) && e.key === 'Enter' && root.contains(document.activeElement)) {
            e.preventDefault();
            calculate();
        }
    });

    updateCharCounter();
    restoreState();
}(window, document));
