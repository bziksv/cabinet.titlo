/**
 * Демо-кабинет: блокируем запуски/сохранения; разрешаем POST только для чтения витрины.
 */
(function () {
  if (!document.body || document.body.getAttribute('data-demo-cabinet') !== '1') {
    return;
  }

  var message =
    'Демо-кабинет только для просмотра. Запуски и изменения отключены — зарегистрируйтесь для своей работы.';

  var alertedOnce = false;
  function notifyReadonly() {
    if (alertedOnce) return;
    alertedOnce = true;
    window.alert(message);
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
    if (/^monitoring\/\d+\/table$/.test(path)) return true;
    return false;
  }

  function isAllowedForm(form) {
    if (!form || !form.action) return false;
    return isAllowedPath(pathFromUrl(form.action));
  }

  document.addEventListener(
    'submit',
    function (e) {
      if (isAllowedForm(e.target)) return;
      e.preventDefault();
      e.stopPropagation();
      notifyReadonly();
    },
    true
  );

  if (window.jQuery) {
    var $ = window.jQuery;
    $(document).ajaxSend(function (event, jqXHR, settings) {
      var type = String(settings.type || 'GET').toUpperCase();
      if (type === 'GET' || type === 'HEAD') return;
      var path = pathFromUrl(settings.url || '');
      if (isAllowedPath(path)) return;
      jqXHR.abort();
      notifyReadonly();
    });
  }

  if (window.fetch) {
    var origFetch = window.fetch.bind(window);
    window.fetch = function (input, init) {
      init = init || {};
      var method = String(init.method || 'GET').toUpperCase();
      var url = typeof input === 'string' ? input : (input && input.url) || '';
      if (method !== 'GET' && method !== 'HEAD') {
        if (!isAllowedPath(pathFromUrl(url))) {
          notifyReadonly();
          return Promise.reject(new Error('demo_readonly'));
        }
      }
      return origFetch(input, init);
    };
  }
})();
