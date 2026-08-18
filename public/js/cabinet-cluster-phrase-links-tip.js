/**
 * Попап «ссылки фразы» внутри results-scroll обрезается overflow.
 * Поднимаем тултип в position:fixed и даём прокрутку списку.
 */
(function ($) {
  'use strict';

  var SELECTOR = '.ui_tooltip_w[data-click="View links phrases"]';
  var pollTimers = new WeakMap();

  function maxTipHeight() {
    return Math.min(Math.floor(window.innerHeight * 0.55), 420);
  }

  function positionTip($wrap) {
    var $tip = $wrap.children('.ui_tooltip').first();
    if (!$tip.length) {
      return;
    }

    var maxH = maxTipHeight();
    $tip.addClass('is-clv2-phrase-links-floating');
    $tip.find('.ui_tooltip_content').css({
      maxHeight: maxH + 'px',
      overflowY: 'auto',
    });

    $tip.css({
      display: 'block',
      visibility: 'hidden',
      left: '0px',
      top: '0px',
    });

    var rect = $wrap[0].getBoundingClientRect();
    var tipW = $tip.outerWidth();
    var tipH = $tip.outerHeight();
    var left = rect.left + rect.width / 2 - tipW / 2;
    left = Math.max(8, Math.min(left, window.innerWidth - tipW - 8));

    var top = rect.bottom + 6;
    if (top + tipH > window.innerHeight - 8) {
      top = rect.top - tipH - 6;
    }
    if (top < 8) {
      top = 8;
    }

    $tip.css({
      visibility: 'visible',
      left: left + 'px',
      top: top + 'px',
    });
  }

  function clearTip($wrap) {
    var $tip = $wrap.children('.ui_tooltip').first();
    if (!$tip.length) {
      return;
    }
    $tip.removeClass('is-clv2-phrase-links-floating').css({
      display: '',
      visibility: '',
      left: '',
      top: '',
    });
    $tip.find('.ui_tooltip_content').css({
      maxHeight: '',
      overflowY: '',
    });
  }

  function stopPoll($wrap) {
    var el = $wrap[0];
    if (!el || !pollTimers.has(el)) {
      return;
    }
    clearInterval(pollTimers.get(el));
    pollTimers.delete(el);
  }

  function contentReady($wrap) {
    var $content = $wrap.find('.ui_tooltip_content');
    if (!$content.length) {
      return false;
    }
    if ($content.find('.clv2-phrase-links-loading').length) {
      return false;
    }
    return $content.find('a').length > 0 || /Нет ссылок/.test($content.text() || '');
  }

  function startPoll($wrap) {
    stopPoll($wrap);
    var tries = 0;
    var el = $wrap[0];
    var iv = setInterval(function () {
      tries += 1;
      if (!$wrap.is(':hover') && !$wrap.find(':hover').length) {
        stopPoll($wrap);
        clearTip($wrap);
        return;
      }
      positionTip($wrap);
      if (contentReady($wrap) || tries > 120) {
        stopPoll($wrap);
        positionTip($wrap);
      }
    }, 50);
    pollTimers.set(el, iv);
  }

  $(document).on('mouseenter focusin', SELECTOR, function () {
    var $wrap = $(this);
    positionTip($wrap);
    startPoll($wrap);
  });

  $(document).on('mouseleave focusout', SELECTOR, function (e) {
    var $wrap = $(this);
    var next = e.relatedTarget;
    if (next && $wrap[0].contains(next)) {
      return;
    }
    stopPoll($wrap);
    clearTip($wrap);
  });

  $(document).on('clv2:phrase-links-ready', function (e, phrase) {
    $(SELECTOR + ':hover').each(function () {
      var $wrap = $(this);
      if (phrase && $wrap.find('.ui_tooltip_content').attr('data-action') !== phrase) {
        return;
      }
      positionTip($wrap);
    });
  });

  $(window).on('resize', function () {
    $(SELECTOR + ':hover').each(function () {
      positionTip($(this));
    });
  });

  document.addEventListener(
    'scroll',
    function () {
      $(SELECTOR + ':hover').each(function () {
        positionTip($(this));
      });
      $('.clv2-phrase-act:hover').each(function () {
        positionActTip($(this));
      });
    },
    true
  );

  function positionActTip($wrap) {
    var $tip = $wrap.children('.clv2-phrase-act__popover').first();
    if (!$tip.length) {
      return;
    }
    $tip.addClass('is-clv2-phrase-links-floating');
    $tip.css({
      display: 'block',
      visibility: 'hidden',
      left: '0px',
      top: '0px',
    });
    var rect = $wrap[0].getBoundingClientRect();
    var tipW = $tip.outerWidth();
    var tipH = $tip.outerHeight();
    var left = rect.left + rect.width / 2 - tipW / 2;
    left = Math.max(8, Math.min(left, window.innerWidth - tipW - 8));
    var top = rect.top - tipH - 6;
    if (top < 8) {
      top = rect.bottom + 6;
    }
    $tip.css({
      visibility: 'visible',
      left: left + 'px',
      top: top + 'px',
    });
  }

  function clearActTip($wrap) {
    $wrap.children('.clv2-phrase-act__popover').removeClass('is-clv2-phrase-links-floating').css({
      display: '',
      visibility: '',
      left: '',
      top: '',
    });
  }

  $(document).on('mouseenter focusin', '.clv2-phrase-act', function () {
    positionActTip($(this));
  });

  $(document).on('mouseleave focusout', '.clv2-phrase-act', function (e) {
    var $wrap = $(this);
    var next = e.relatedTarget;
    if (next && $wrap[0].contains(next)) {
      return;
    }
    clearActTip($wrap);
  });
})(jQuery);
