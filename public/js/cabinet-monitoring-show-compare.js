/**
 * /monitoring/{id} — сравнение с другим проектом (+ отдельная группа), localStorage.
 */
(function (global) {
    'use strict';

    var STORAGE_KEY = 'cabinet-mon-show-compare-v1';
    var cfg = global.cabinetMonProjectConfig || {};
    var state = {
        projectId: null,
        groupId: null,
        projectName: '',
    };
    var projectsById = {};
    var regionBlock = null;
    var changeListeners = [];
    var initialized = false;
    var lastNoticeKey = '';

    function readStore() {
        try {
            var raw = localStorage.getItem(STORAGE_KEY);
            return raw ? JSON.parse(raw) : {};
        } catch (e) {
            return {};
        }
    }

    function writeStore(patch) {
        if (!cfg.projectId) {
            return;
        }
        var all = readStore();
        var key = String(cfg.projectId);
        if (!patch || !patch.projectId) {
            delete all[key];
        } else {
            // regionKey не используем для сброса: смена «все регионы» → конкретный
            // регион как раз нужна для сравнения, выбор проекта должен сохраниться.
            all[key] = {
                projectId: parseInt(patch.projectId, 10),
                groupId: patch.groupId != null && patch.groupId !== '' ? parseInt(patch.groupId, 10) : null,
                projectName: patch.projectName || '',
            };
        }
        try {
            localStorage.setItem(STORAGE_KEY, JSON.stringify(all));
        } catch (e2) {
            /* ignore */
        }
    }

    function loadSaved() {
        var all = readStore();
        var saved = all[String(cfg.projectId)];
        if (!saved || !saved.projectId) {
            return null;
        }
        return {
            projectId: parseInt(saved.projectId, 10),
            groupId: saved.groupId != null ? parseInt(saved.groupId, 10) : null,
            projectName: saved.projectName || '',
        };
    }

    function flattenRegions(project) {
        var out = [];
        var er = (project && project.engine_regions) || {};
        Object.keys(er).forEach(function (engine) {
            (er[engine] || []).forEach(function (r) {
                out.push({
                    engine: String((r.engine || engine) || '').toLowerCase(),
                    lr: String(r.lr != null ? r.lr : ''),
                    name: r.name || '',
                });
            });
        });
        return out;
    }

    function findRegionMatch(project, engine, lr) {
        if (!project || !engine || lr == null || lr === '') {
            return null;
        }
        var eng = String(engine).toLowerCase();
        var lrStr = String(lr);
        return (
            flattenRegions(project).find(function (r) {
                return r.engine === eng && r.lr === lrStr;
            }) || null
        );
    }

    function checkRegionCompatibility(compareProjectId) {
        var cmp = projectsById[compareProjectId];
        if (!cmp) {
            return { ok: false, reason: 'unknown' };
        }
        if (!cfg.baseRegion || !cfg.baseRegion.engine || cfg.baseRegion.lr == null || cfg.baseRegion.lr === '') {
            return {
                ok: false,
                reason: 'no_base_region',
                projectName: cmp.name || cmp.url || String(compareProjectId),
            };
        }
        var match = findRegionMatch(cmp, cfg.baseRegion.engine, cfg.baseRegion.lr);
        if (match) {
            return { ok: true };
        }
        return {
            ok: false,
            reason: 'missing_region',
            projectName: cmp.name || cmp.url || String(compareProjectId),
            baseLabel: cfg.baseRegion.label || '',
            available: flattenRegions(cmp).map(function (r) {
                return r.name;
            }),
        };
    }

    function escapeHtml(value) {
        return String(value == null ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function renderCompareNotice(check) {
        var $notice = $('#cabinetMonCompareNotice');
        if (!$notice.length) {
            return;
        }
        if (!check || check.ok) {
            $notice.addClass('d-none').empty().removeClass('cabinet-mon-compare-notice--warn');
            return;
        }

        var html = '';
        if (check.reason === 'no_base_region') {
            html = escapeHtml(
                (cfg.i18n && cfg.i18n.compareNeedBaseRegion) ||
                    'Чтобы сравнить проекты, выберите один регион в фильтре «Поисковая система».'
            );
            $notice.removeClass('cabinet-mon-compare-notice--warn');
        } else if (check.reason === 'missing_region') {
            var leadTemplate =
                (cfg.i18n && cfg.i18n.compareMissingRegionLead) ||
                'У «:project» нет региона «:region».';
            var availableLabel =
                (cfg.i18n && cfg.i18n.compareMissingRegionAvailable) || 'Можно сравнить по:';
            var lead = leadTemplate
                .replace(':project', check.projectName || '')
                .replace(':region', check.baseLabel || '');
            var tags = (check.available || [])
                .filter(Boolean)
                .map(function (name) {
                    return '<span class="cabinet-mon-compare-notice__tag">' + escapeHtml(name) + '</span>';
                })
                .join('');
            html =
                '<span class="cabinet-mon-compare-notice__lead">' +
                escapeHtml(lead) +
                '</span>' +
                '<span class="cabinet-mon-compare-notice__tags">' +
                '<span class="cabinet-mon-compare-notice__tag cabinet-mon-compare-notice__tag--label">' +
                escapeHtml(availableLabel) +
                '</span>' +
                tags +
                '</span>';
            $notice.addClass('cabinet-mon-compare-notice--warn');
        }

        if (!html) {
            $notice.addClass('d-none').empty();
            return;
        }

        $notice.html(html).removeClass('d-none');
    }

    function notifyRegionMismatch(check) {
        if (!check || check.ok) {
            renderCompareNotice(null);
            return;
        }
        var key = [check.reason, check.projectName, check.baseLabel, (check.available || []).join('|')].join('::');
        if (key === lastNoticeKey) {
            return;
        }
        lastNoticeKey = key;
        renderCompareNotice(check);
    }

    function updateRegionBlock(compareProjectId) {
        if (!compareProjectId) {
            regionBlock = null;
            renderCompareNotice(null);
            return;
        }
        regionBlock = checkRegionCompatibility(compareProjectId);
        if (!regionBlock.ok) {
            notifyRegionMismatch(regionBlock);
        } else {
            renderCompareNotice(null);
        }
    }

    var panelOpen = false;

    function chipIdleLabel() {
        return (cfg.i18n && cfg.i18n.compareChipIdle) || (cfg.i18n && cfg.i18n.compareProject) || 'Сравнить с проектом';
    }

    function syncCompareChrome() {
        var $root = $('#cabinetMonProjectCompare');
        var $chip = $('#cabinet-mon-compare-toggle');
        var $label = $('#cabinet-mon-compare-chip-label');
        var $vs = $('#cabinet-mon-compare-chip-vs');
        var $clear = $('#cabinet-mon-compare-clear');
        var $panel = $('#cabinet-mon-compare-panel');
        if (!$chip.length) {
            return;
        }

        var active = !!state.projectId;
        $root.toggleClass('is-expanded', panelOpen);
        $chip.toggleClass('is-active', active);
        $chip.toggleClass('is-open', panelOpen);
        $chip.attr('aria-expanded', panelOpen ? 'true' : 'false');

        if (active) {
            $label.addClass('d-none');
            $vs.removeClass('d-none').attr('aria-hidden', 'false').text('vs ' + (state.projectName || ('#' + state.projectId)));
            $clear.removeClass('d-none');
        } else {
            $label.removeClass('d-none').text(chipIdleLabel());
            $vs.addClass('d-none').attr('aria-hidden', 'true').text('');
            $clear.addClass('d-none');
        }

        if (panelOpen) {
            $panel.removeClass('d-none');
        } else {
            $panel.addClass('d-none');
        }
    }

    function setPanelOpen(open) {
        panelOpen = !!open;
        syncCompareChrome();
    }

    function clearCompareUi() {
        var $project = $('#cabinet-mon-compare-project');
        var $group = $('#cabinet-mon-compare-group');
        $project.val('').trigger('change');
        if ($project.hasClass('select2-hidden-accessible')) {
            $project.trigger('change.select2');
        }
        $group.prop('disabled', true).empty();
        setState(null);
        setPanelOpen(false);
    }

    function notifyChange() {
        syncCompareChrome();
        changeListeners.forEach(function (cb) {
            try {
                cb(state);
            } catch (e) {
                /* ignore */
            }
        });
    }

    function setState(next) {
        if (!next || !next.projectId) {
            state = { projectId: null, groupId: null, projectName: '' };
            regionBlock = null;
            lastNoticeKey = '';
            renderIntersectHint(null);
            renderCompareChartEmpty(false);
            writeStore(null);
            notifyChange();
            return;
        }
        state = {
            projectId: parseInt(next.projectId, 10),
            groupId: next.groupId != null && next.groupId !== '' ? parseInt(next.groupId, 10) : null,
            projectName: next.projectName || '',
        };
        if (!state.projectName && projectsById[state.projectId]) {
            state.projectName = projectLabel(projectsById[state.projectId]);
        }
        updateRegionBlock(state.projectId);
        writeStore(state);
        notifyChange();
    }

    /**
     * Парсим подписи оси (d.m.Y / Y-m-d / m.Y) → timestamp для сортировки.
     * Без сортировки даты второго проекта дописывались в хвост и ломали шкалу.
     */
    function chartLabelSortKey(label) {
        var s = String(label || '').trim();
        var m = s.match(/^(\d{1,2})\.(\d{1,2})\.(\d{4})$/);
        if (m) {
            return Date.UTC(+m[3], +m[2] - 1, +m[1]);
        }
        m = s.match(/^(\d{4})-(\d{1,2})-(\d{1,2})$/);
        if (m) {
            return Date.UTC(+m[1], +m[2] - 1, +m[3]);
        }
        m = s.match(/^(\d{1,2})\.(\d{4})$/);
        if (m) {
            return Date.UTC(+m[2], +m[1] - 1, 1);
        }
        var t = Date.parse(s);
        return Number.isFinite(t) ? t : Number.MAX_SAFE_INTEGER;
    }

    function alignPayloadLabels(basePayload, comparePayload) {
        var labelSet = {};
        var labels = [];

        function pushLabels(arr) {
            (arr || []).forEach(function (l) {
                if (!Object.prototype.hasOwnProperty.call(labelSet, l)) {
                    labelSet[l] = true;
                    labels.push(l);
                }
            });
        }

        pushLabels(basePayload && basePayload.labels);
        pushLabels(comparePayload && comparePayload.labels);
        labels.sort(function (a, b) {
            return chartLabelSortKey(a) - chartLabelSortKey(b);
        });

        function remapDataset(payload, ds, suffix) {
            var map = {};
            (payload.labels || []).forEach(function (l, i) {
                map[l] = ds.data[i];
            });
            var data = labels.map(function (l) {
                return Object.prototype.hasOwnProperty.call(map, l) ? map[l] : null;
            });
            var out = Object.assign({}, ds, {
                data: data,
                spanGaps: true,
                label: suffix ? ds.label + ' · ' + suffix : ds.label,
            });
            if (ds._compareSeries) {
                out.borderDash = ds.borderDash || [6, 4];
                out._compareSeries = true;
            }
            return out;
        }

        var datasets = [];
        (basePayload.datasets || []).forEach(function (ds) {
            datasets.push(remapDataset(basePayload, ds, basePayload._projectSuffix || ''));
        });
        (comparePayload.datasets || []).forEach(function (ds) {
            var marked = Object.assign({}, ds, { _compareSeries: true });
            datasets.push(remapDataset(comparePayload, marked, comparePayload._projectSuffix || ''));
        });

        return { labels: labels, datasets: datasets };
    }

    function chartPayloadHasSeries(payload) {
        if (!payload || !payload.datasets || !payload.datasets.length) {
            return false;
        }
        if (!payload.labels || !payload.labels.length) {
            return false;
        }
        return payload.datasets.some(function (ds) {
            var label = String((ds && ds.label) || '');
            if (!label || label === 'Chart') {
                return false;
            }
            var data = (ds && ds.data) || [];
            return data.length > 0;
        });
    }

    function mergeChartPayloads(basePayload, comparePayload, baseName, compareName) {
        if (!chartPayloadHasSeries(comparePayload)) {
            return basePayload;
        }
        if (!chartPayloadHasSeries(basePayload)) {
            return comparePayload;
        }
        var base = Object.assign({}, basePayload, { _projectSuffix: baseName || '' });
        var cmp = Object.assign({}, comparePayload, { _projectSuffix: compareName || '' });
        var merged = alignPayloadLabels(base, cmp);
        delete merged._projectSuffix;
        return merged;
    }

    function renderCompareChartEmpty(isEmpty) {
        var $notice = $('#cabinetMonCompareNotice');
        if (!$notice.length) {
            return;
        }
        if (!isEmpty || !canFetchCompareCharts()) {
            if ($notice.attr('data-mon-compare-empty') === '1') {
                $notice.addClass('d-none').empty().removeClass('cabinet-mon-compare-notice--warn').removeAttr('data-mon-compare-empty');
            }
            return;
        }
        var name = state.projectName || ('#' + state.projectId);
        var html =
            '<span class="cabinet-mon-compare-notice__lead">' +
            escapeHtml(
                ((cfg.i18n && cfg.i18n.compareChartEmpty) ||
                    'У «:name» нет позиций за выбранный период/регион — вторая линия на графике не появится.')
                    .replace(':name', name)
            ) +
            '</span>';
        $notice
            .attr('data-mon-compare-empty', '1')
            .html(html)
            .addClass('cabinet-mon-compare-notice--warn')
            .removeClass('d-none');
    }

    function buildIntersectParams(otherProjectId, otherGroupId) {
        if (!otherProjectId) {
            return {};
        }
        var params = {
            intersect: 1,
            intersectProjectId: parseInt(otherProjectId, 10),
        };
        if (otherGroupId != null && otherGroupId !== '') {
            params.intersectGroup = parseInt(otherGroupId, 10);
        }
        return params;
    }

    function renderIntersectHint(meta) {
        var $hint = $('#cabinetMonCompareIntersect');
        if (!$hint.length) {
            return;
        }
        if (!canFetchCompareCharts()) {
            $hint.addClass('d-none').empty();
            return;
        }
        if (!meta || !meta.intersected) {
            $hint.addClass('d-none').empty();
            return;
        }
        var count = parseInt(meta.keywords, 10) || 0;
        if (count <= 0) {
            var emptyLabel =
                (cfg.i18n && cfg.i18n.compareIntersectEmpty) ||
                'Нет общих запросов в выбранных папках — на графиках сравнение будет пустым.';
            $hint.html('<span class="cabinet-mon-compare-intersect__lead">' + escapeHtml(emptyLabel) + '</span>').removeClass('d-none');
            return;
        }
        var template =
            (cfg.i18n && cfg.i18n.compareIntersectHint) ||
            ':count общих запросов в выбранных папках.';
        var note =
            (cfg.i18n && cfg.i18n.compareIntersectChartsNote) ||
            'В таблице — последние даты обоих проектов; на графиках «Обзор» — динамика.';
        $hint
            .html(
                '<span class="cabinet-mon-compare-intersect__lead">' +
                    escapeHtml(template.replace(':count', String(count))) +
                    '</span><span class="cabinet-mon-compare-intersect__note">' +
                    escapeHtml(note) +
                    '</span>'
            )
            .removeClass('d-none');
    }

    function appendIntersectParams(params, forBaseProject) {
        if (!params) {
            return params;
        }
        if (forBaseProject) {
            if (!canFetchCompareCharts()) {
                return params;
            }
            return Object.assign({}, params, buildIntersectParams(state.projectId, state.groupId));
        }
        return Object.assign({}, params, buildIntersectParams(cfg.projectId, cfg.baseGroupId));
    }

    function getChartParams(baseParams) {
        if (!canFetchCompareCharts()) {
            return null;
        }
        var params = Object.assign({}, baseParams, appendIntersectParams({}, false), {
            projectId: state.projectId,
            strictMatch: 1,
        });
        if (state.groupId) {
            params.group = state.groupId;
        } else {
            delete params.group;
        }
        delete params.regionId;
        if (baseParams.matchEngine) {
            params.matchEngine = baseParams.matchEngine;
        }
        if (baseParams.matchLr) {
            params.matchLr = baseParams.matchLr;
        }
        return params;
    }

    function loadProjects() {
        if (!cfg.projectsListUrl) {
            return Promise.resolve([]);
        }
        return fetch(cfg.projectsListUrl, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': cfg.csrf || '',
            },
            credentials: 'same-origin',
            body: JSON.stringify({}),
        })
            .then(function (r) {
                return r.ok ? r.json() : null;
            })
            .then(function (data) {
                return (data && data.projects) || [];
            })
            .catch(function () {
                return [];
            });
    }

    function loadGroups(projectId) {
        if (!cfg.groupsUrl || !projectId) {
            return Promise.resolve([]);
        }
        var url = cfg.groupsUrl + (cfg.groupsUrl.indexOf('?') >= 0 ? '&' : '?') + 'id=' + encodeURIComponent(projectId);
        return fetch(url, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        })
            .then(function (r) {
                return r.ok ? r.json() : [];
            })
            .catch(function () {
                return [];
            });
    }

    function initProjectSelect2($project, $root) {
        if (!$project.length || typeof $project.select2 !== 'function') {
            return;
        }
        if ($project.hasClass('select2-hidden-accessible')) {
            $project.select2('destroy');
        }
        var noneLabel = (cfg.i18n && cfg.i18n.compareNone) || 'Без сравнения';
        var searchPlaceholder =
            (cfg.i18n && cfg.i18n.compareSearchPlaceholder) || 'Название или домен…';
        var $parent = $('#cabinet-mon-compare-panel');
        if (!$parent.length) {
            $parent = $root;
        }
        $project.select2({
            theme: 'bootstrap4',
            width: 'style',
            placeholder: noneLabel,
            allowClear: true,
            minimumResultsForSearch: 0,
            dropdownAutoWidth: true,
            dropdownParent: $parent.length ? $parent : undefined,
            matcher: function (params, data) {
                if (window.cabinetMonitoringSearch && window.cabinetMonitoringSearch.select2Matcher) {
                    return window.cabinetMonitoringSearch.select2Matcher(params, data);
                }
                return data;
            },
            language: {
                noResults: function () {
                    return (cfg.i18n && cfg.i18n.compareNoResults) || 'Ничего не найдено';
                },
                searching: function () {
                    return (cfg.i18n && cfg.i18n.compareSearching) || 'Поиск…';
                },
                inputTooShort: function () {
                    return searchPlaceholder;
                },
            },
        });
    }

    function fillGroupSelect($group, groups, selectedId) {
        var allLabel = (cfg.i18n && cfg.i18n.compareAllGroups) || 'Все группы';
        $group.empty();
        $group.append($('<option>', { value: '', text: allLabel }));
        (groups || []).forEach(function (g) {
            if (g.id == null) {
                return;
            }
            $group.append(
                $('<option>', {
                    value: String(g.id),
                    text: g.name || ('#' + g.id),
                })
            );
        });
        if (selectedId) {
            $group.val(String(selectedId));
        }
    }

    function projectLabel(p) {
        return p.name || p.url || '#' + p.id;
    }

    function initUi() {
        var $root = $('#cabinetMonProjectCompare');
        if (!$root.length || initialized) {
            return Promise.resolve();
        }
        initialized = true;

        var $project = $('#cabinet-mon-compare-project');
        var $group = $('#cabinet-mon-compare-group');
        var noneLabel = (cfg.i18n && cfg.i18n.compareNone) || 'Без сравнения';

        return loadProjects().then(function (projects) {
            $project.empty();
            $project.append($('<option>', { value: '', text: noneLabel }));
            projects.forEach(function (p) {
                if (!p || !p.id || parseInt(p.id, 10) === parseInt(cfg.projectId, 10)) {
                    return;
                }
                projectsById[p.id] = p;
                $project.append(
                    $('<option>', {
                        value: String(p.id),
                        text: projectLabel(p),
                    })
                );
            });

            initProjectSelect2($project, $root);
            syncCompareChrome();

            var saved = loadSaved();
            if (saved && saved.projectId && projectsById[saved.projectId]) {
                $project.val(String(saved.projectId));
                if ($project.hasClass('select2-hidden-accessible')) {
                    $project.trigger('change.select2');
                }
                state = {
                    projectId: saved.projectId,
                    groupId: saved.groupId,
                    projectName: saved.projectName || projectLabel(projectsById[saved.projectId]),
                };
                updateRegionBlock(saved.projectId);
                setPanelOpen(true);
                return loadGroups(saved.projectId).then(function (groups) {
                    fillGroupSelect($group, groups, saved.groupId);
                    $group.prop('disabled', false);
                    notifyChange();
                });
            }

            $project.val('');
            $group.prop('disabled', true);
            syncCompareChrome();
        });
    }

    function wireUi() {
        var $project = $('#cabinet-mon-compare-project');
        var $group = $('#cabinet-mon-compare-group');
        if (!$project.length) {
            return;
        }

        $('#cabinet-mon-compare-toggle')
            .off('click.monCompare')
            .on('click.monCompare', function (e) {
                e.preventDefault();
                setPanelOpen(!panelOpen);
                if (panelOpen) {
                    setTimeout(function () {
                        try {
                            $project.select2('open');
                        } catch (err) {
                            $project.trigger('focus');
                        }
                    }, 50);
                }
            });

        $('#cabinet-mon-compare-clear')
            .off('click.monCompare')
            .on('click.monCompare', function (e) {
                e.preventDefault();
                e.stopPropagation();
                clearCompareUi();
            });

        $project.on('change', function () {
            var pid = $(this).val();
            lastNoticeKey = '';
            if (!pid) {
                $group.prop('disabled', true).empty();
                setState(null);
                return;
            }
            var p = projectsById[pid];
            var name = p ? projectLabel(p) : $project.find('option:selected').text();
            setPanelOpen(true);
            loadGroups(pid).then(function (groups) {
                fillGroupSelect($group, groups, null);
                $group.prop('disabled', false);
                setState({ projectId: pid, groupId: null, projectName: name });
            });
        });

        $group.on('change', function () {
            if (!state.projectId) {
                return;
            }
            var gid = $(this).val();
            setState({
                projectId: state.projectId,
                groupId: gid || null,
                projectName: state.projectName,
            });
        });
    }

    function onChange(cb) {
        if (typeof cb === 'function') {
            changeListeners.push(cb);
        }
    }

    function isActive() {
        return !!state.projectId;
    }

    function canFetchCompareCharts() {
        return !!state.projectId && regionBlock && regionBlock.ok === true;
    }

    function getState() {
        return Object.assign({}, state);
    }

    global.cabinetMonitoringShowCompare = {
        init: function () {
            // wireUi до restore-notify: слушатели страницы уже должны быть на onChange.
            // Сам restore вызывает notifyChange в конце initUi — таблица/графики подхватят.
            return initUi().then(function () {
                wireUi();
            });
        },
        isActive: isActive,
        canFetchCompareCharts: canFetchCompareCharts,
        getState: getState,
        getChartParams: getChartParams,
        appendIntersectParams: appendIntersectParams,
        setIntersectMeta: renderIntersectHint,
        setCompareChartEmpty: renderCompareChartEmpty,
        chartPayloadHasSeries: chartPayloadHasSeries,
        mergeChartPayloads: mergeChartPayloads,
        onChange: onChange,
        /** Принудительно дернуть слушателей (после init / смены региона). */
        notify: notifyChange,
    };
})(window);
