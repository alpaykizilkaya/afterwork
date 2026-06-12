/* Afterwork ürün turu — ÇOK SAYFALI spotlight rehberi.
 *
 * Sayfa, tour.js'ten ÖNCE window.AW_TOUR'u tanımlar. Her adımın bir `path`i
 * vardır (hangi ekrana ait). "İleri" sonraki adım başka bir ekrana aitse o
 * ekrana gider ve tur orada kaldığı yerden devam eder. İlerleme localStorage'da
 * tutulur. Her adımda altta sabit "Turu atla" durur. [data-tour-start] butonu
 * turu baştan açar.
 *
 *   window.AW_TOUR = {
 *     id: 'seeker',
 *     auto: true,
 *     steps: [
 *       { path: '/seeker-panel.php', el: null, title, text },
 *       { path: '/akis.php', el: '[data-tour="x"]', title, text, placement },
 *     ],
 *   };
 */
(function () {
  'use strict';

  var cfg = window.AW_TOUR;
  var DONE = 'aw_tour_done_';
  var PROG = 'aw_tour_prog';

  function ls(get, key, val) {
    try { return get ? localStorage.getItem(key) : localStorage.setItem(key, val); }
    catch (e) { return null; }
  }
  function rm(key) { try { localStorage.removeItem(key); } catch (e) {} }

  function isSeen(id) { return ls(1, DONE + id) === '1'; }
  function markSeen(id) { ls(0, DONE + id, '1'); }
  function readProg() { try { return JSON.parse(ls(1, PROG) || 'null'); } catch (e) { return null; } }
  function saveProg(id, i) { ls(0, PROG, JSON.stringify({ id: id, i: i })); }
  function clearProg() { rm(PROG); }

  var root, spot, pop, idx = 0, steps = [], tourId = 'default', curPath = location.pathname;

  function stepPath(s) { return s && s.path ? s.path : curPath; }
  function onThisPage(s) { return stepPath(s) === curPath; }

  function buildDom() {
    if (root) return;
    root = document.createElement('div');
    root.className = 'aw-tour-root';
    root.setAttribute('role', 'dialog');
    root.setAttribute('aria-modal', 'true');
    root.hidden = true;
    spot = document.createElement('div');
    spot.className = 'aw-tour-spot';
    pop = document.createElement('div');
    pop.className = 'aw-tour-pop';
    root.appendChild(spot);
    root.appendChild(pop);
    document.body.appendChild(root);
    window.addEventListener('resize', reposition);
    window.addEventListener('scroll', reposition, true);
    document.addEventListener('keydown', onKey);
  }

  function onKey(e) {
    if (root.hidden) return;
    if (e.key === 'Escape') skip();
    else if (e.key === 'ArrowRight') next();
    else if (e.key === 'ArrowLeft') prev();
  }

  function render() {
    var s = steps[idx], total = steps.length, dots = '';
    for (var i = 0; i < total; i++) dots += '<span class="aw-tour-dot' + (i === idx ? ' is-on' : '') + '"></span>';
    pop.innerHTML =
      '<button type="button" class="aw-tour-close" data-x aria-label="Kapat">&times;</button>' +
      (s.kicker ? '<p class="aw-tour-kicker">' + esc(s.kicker) + '</p>' : '') +
      '<h3 class="aw-tour-title">' + esc(s.title || '') + '</h3>' +
      '<p class="aw-tour-text">' + esc(s.text || '') + '</p>' +
      '<div class="aw-tour-foot">' +
        '<span class="aw-tour-dots">' + dots + '</span>' +
        '<button type="button" class="aw-tour-btn" data-prev' + (idx === 0 ? ' disabled' : '') + '>Geri</button>' +
        '<button type="button" class="aw-tour-btn aw-tour-btn--solid" data-next>' + (idx === total - 1 ? 'Bitir' : 'İleri') + '</button>' +
      '</div>' +
      '<button type="button" class="aw-tour-skip" data-skip>Turu atla</button>';
    pop.querySelector('[data-x]').addEventListener('click', skip);
    pop.querySelector('[data-skip]').addEventListener('click', skip);
    pop.querySelector('[data-next]').addEventListener('click', next);
    var p = pop.querySelector('[data-prev]');
    if (p && idx > 0) p.addEventListener('click', prev);
    reposition();
  }

  function reposition() {
    if (!root || root.hidden) return;
    var s = steps[idx];
    var target = s.el ? document.querySelector(s.el) : null;
    if (!target) {
      spot.className = 'aw-tour-spot is-center';
      spot.style.cssText = '';
      pop.style.top = '50%'; pop.style.left = '50%'; pop.style.transform = 'translate(-50%, -50%)';
      return;
    }
    target.scrollIntoView({ behavior: 'smooth', block: 'center', inline: 'nearest' });
    var r = target.getBoundingClientRect(), pad = 6;
    spot.className = 'aw-tour-spot';
    spot.style.top = (r.top - pad) + 'px';
    spot.style.left = (r.left - pad) + 'px';
    spot.style.width = (r.width + pad * 2) + 'px';
    spot.style.height = (r.height + pad * 2) + 'px';
    pop.style.transform = 'none';
    var pr = pop.getBoundingClientRect(), vw = innerWidth, vh = innerHeight, gap = 14, place = s.placement || 'bottom', top, left;
    if (place === 'right' && r.right + gap + pr.width < vw) { left = r.right + gap; top = r.top + r.height / 2 - pr.height / 2; }
    else if (place === 'left' && r.left - gap - pr.width > 0) { left = r.left - gap - pr.width; top = r.top + r.height / 2 - pr.height / 2; }
    else if (place === 'top' && r.top - gap - pr.height > 0) { top = r.top - gap - pr.height; left = r.left + r.width / 2 - pr.width / 2; }
    else if (r.bottom + gap + pr.height < vh) { top = r.bottom + gap; left = r.left + r.width / 2 - pr.width / 2; }
    else { top = Math.max(gap, r.top - gap - pr.height); left = r.left + r.width / 2 - pr.width / 2; }
    left = Math.max(12, Math.min(left, vw - pr.width - 12));
    top = Math.max(12, Math.min(top, vh - pr.height - 46));
    pop.style.top = top + 'px';
    pop.style.left = left + 'px';
  }

  function open(i) {
    idx = Math.max(0, Math.min(i, steps.length - 1));
    buildDom();
    root.hidden = false;
    document.body.style.overflow = 'hidden';
    render();
  }
  function close() { if (root) { root.hidden = true; } document.body.style.overflow = ''; }

  function gotoStep(i) {
    if (i < 0 || i >= steps.length) return;
    if (onThisPage(steps[i])) { open(i); }
    else { saveProg(tourId, i); location.href = stepPath(steps[i]); }
  }
  function next() { if (idx >= steps.length - 1) finish(); else gotoStep(idx + 1); }
  function prev() { if (idx > 0) gotoStep(idx - 1); }
  function finish() { clearProg(); markSeen(tourId); close(); }
  function skip() { clearProg(); markSeen(tourId); close(); }

  function esc(s) { return String(s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }

  function startFromBeginning() {
    clearProg();
    rm(DONE + tourId);
    gotoStep(0); // 0. adım başka sayfadaysa oraya götürür
  }

  function init() {
    wireStarters();
    if (!cfg || !cfg.steps || !cfg.steps.length) return;
    tourId = cfg.id || 'default';
    steps = cfg.steps;

    // 1) Devam eden tur var mı, bu sayfaya mı ait?
    var prog = readProg();
    if (prog && prog.id === tourId && steps[prog.i] && onThisPage(steps[prog.i])) {
      setTimeout(function () { open(prog.i); }, 450);
      return;
    }
    // 2) İlk giriş: görülmemiş + ilk adım bu sayfada → otomatik başlat
    if (cfg.auto && !isSeen(tourId) && !prog && onThisPage(steps[0])) {
      setTimeout(function () { open(0); }, 650);
    }
  }

  function wireStarters() {
    Array.prototype.slice.call(document.querySelectorAll('[data-tour-start]')).forEach(function (b) {
      b.addEventListener('click', function (e) { e.preventDefault(); startFromBeginning(); });
    });
  }

  window.AfterworkTour = { start: function () { startFromBeginning(); } };

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();
