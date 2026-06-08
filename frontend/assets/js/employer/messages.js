// Mesajlar — light progressive enhancement (the page works without JS via POST→redirect).
(function () {
  'use strict';

  // Set up an open thread: jump to the newest message and wire the composer.
  function initThread() {
    var scroll = document.getElementById('msg-scroll');
    if (scroll) {
      scroll.scrollTop = scroll.scrollHeight;
    }

    var ta = document.querySelector('.msg-compose textarea');
    if (!ta) {
      return;
    }

    // Auto-grow the composer up to its CSS max-height.
    function grow() {
      ta.style.height = 'auto';
      ta.style.height = Math.min(ta.scrollHeight, 144) + 'px';
    }
    ta.addEventListener('input', grow);
    grow();
    ta.focus();
    ta.setSelectionRange(ta.value.length, ta.value.length);

    // Enter sends; Shift+Enter makes a new line.
    ta.addEventListener('keydown', function (e) {
      if (e.key === 'Enter' && !e.shiftKey) {
        if (ta.value.trim() === '') {
          e.preventDefault();
          return;
        }
        e.preventDefault();
        ta.form.requestSubmit ? ta.form.requestSubmit() : ta.form.submit();
      }
    });
  }

  initThread();

  // Switching sides (İş Başvuranlar ⇄ İş Verenler) without a page reload, so the
  // button colour transitions smoothly and the inbox swaps instantly. Both lists
  // are already in the DOM; we toggle visibility and reset the pane to blank — a
  // freshly switched side has no conversation open, matching the server state.
  var sw = document.querySelector('.msg-switch');
  var list = document.querySelector('.msg-list');
  var pane = document.querySelector('.msg-pane');
  var blankTpl = document.getElementById('msg-blank-tpl');

  if (sw && list && pane && blankTpl) {
    sw.addEventListener('click', function (e) {
      var btn = e.target.closest('.msg-switch-btn');
      if (!btn || btn.classList.contains('is-on')) {
        return;
      }
      e.preventDefault();

      var url = btn.getAttribute('href');
      var side = /side=verenler/.test(url) ? 'verenler' : 'basvuranlar';

      // Smooth colour change: toggle the active button in place (CSS transition).
      sw.querySelectorAll('.msg-switch-btn').forEach(function (b) {
        var on = b === btn;
        b.classList.toggle('is-on', on);
        b.setAttribute('aria-selected', on ? 'true' : 'false');
      });

      // Show the chosen side's threads, hide the other.
      list.querySelectorAll('.msg-threads').forEach(function (el) {
        el.hidden = el.dataset.side !== side;
      });

      // Reset selection + pane to the blank state.
      list.querySelectorAll('.msg-thread.is-active').forEach(function (el) {
        el.classList.remove('is-active');
      });
      list.classList.remove('has-active');
      pane.innerHTML = blankTpl.innerHTML;

      history.pushState({ side: side }, '', url);
    });

    // Back/forward should reflect the real server-rendered state.
    window.addEventListener('popstate', function () {
      window.location.reload();
    });
  }
})();
