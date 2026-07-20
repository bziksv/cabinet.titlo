/**
 * Демо-кабинет: блокируем запуски/сохранения; разрешаем POST только для чтения витрины.
 * Без window.alert — баннер уже объясняет режим; alert давал циклы (blur / ретраи).
 */
(function () {
  if (!document.body || document.body.getAttribute('data-demo-cabinet') !== '1') {
    return;
  }

  var warned = {};
  function warnBlocked(path, method) {
    var key = String(method || '') + ':' + String(path || '');
    if (warned[key]) return;
    warned[key] = true;
    if (window.console && console.info) {
      console.info('[demo-readonly] blocked', method, path || '(empty)');
    }
  }

  var allowPrefixes = [];
  try {
    var raw = document.body.getAttribute('data-demo-readonly-allow');
    if (raw) {
      allowPrefixes = JSON.parse(raw);
    }
  } catch (e) {
    allowPrefixes = [];
  }

  if (!allowPrefixes.length) {
    allowPrefixes = [
      'logout',
      'demo-cabinet/exit',
      'update-statistics',
      'click-tracking',
      'broadcasting/auth',
      'get-details-history',
      'get-stories',
      'get-stories-v2',
      'check-state',
      'check-queue-scan-state',
      'get-relevance-progress-percent',
      'get-slice-result',
      'get-competitor-progress',
      'get-recommendations',
      'get-count-new-news',
      'get-statistic-modules',
      'get-cluster-request',
      'ai-generation/history',
      'monitoring-v2/projects/list',
      'monitoring-v2/portfolio/top10-trend',
      'monitoring-v2/project-stats',
      'monitoring/projects/get-positions-for-calendars',
      'monitoring/get-top/sites',
      'monitoring/competitors/history/positions',
      'monitoring/competitors/history/estimate',
      'monitoring/competitors/check-analyse-state',
      'monitoring/competitors/check-analyse-state-batch',
      'monitoring/projects/competitors',
    ];
  }

  function pathFromUrl(url) {
    try {
      var u = new URL(url, window.location.origin);
      return String(u.pathname || '').replace(/^\/+/, '').replace(/\/+$/, '');
    } catch (e) {
      return String(url || '')
        .replace(/^https?:\/\/[^/]+/i, '')
        .replace(/^\/+/, '')
        .split('?')[0]
        .replace(/\/+$/, '');
    }
  }

  function isAllowedPath(path) {
    path = String(path || '');
    for (var i = 0; i < allowPrefixes.length; i++) {
      var p = String(allowPrefixes[i] || '').replace(/^\/+/, '').replace(/\/+$/, '');
      if (!p) continue;
      if (path === p || path.indexOf(p + '/') === 0) return true;
    }
    // чтение витрины мониторинга (list/trend уже в allowlist; на всякий случай)
    if (path.indexOf('monitoring-v2/') === 0) {
      if (
        path.indexOf('monitoring-v2/snapshots/fill') === 0 ||
        path.indexOf('monitoring-v2/favicons/fill') === 0 ||
        path.indexOf('monitoring-v2/preferences/') === 0 ||
        path.indexOf('monitoring-v2/public-share') === 0
      ) {
        return false;
      }
      return true;
    }
    if (/^monitoring\/\d+\/table$/.test(path)) return true;
    if (path === 'broadcasting/auth') return true;
    return false;
  }

  function isWriteMethod(method) {
    method = String(method || 'GET').toUpperCase();
    return method !== 'GET' && method !== 'HEAD' && method !== 'OPTIONS';
  }

  function blockIfNeeded(url, method) {
    if (!isWriteMethod(method)) return false;
    var path = pathFromUrl(url);
    if (isAllowedPath(path)) return false;
    warnBlocked(path, method);
    return true;
  }

  document.addEventListener(
    'submit',
    function (e) {
      var form = e.target;
      if (!form || !form.action) return;
      var method = (form.getAttribute('method') || 'GET').toUpperCase();
      if (!blockIfNeeded(form.action, method)) return;
      e.preventDefault();
      e.stopPropagation();
    },
    true
  );

  if (window.jQuery) {
    window.jQuery(document).ajaxSend(function (event, jqXHR, settings) {
      if (!blockIfNeeded(settings.url || '', settings.type || 'GET')) return;
      jqXHR.abort();
    });
  }

  if (window.fetch) {
    var origFetch = window.fetch.bind(window);
    window.fetch = function (input, init) {
      init = init || {};
      var url = typeof input === 'string' ? input : (input && input.url) || '';
      var method = init.method;
      if (!method && input && typeof input === 'object' && input.method) {
        method = input.method;
      }
      if (blockIfNeeded(url, method || 'GET')) {
        return Promise.reject(new Error('demo_readonly'));
      }
      return origFetch(input, init);
    };
  }

  // axios / pusher используют XHR напрямую
  if (window.XMLHttpRequest && window.XMLHttpRequest.prototype) {
    var proto = window.XMLHttpRequest.prototype;
    var origOpen = proto.open;
    var origSend = proto.send;
    proto.open = function (method, url) {
      this.__demoReadonlyMethod = method;
      this.__demoReadonlyUrl = url;
      return origOpen.apply(this, arguments);
    };
    proto.send = function () {
      if (blockIfNeeded(this.__demoReadonlyUrl || '', this.__demoReadonlyMethod || 'GET')) {
        this.abort();
        return;
      }
      return origSend.apply(this, arguments);
    };
  }
})();
