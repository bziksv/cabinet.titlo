(function ($, window) {
  'use strict';

  const cfg = window.cabinetClusterV2 || {};
  let pollInterval = null;
  let mode = 'classic';
  let clusterDebugPollCount = 0;
  let clusterClientDebugLines = [];
  let clusterLastServerDebugLog = [];
  let clusterLastDebugState = null;
  const clusterAdminDebug = !!cfg.adminDebug;
  let activeProgressId = null;
  let knownTotalPhrases = 0;
  const PROGRESS_STORAGE_KEY = 'cabinet_cluster_v2_active_progress';
  const DRAFT_STORAGE_KEY = 'cabinet_cluster_v2_draft';

  function saveProgressLocal(progressId, total) {
    try {
      sessionStorage.setItem(PROGRESS_STORAGE_KEY, JSON.stringify({
        id: progressId,
        total: total || 0,
        at: Date.now(),
      }));
    } catch (e) { /* ignore */ }
  }

  function clearProgressLocal() {
    try {
      sessionStorage.removeItem(PROGRESS_STORAGE_KEY);
    } catch (e) { /* ignore */ }
  }

  function readProgressLocal() {
    try {
      const raw = sessionStorage.getItem(PROGRESS_STORAGE_KEY);
      if (!raw) return null;
      const data = JSON.parse(raw);
      if (!data || !data.id) return null;
      // старше 12 часов — мусор
      if (data.at && (Date.now() - data.at) > 12 * 60 * 60 * 1000) {
        clearProgressLocal();
        return null;
      }
      return data;
    } catch (e) {
      return null;
    }
  }

  function saveDraftLocal() {
    try {
      sessionStorage.setItem(DRAFT_STORAGE_KEY, JSON.stringify({
        mode: mode,
        phrases: $('#clv2-phrases').val() || '',
        domain: $('#clv2-domain').val() || '',
        comment: $('#clv2-comment').val() || '',
        region: $('#clv2-region').val() || '',
        clusteringLevel: $('#clv2-clustering-level').val() || '',
        searchEngine: $('#clv2-search-engine').val() || 'yandex',
        searchBase: $('#clv2-search-base').is(':checked'),
        searchPhrases: $('#clv2-search-phrases').is(':checked'),
        searchTarget: $('#clv2-search-target').is(':checked'),
        searchRelevance: $('#clv2-search-relevance').is(':checked'),
        save: $('#clv2-save').val(),
        sendMessage: $('#clv2-send-message').length ? $('#clv2-send-message').val() : '0',
        top: $('#clv2-top').val() || '',
        at: Date.now(),
      }));
    } catch (e) { /* ignore */ }
  }

  function restoreDraftLocal() {
    try {
      const raw = sessionStorage.getItem(DRAFT_STORAGE_KEY);
      if (!raw) return false;
      const draft = JSON.parse(raw);
      if (!draft || !draft.phrases) return false;
      if ($('#clv2-phrases').val().trim() !== '') return false;

      if (draft.mode === 'professional' || draft.mode === 'classic') {
        setMode(draft.mode);
      }
      $('#clv2-phrases').val(draft.phrases || '');
      $('#clv2-domain').val(draft.domain || '');
      $('#clv2-comment').val(draft.comment || '');
      if (draft.searchEngine) {
        $('#clv2-search-engine').val(draft.searchEngine).trigger('change');
      }
      if (draft.region) {
        const $region = $('#clv2-region');
        if ($region.find('option[value="' + draft.region + '"]').length === 0) {
          $region.append(new Option(draft.region, draft.region, true, true));
        }
        $region.val(draft.region).trigger('change');
      }
      if (draft.clusteringLevel) {
        $('#clv2-clustering-level').val(draft.clusteringLevel);
      }
      $('#clv2-search-base').prop('checked', !!draft.searchBase);
      $('#clv2-search-phrases').prop('checked', !!draft.searchPhrases);
      $('#clv2-search-target').prop('checked', !!draft.searchTarget);
      $('#clv2-search-relevance').prop('checked', !!draft.searchRelevance);
      if (draft.save != null) $('#clv2-save').val(String(draft.save));
      if ($('#clv2-send-message').length && draft.sendMessage != null) {
        $('#clv2-send-message').val(String(draft.sendMessage));
      }
      if (draft.top) $('#clv2-top').val(draft.top);
      updateLimits();
      return true;
    } catch (e) {
      return false;
    }
  }

  function clusterDebugLine(level, message, context) {
    if (!clusterAdminDebug) return;
    const t = new Date();
    const ts = t.toLocaleTimeString('ru-RU') + '.' + String(t.getMilliseconds()).padStart(3, '0');
    const ctx = context ? ' ' + JSON.stringify(context) : '';
    clusterClientDebugLines.push('[' + ts + '] [' + level + '] [client] ' + message + ctx);
    if (clusterClientDebugLines.length > 80) {
      clusterClientDebugLines = clusterClientDebugLines.slice(-80);
    }
    renderClusterDebugLog(null, null, activeProgressId);
  }

  function renderClusterDebugLog(serverLog, debugState, progressId) {
    if (!clusterAdminDebug) return;

    const $panel = $('#cabinet-clv2-admin-debug');
    const $pre = $('#cabinet-clv2-debug-log');
    if (!$panel.length || !$pre.length) return;

    $panel.show();
    $('#cabinet-clv2-debug-session').text(progressId || activeProgressId || '—');
    $('#cabinet-clv2-debug-poll').text(String(clusterDebugPollCount));

    if (progressId) {
      activeProgressId = progressId;
    }

    if (Array.isArray(serverLog)) {
      clusterLastServerDebugLog = serverLog;
    }
    if (debugState) {
      clusterLastDebugState = debugState;
    }

    const state = debugState || clusterLastDebugState;
    const serverEntries = Array.isArray(serverLog) && serverLog.length
      ? serverLog
      : clusterLastServerDebugLog;

    const lines = [];
    if (state) {
      lines.push('--- state ---');
      lines.push(JSON.stringify(state, null, 2));
    }
    if (serverEntries.length) {
      lines.push('--- server ---');
      serverEntries.forEach(function (row) {
        const ctx = row.context && Object.keys(row.context).length
          ? ' ' + JSON.stringify(row.context)
          : '';
        lines.push('[' + row.t + '] [' + row.level + '] ' + row.message + ctx);
      });
    }
    if (clusterClientDebugLines.length) {
      lines.push('--- browser ---');
      Array.prototype.push.apply(lines, clusterClientDebugLines);
    }
    $pre.text(lines.join('\n'));
    $pre.scrollTop($pre[0].scrollHeight);
  }

  function csrf() {
    return $('meta[name="csrf-token"]').attr('content');
  }

  function countPhrases(text) {
    if (!text) return 0;
    return text.split('\n').filter(function (line) {
      return line.trim() !== '';
    }).length;
  }

  function normalizeDomainInput(raw) {
    if (!raw) return '';

    return raw.split('\n').map(function (line) {
      line = line.trim();
      if (!line) return '';

      if (!/^https?:\/\//i.test(line)) {
        line = 'https://' + line.replace(/^\/+/, '');
      }

      return line;
    }).filter(Boolean).join('\n');
  }

  function applyDomainFieldNormalization() {
    const $domain = $('#clv2-domain');
    if (!$domain.length) return;

    const normalized = normalizeDomainInput($domain.val());
    if (normalized !== $domain.val()) {
      $domain.val(normalized);
    }
  }

  function currentSearchEngine() {
    const engine = ($('#clv2-search-engine').val() || 'yandex').toLowerCase();
    return engine === 'google' ? 'google' : 'yandex';
  }

  function currentTop() {
    const fromSelect = parseInt($('#clv2-top').val(), 10);
    if (!isNaN(fromSelect) && fromSelect > 0) {
      return fromSelect;
    }
    const defaults = mode === 'professional'
      ? (cfg.defaults && cfg.defaults.pro)
      : (cfg.defaults && cfg.defaults.classic);
    const fallback = defaults && defaults.count ? parseInt(defaults.count, 10) : 10;
    return isNaN(fallback) || fallback < 10 ? 10 : fallback;
  }

  function googlePagesForTop(top) {
    return Math.max(1, Math.ceil(Math.max(10, top) / 10));
  }

  function syncTopVisibility() {
    const isGoogle = currentSearchEngine() === 'google';
    const showTop = mode === 'professional' || isGoogle;
    $('#clv2-top-wrap').toggleClass('d-none', !showTop);
    $('#clv2-google-limits-hint').toggleClass('d-none', !isGoogle);
  }

  function limitMultiplier() {
    let m = currentSearchEngine() === 'google' ? googlePagesForTop(currentTop()) : 1;
    if ($('#clv2-search-relevance').is(':checked')) m += 1;
    if ($('#clv2-search-base').is(':checked')) m += 1;
    if ($('#clv2-search-phrases').is(':checked')) m += 1;
    if ($('#clv2-search-target').is(':checked')) m += 1;
    return m;
  }

  function updateChips() {
    const chips = [];
    if ($('#clv2-search-base').is(':checked')) chips.push(cfg.i18n.freqBase);
    if ($('#clv2-search-phrases').is(':checked')) chips.push(cfg.i18n.freqPhrase);
    if ($('#clv2-search-target').is(':checked')) chips.push(cfg.i18n.freqExact);
    if ($('#clv2-search-relevance').is(':checked')) chips.push(cfg.i18n.relevance);
    if (!chips.length) chips.push(cfg.i18n.clusteringOnly);

    const html = chips.map(function (label) {
      return '<span class="badge text-bg-light text-dark border">' + label + '</span>';
    }).join('');
    $('#clv2-option-chips').html(html);
  }

  function isSendMessageYes() {
    return $('#clv2-send-message').val() === '1';
  }

  function toggleTelegramHint(connected) {
    $('#clv2-telegram-hint').toggleClass('d-none', !!connected);
  }

  function rejectTelegramNotify() {
    $('#clv2-send-message').val('0');
    toggleTelegramHint(false);
    showToast('error', cfg.i18n.telegramRequired + ' <a href="' + cfg.routes.profile + '" target="_blank">' + cfg.i18n.connectTelegram + '</a>');
  }

  function verifyTelegramSubscription(onOk) {
    if (!isSendMessageYes()) {
      if (typeof onOk === 'function') onOk(true);
      return;
    }

    $.ajax({
      type: 'GET',
      url: cfg.routes.telegramStatus,
      success: function (response) {
        if (response.connected) {
          toggleTelegramHint(true);
          if (typeof onOk === 'function') onOk(true);
          return;
        }
        rejectTelegramNotify();
        if (typeof onOk === 'function') onOk(false);
      },
      error: function () {
        rejectTelegramNotify();
        if (typeof onOk === 'function') onOk(false);
      },
    });
  }

  function updateLimits() {
    const phrases = countPhrases($('#clv2-phrases').val());
    const mult = limitMultiplier();
    const cost = phrases * mult;

    $('#clv2-phrase-count').text(phrases);
    $('#clv2-stats-phrases').text(phrases);
    $('#clv2-stats-mult').text('×' + mult);
    $('#clv2-limit-cost').text(cost);
    updateChips();
  }

  function setMode(next) {
    mode = next;
    const isClassic = next === 'classic';

    $('#clv2-mode-classic')
      .toggleClass('btn-primary', isClassic)
      .toggleClass('btn-outline-primary', !isClassic);
    $('#clv2-mode-pro')
      .toggleClass('btn-primary', !isClassic)
      .toggleClass('btn-outline-primary', isClassic);

    $('#clv2-pro-panel').toggleClass('d-none', isClassic);
    syncTopVisibility();

    updateLimits();
  }

  function applyDefaults(targetMode) {
    const data = targetMode === 'professional' ? cfg.defaults.pro : cfg.defaults.classic;
    if (!data) return;

    const engine = data.search_engine === 'google' ? 'google' : 'yandex';
    $('#clv2-search-engine').val(engine);
    applyRegionForEngine(engine, data.region, data.region_text);
    $('#clv2-clustering-level').val(data.clustering_level);
    $('#clv2-top').val(String(data.count || 30));
    $('#clv2-save').val(String(data.save_results));
    $('#clv2-search-base').prop('checked', !!data.search_base);
    $('#clv2-search-phrases').prop('checked', !!data.search_phrased);
    $('#clv2-search-target').prop('checked', !!data.search_target);
    $('#clv2-search-relevance').prop('checked', !!data.search_relevance);
    $('#clv2-brut-force').prop('checked', !!data.brut_force);
    $('#clv2-gain-factor').val(data.gain_factor || 10);
    $('#clv2-brut-force-count').val(data.brut_force_count || 1);
    $('#clv2-reduction-ratio').val(data.reduction_ratio || 'pre-hard');
    $('#clv2-ignored-domains').val(data.ignored_domains || '');
    $('#clv2-ignored-words').val(data.ignored_words || '');
    if ($('#clv2-send-message').length) {
      $('#clv2-send-message').val(data.send_message ? '1' : '0');
    }

    toggleBrutForce();
    syncTopVisibility();
    updateLimits();
  }

  function truthyRequestFlag(value) {
    return value === true || value === 'true' || value === 1 || value === '1';
  }

  function applyFromSavedProject(request) {
    if (!request || typeof request !== 'object') return;

    const engine = request.engineVersion === 'professional' ? 'professional' : 'classic';
    setMode(engine);
    applyDefaults(engine);

    $('#clv2-phrases').val(request.phrases || '');
    $('#clv2-domain').val(request.domain || '');
    if ($('#clv2-comment').length) {
      $('#clv2-comment').val(request.comment || '');
    }

    const projectEngine = request.searchEngine === 'google' ? 'google' : 'yandex';
    $('#clv2-search-engine').val(projectEngine);
    applyRegionForEngine(projectEngine, request.region, '');
    if (request.clusteringLevel) {
      $('#clv2-clustering-level').val(request.clusteringLevel);
    }
    if (request.count) {
      $('#clv2-top').val(String(request.count));
    }
    if (request.save !== undefined && request.save !== null) {
      $('#clv2-save').val(String(request.save));
    }

    $('#clv2-search-base').prop('checked', truthyRequestFlag(request.searchBase));
    $('#clv2-search-phrases').prop('checked', truthyRequestFlag(request.searchPhrases));
    $('#clv2-search-target').prop('checked', truthyRequestFlag(request.searchTarget));
    $('#clv2-search-relevance').prop('checked', truthyRequestFlag(request.searchRelevance));
    $('#clv2-brut-force').prop('checked', truthyRequestFlag(request.brutForce));

    if (request.brutForceCount) {
      $('#clv2-brut-force-count').val(request.brutForceCount);
    }
    if (request.gainFactor) {
      $('#clv2-gain-factor').val(request.gainFactor);
    }
    if (request.reductionRatio) {
      $('#clv2-reduction-ratio').val(request.reductionRatio);
    }
    if (request.ignoredDomains) {
      $('#clv2-ignored-domains').val(request.ignoredDomains);
    }
    if (request.ignoredWords) {
      $('#clv2-ignored-words').val(request.ignoredWords);
    }

    applyDomainFieldNormalization();
    toggleBrutForce();
    syncTopVisibility();
    updateLimits();

    const step1 = document.getElementById('clv2-step-1');
    if (step1) {
      step1.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
  }

  function loadProjectFromQuery() {
    const params = new URLSearchParams(window.location.search);
    const id = params.get('from_project');
    if (!id || !cfg.routes.getClusterRequest) return;

    $.ajax({
      type: 'POST',
      url: cfg.routes.getClusterRequest,
      data: { _token: csrf(), id: id },
      success: function (response) {
        if (response && response.request) {
          applyFromSavedProject(response.request);
          showToast('success', cfg.i18n.projectLoaded || 'Параметры проекта подставлены');
        }
      },
    });
  }

  function applyClusterPreset(presetKey) {
    const preset = cfg.presets && cfg.presets[presetKey];
    if (!preset) return;

    $('#clv2-phrases').val(preset.phrases || '');
    $('#clv2-domain').val(preset.domain || '');
    $('#clv2-search-base').prop('checked', !!preset.searchBase);
    $('#clv2-search-phrases').prop('checked', !!preset.searchPhrases);
    $('#clv2-search-target').prop('checked', !!preset.searchTarget);
    $('#clv2-search-relevance').prop('checked', !!preset.searchRelevance);
    $('#clv2-save').val(String(preset.save || '0'));
    if ($('#clv2-send-message').length) {
      $('#clv2-send-message').val(String(preset.sendMessage || '0'));
    }

    applyDomainFieldNormalization();
    updateLimits();

    if (isSendMessageYes()) {
      verifyTelegramSubscription();
    } else {
      $.get(cfg.routes.telegramStatus, function (response) {
        toggleTelegramHint(response.connected);
      });
    }

    if (cfg.i18n.presetApplied) {
      showToast('success', cfg.i18n.presetApplied);
    }

    document.getElementById('clv2-step-3').scrollIntoView({ behavior: 'smooth', block: 'start' });
  }

  function toggleBrutForce() {
    $('#clv2-brut-force-fields').toggleClass('d-none', !$('#clv2-brut-force').is(':checked'));
  }

  function regionSelectLabel(item) {
    if (!item) return '';
    if (item.name) return item.name;
    if (item.text) return String(item.text).replace(/\s*\[\d+\]\s*$/, '');
    return item.id || '';
  }

  function setRegionValue(regionId, regionText) {
    const $region = $('#clv2-region');
    if (!$region.length) return;

    const id = String(regionId || '');
    if (!id) return;

    const text = regionSelectLabel({ name: regionText, text: regionText }) || id;
    $region.find('option').remove();
    const option = new Option(text, id, true, true);
    $region.append(option);
    $region.val(id).trigger('change.select2');
  }

  function defaultRegionForEngine(engine) {
    const key = engine === 'google' ? 'google' : 'yandex';
    const fromCfg = cfg.defaultRegions && cfg.defaultRegions[key];
    if (fromCfg && fromCfg.id) {
      return fromCfg;
    }
    return key === 'google'
      ? { id: '1011969', name: 'Москва', text: 'Москва [1011969]' }
      : { id: '213', name: 'Москва', text: 'Москва [213]' };
  }

  function applyRegionForEngine(engine, preferredId, preferredText) {
    const fallback = defaultRegionForEngine(engine);
    const id = preferredId ? String(preferredId) : '';
    // Яндекс lr обычно ≤5 цифр; Google geotarget — 7+ (напр. 1011969)
    const looksGoogleId = /^\d{7,}$/.test(id);
    const validPreferred = id && (
      (engine === 'google' && looksGoogleId) ||
      (engine !== 'google' && !looksGoogleId)
    );

    if (validPreferred) {
      setRegionValue(id, preferredText || '');
      return;
    }

    setRegionValue(fallback.id, fallback.name || fallback.text || fallback.id);
  }

  function initRegionSelect() {
    const $region = $('#clv2-region');
    if (!$region.length || typeof $.fn.select2 !== 'function') {
      return;
    }

    if ($region.hasClass('select2-hidden-accessible')) {
      $region.select2('destroy');
    }

    $region.select2({
      theme: 'bootstrap4',
      placeholder: $region.data('placeholder') || cfg.i18n.regionPlaceholder,
      allowClear: false,
      minimumInputLength: 0,
      width: '100%',
      dropdownParent: $(document.body),
      templateResult: function (item) {
        if (!item.id) return item.text;
        return item.text || regionSelectLabel(item);
      },
      templateSelection: function (item) {
        if (!item.id) return item.text;
        return regionSelectLabel(item);
      },
      language: {
        inputTooShort: function () {
          return cfg.i18n.regionSearchMin;
        },
        noResults: function () {
          return cfg.i18n.regionNotFound;
        },
        searching: function () {
          return cfg.i18n.regionSearching;
        },
      },
      ajax: {
        delay: 250,
        url: cfg.routes.regions,
        dataType: 'json',
        data: function (params) {
          return {
            q: params.term || '',
            limit: 25,
            engine: currentSearchEngine(),
          };
        },
        processResults: function (data) {
          return {
            results: $.map(data.results || [], function (item) {
              return {
                id: item.id,
                text: item.text,
                name: item.name,
              };
            }),
          };
        },
      },
    });
  }

  function setProgress(pct, label) {
    const value = Math.max(5, Math.min(100, pct));
    $('#clv2-progress-bar').css('width', value + '%').attr('aria-valuenow', value);
    if (label) $('#clv2-progress-label').text(label);
  }

  function buildPayload(progressId) {
    const engine = currentSearchEngine();
    const payload = {
      _token: csrf(),
      progressId: progressId,
      save: $('#clv2-save').val(),
      region: $('#clv2-region').val(),
      phrases: $('#clv2-phrases').val(),
      domain: normalizeDomainInput($('#clv2-domain').val()),
      comment: $('#clv2-comment').val(),
      clusteringLevel: $('#clv2-clustering-level').val(),
      searchBase: $('#clv2-search-base').is(':checked'),
      searchPhrases: $('#clv2-search-phrases').is(':checked'),
      searchTarget: $('#clv2-search-target').is(':checked'),
      searchRelevance: $('#clv2-search-relevance').is(':checked'),
      searchEngine: engine,
      sendMessage: $('#clv2-send-message').length ? $('#clv2-send-message').val() : '0',
      mode: mode === 'classic' ? 'classic' : 'professional',
    };

    if (mode === 'professional' || engine === 'google') {
      payload.count = String(currentTop());
    }

    if (mode === 'professional') {
      payload.brutForce = $('#clv2-brut-force').is(':checked');
      payload.brutForceCount = $('#clv2-brut-force-count').val();
      payload.reductionRatio = $('#clv2-reduction-ratio').val();
      payload.ignoredWords = $('#clv2-ignored-words').val();
      payload.ignoredDomains = $('#clv2-ignored-domains').val();
      payload.gainFactor = $('#clv2-gain-factor').val();
    }

    return payload;
  }

  function showToast(type, message) {
    const $wrap = type === 'success' ? $('.toast-top-right.success-message').first() : $('.toast-top-right.error-message');
    $wrap.find('.toast-message').html(message);
    $wrap.find('.toast').show(300);
    setTimeout(function () {
      $wrap.find('.toast').hide(300);
    }, type === 'success' ? 4000 : 6000);
  }

  function resetResults() {
    $('#cabinet-cluster-v2-results').addClass('d-none');
    $('#result-table').hide();
    $('#clv2-results-meta').text('');
    $('#files-downloads').empty();
    $.each($('.render-table'), function () {
      const id = $(this).attr('id');
      if (id && $.fn.dataTable && $(this).dataTable) {
        try {
          $(this).dataTable().fnDestroy();
        } catch (e) {}
      }
    });
    $('.render-table, .render').remove();
  }

  function initResultsTooltips() {
    if (typeof bootstrap === 'undefined' || !bootstrap.Tooltip) {
      return;
    }
    $('#clusters-table [data-bs-toggle="tooltip"]').each(function () {
      bootstrap.Tooltip.getOrCreateInstance(this);
    });
  }

  function enhanceClusterV2Results() {
    var $rows = $('#clusters-table-tbody tr.render');
    if (!$rows.length) {
      return;
    }

    $rows.each(function () {
      $(this).find('> td:first-child').addClass('cabinet-cluster-v2-cluster-data');
      $(this).find('> td:last-child').addClass('cabinet-cluster-v2-cluster-actions');
    });

    $('#clusters-table .fa-copy.copy-full-urls').attr({
      'data-bs-toggle': 'tooltip',
      'data-bs-placement': 'top',
      title: cfg.i18n.copyUrls,
    });

    $('#clusters-table .fa-paperclip').attr({
      'data-bs-toggle': 'tooltip',
      'data-bs-placement': 'top',
      title: cfg.i18n.viewLinks,
    });

    var phraseCount = $('#rendered-clusters').text();
    var clusterCount = $rows.length;
    if (cfg.i18n.resultsMeta) {
      $('#clv2-results-meta').text(
        cfg.i18n.resultsMeta
          .replace(':clusters', clusterCount)
          .replace(':phrases', phraseCount || '0')
      );
    }

    $('#clv2-freq-zero-hint').remove();
    var freqCells = $('#clusters-table td[class*="base-"], #clusters-table td[class*="phrase-"], #clusters-table td[class*="target-"]');
    var freqNonZero = 0;
    freqCells.each(function () {
      var val = parseInt(String($(this).text()).replace(/\s/g, ''), 10);
      if (!isNaN(val) && val > 0) {
        freqNonZero += 1;
      }
    });
    if (freqCells.length > 0 && freqNonZero === 0 && cfg.i18n.freqZeroHint) {
      $('#cabinet-cluster-v2-results .card-body').prepend(
        '<div id="clv2-freq-zero-hint" class="alert alert-warning py-2 px-3 mb-0 cabinet-cluster-v2-freq-zero-hint small">' +
        cfg.i18n.freqZeroHint +
        '</div>'
      );
    }

    initResultsTooltips();
  }

  var enhanceClusterV2Timer = null;

  function scheduleEnhanceClusterV2Results() {
    if (enhanceClusterV2Timer) {
      clearInterval(enhanceClusterV2Timer);
    }

    var attempts = 0;
    enhanceClusterV2Timer = setInterval(function () {
      attempts += 1;
      var $rows = $('#clusters-table-tbody tr.render');
      var ready = $rows.length > 0;
      var timedOut = attempts > 40;

      if (!ready && !timedOut) {
        return;
      }

      clearInterval(enhanceClusterV2Timer);
      enhanceClusterV2Timer = null;

      if (ready) {
        enhanceClusterV2Results();
      }

      $('#result-table').show();
      $('#cabinet-cluster-v2-results').removeClass('d-none');

      var $results = document.getElementById('cabinet-cluster-v2-results');
      if ($results && ready) {
        $results.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }
    }, 250);
  }

  function onComplete(response) {
    clusterDebugLine('info', 'analysis.complete', { objectId: response.objectId });
    clearProgressLocal();
    if (response.debug_admin) {
      renderClusterDebugLog(response.debug_log || [], response.debug_state || null, activeProgressId);
    }

    $('#clv2-progress-wrap').addClass('d-none');
    $('#clv2-start').prop('disabled', false);

    resetResults();
    if (typeof renderResultTable_v2 === 'function') {
      renderResultTable_v2(response.result, response.objectId);
    }

    $('#files-downloads').html(
      '<a class="btn btn-outline-secondary btn-sm me-1" href="/download-cluster-result/' + response.objectId + '/csv" target="_blank">CSV</a>' +
      '<a class="btn btn-outline-secondary btn-sm" href="/download-cluster-result/' + response.objectId + '/xls" target="_blank">XLS</a>'
    );

    if (typeof saveAllUrls === 'function') {
      saveAllUrls(response.objectId);
    }

    scheduleEnhanceClusterV2Results();

    if ($('#clv2-save').val() === '1') {
      showToast('success', cfg.i18n.historyHint);
    }
  }

  function resumeProgressTracking(progressId, total) {
    if (!progressId) return;
    if (pollInterval) {
      clearInterval(pollInterval);
      pollInterval = null;
    }

    activeProgressId = progressId;
    knownTotalPhrases = parseInt(total, 10) || knownTotalPhrases || 0;
    saveProgressLocal(progressId, knownTotalPhrases);

    $('#clv2-start').prop('disabled', true);
    $('#clv2-progress-wrap').removeClass('d-none');
    if (knownTotalPhrases > 0) {
      setProgress(12, (cfg.i18n.preparing || cfg.i18n.started) + ' 0 / ' + knownTotalPhrases);
    } else {
      setProgress(12, cfg.i18n.started);
    }

    pollInterval = setInterval(function () {
      pollProgress(progressId);
    }, 5000);
    pollProgress(progressId);
  }

  function resumeIfNeeded() {
    const local = readProgressLocal();
    const finishLocal = function () {
      if (!local || !local.id) return;
      resumeProgressTracking(local.id, local.total || 0);
      restoreDraftLocal();
      if (cfg.i18n.resumedHint) {
        showToast('success', cfg.i18n.resumedHint);
      }
    };

    if (!cfg.routes.activeProgress) {
      finishLocal();
      return;
    }

    $.ajax({
      type: 'GET',
      url: cfg.routes.activeProgress,
      success: function (resp) {
        if (resp && resp.status === 'complete' && resp.progress_id) {
          clearProgressLocal();
          activeProgressId = resp.progress_id;
          $('#clv2-start').prop('disabled', true);
          $('#clv2-progress-wrap').removeClass('d-none');
          setProgress(95, cfg.i18n.rendering || cfg.i18n.started);
          pollProgress(resp.progress_id);
          return;
        }

        if (resp && resp.status === 'failed') {
          clearProgressLocal();
          showToast('error', resp.error || cfg.i18n.progressError);
          return;
        }

        if (resp && resp.active && resp.progress_id) {
          restoreDraftLocal();
          resumeProgressTracking(resp.progress_id, resp.phrases_total || (local && local.total) || 0);
          if (cfg.i18n.resumedHint) {
            showToast('success', cfg.i18n.resumedHint);
          }
          return;
        }

        finishLocal();
      },
      error: function () {
        finishLocal();
      },
    });
  }

  function pollProgress(progressId) {
    clusterDebugPollCount += 1;
    clusterDebugLine('info', 'poll.progress', { progressId: progressId, n: clusterDebugPollCount });

    $.ajax({
      type: 'GET',
      url: cfg.routes.progress + '/' + progressId,
      success: function (response) {
        if (response.debug_admin) {
          renderClusterDebugLog(response.debug_log || [], response.debug_state || null, progressId);
        }

        if (response.failed) {
          clearInterval(pollInterval);
          clearProgressLocal();
          $('#clv2-start').prop('disabled', false);
          $('#clv2-progress-wrap').addClass('d-none');
          showToast('error', response.error || cfg.i18n.progressError);
          return;
        }

        if ('result' in response) {
          clearInterval(pollInterval);
          setProgress(100, cfg.i18n.rendering);
          onComplete(response);
          return;
        }

        const q = response.count || 0;
        const total = response.phrases_total
          || (response.debug_state && response.debug_state.phrases_total)
          || knownTotalPhrases
          || 0;
        if (total > 0) {
          knownTotalPhrases = total;
          saveProgressLocal(progressId, total);
        }
        const pending = response.phrases_pending || (response.debug_state && response.debug_state.phrases_pending) || 0;
        const starting = !!(response.starting || (response.debug_state && response.debug_state.starting));
        let pct;
        let label;
        if (total > 0) {
          if (starting || (q === 0 && pending === 0)) {
            pct = 12;
            label = (cfg.i18n.preparing || cfg.i18n.started) + ' 0 / ' + total;
          } else if (q === 0 && pending > 0) {
            pct = Math.min(90, 12 + Math.round((1 / total) * 8));
            label = (cfg.i18n.processed || cfg.i18n.waitingQueue) + ' 0 / ' + total;
          } else {
            pct = Math.min(90, 12 + Math.round((q / total) * 78));
            label = (cfg.i18n.processed || cfg.i18n.queue) + ' ' + q + ' / ' + total;
          }
        } else {
          pct = Math.min(90, 12 + q * 4);
          label = (cfg.i18n.processed || cfg.i18n.queue) + ' ' + q;
        }
        $('#clv2-total-phrases').text('');
        setProgress(pct, label);
      },
      error: function () {
        clusterDebugLine('error', 'poll.failed', { progressId: progressId });
        clearInterval(pollInterval);
        clearProgressLocal();
        $('#clv2-start').prop('disabled', false);
        $('#clv2-progress-wrap').addClass('d-none');
        showToast('error', cfg.i18n.progressError);
      },
    });
  }

  function startAnalysis() {
    const phrases = $('#clv2-phrases').val().trim();
    if (!phrases) {
      showToast('error', cfg.i18n.phrasesRequired);
      $('#clv2-phrases').focus();
      return;
    }

    verifyTelegramSubscription(function (ok) {
      if (!ok) return;
      runAnalysis();
    });
  }

  function runAnalysis() {
    applyDomainFieldNormalization();
    $('#clv2-start').prop('disabled', true);
    resetResults();
    saveDraftLocal();

    $.ajax({
      type: 'GET',
      url: cfg.routes.startProgress,
      success: function (response) {
        const progressId = response.id;
        activeProgressId = progressId;
        clusterDebugPollCount = 0;
        clusterClientDebugLines = [];
        clusterLastServerDebugLog = [];
        clusterLastDebugState = null;
        clusterDebugLine('info', 'progress.started', { progressId: progressId });
        saveProgressLocal(progressId, countPhrases($('#clv2-phrases').val()));
        if (response.debug_admin) {
          renderClusterDebugLog(response.debug_log || [], null, progressId);
        }

        $('#clv2-progress-wrap').removeClass('d-none');
        knownTotalPhrases = countPhrases($('#clv2-phrases').val()) || 0;
        setProgress(12, knownTotalPhrases
          ? ((cfg.i18n.preparing || cfg.i18n.started) + ' 0 / ' + knownTotalPhrases)
          : cfg.i18n.started);
        $('#clv2-total-phrases').text('');

        pollInterval = setInterval(function () {
          pollProgress(progressId);
        }, 5000);
        pollProgress(progressId);

        $.ajax({
          type: 'POST',
          url: cfg.routes.analyse,
          data: buildPayload(progressId),
          success: function (resp) {
            clusterDebugLine('info', 'analyse.accepted', resp);
            if (resp.debug_admin) {
              renderClusterDebugLog(resp.debug_log || [], null, progressId);
            }

            if (resp.totalPhrases) {
              knownTotalPhrases = parseInt(resp.totalPhrases, 10) || 0;
              saveProgressLocal(progressId, knownTotalPhrases);
              setProgress(12, (cfg.i18n.preparing || cfg.i18n.started) + ' 0 / ' + knownTotalPhrases);
            }
            if ($('#clv2-save').val() === '1') {
              $('.history-notification').show(300);
              setTimeout(function () {
                $('.history-notification').hide(300);
              }, 12000);
            }
            if ($('#clv2-send-message').val() === '1') {
              showToast('success', cfg.i18n.notifyChannelsHint || cfg.i18n.longProcessHint);
            } else {
              showToast('success', cfg.i18n.longProcessHint || cfg.i18n.historyHint);
            }
          },
          error: function (xhr) {
            clearInterval(pollInterval);
            clearProgressLocal();
            $('#clv2-start').prop('disabled', false);
            $('#clv2-progress-wrap').addClass('d-none');
            let msg = cfg.i18n.genericError;
            if (xhr.responseJSON && xhr.responseJSON.errors) {
              msg = Object.values(xhr.responseJSON.errors).join(', ');
            }
            showToast('error', msg);
          },
        });
      },
      error: function () {
        clearProgressLocal();
        $('#clv2-start').prop('disabled', false);
        showToast('error', cfg.i18n.genericError);
      },
    });
  }

  $(function () {
    initRegionSelect();
    setMode('classic');
    applyDefaults('classic');

    if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
      document.querySelectorAll('#cabinet-cluster-v2-root [data-bs-toggle="tooltip"]').forEach(function (el) {
        bootstrap.Tooltip.getOrCreateInstance(el);
      });
    }

    $('#clv2-mode-classic, #clv2-mode-pro').on('click', function () {
      const next = $(this).data('mode');
      setMode(next);
      applyDefaults(next);
    });

    $('#clv2-phrases, #clv2-search-base, #clv2-search-phrases, #clv2-search-target, #clv2-search-relevance, #clv2-top')
      .on('input change', updateLimits);

    $('#clv2-search-engine').on('change', function () {
      applyRegionForEngine(currentSearchEngine());
      syncTopVisibility();
      updateLimits();
    });

    $('#clv2-domain').on('blur', applyDomainFieldNormalization);

    $('#clv2-brut-force').on('change', toggleBrutForce);
    $('#clv2-send-message').on('change', function () {
      if (isSendMessageYes()) {
        verifyTelegramSubscription();
      } else {
        $.get(cfg.routes.telegramStatus, function (response) {
          toggleTelegramHint(response.connected);
        });
      }
    });
    $('#clv2-start').on('click', startAnalysis);

    $('#clv2-preset-kawe').on('click', function () {
      applyClusterPreset('kawe');
    });

    if (clusterAdminDebug) {
      $('#cabinet-clv2-debug-clear').on('click', function () {
        clusterClientDebugLines = [];
        $('#cabinet-clv2-debug-log').text('');
      });
      $('#cabinet-clv2-debug-copy').on('click', function () {
        const text = $('#cabinet-clv2-debug-log').text();
        if (!text) return;
        if (navigator.clipboard && navigator.clipboard.writeText) {
          navigator.clipboard.writeText(text);
          return;
        }
        const $tmp = $('<textarea>').val(text).appendTo('body').select();
        document.execCommand('copy');
        $tmp.remove();
      });
    }

    if (isSendMessageYes()) {
      verifyTelegramSubscription();
    }

    updateLimits();
    loadProjectFromQuery();
    resumeIfNeeded();
  });
})(jQuery, window);
