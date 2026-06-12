/* Seeker kariyer paneli — sol nav geçişi, "işe açık" toggle, koleksiyon
   arama/filtre/sıralama ve şirket-sorusu düzenleme. Progressive enhancement:
   JS yoksa profil yine görünür (paneller [hidden], nav yalnız profilim'i açar). */
(function () {
  'use strict';

  var dash = document.querySelector('[data-dash]');
  if (!dash) return;

  var navItems = Array.prototype.slice.call(dash.querySelectorAll('.sk-nav-item'));
  var panels   = Array.prototype.slice.call(dash.querySelectorAll('.sk-panel'));

  function panelByKey(key) {
    for (var i = 0; i < panels.length; i++) {
      if (panels[i].getAttribute('data-panel') === key) return panels[i];
    }
    return null;
  }

  function activate(key, push) {
    var target = panelByKey(key);
    if (!target) { key = 'profilim'; target = panelByKey('profilim'); }
    if (!target) return;

    panels.forEach(function (p) {
      var on = p === target;
      p.hidden = !on;
      if (on) {
        // animasyonu yeniden tetikle
        p.style.animation = 'none';
        // reflow
        void p.offsetWidth;
        p.style.animation = '';
      }
    });
    navItems.forEach(function (n) {
      n.classList.toggle('is-active', n.getAttribute('data-nav') === key);
    });

    if (push !== false) {
      try { history.replaceState(null, '', '#' + key); } catch (e) { /* noop */ }
    }
  }

  navItems.forEach(function (n) {
    n.addEventListener('click', function () {
      activate(n.getAttribute('data-nav'));
      // içerik üstüne hizalı kalsın
      var top = dash.getBoundingClientRect().top + window.pageYOffset - 80;
      if (window.pageYOffset > top) window.scrollTo({ top: top, behavior: 'smooth' });
    });
  });

  // İlk yüklemede hash varsa o paneli aç
  var initial = (location.hash || '').replace('#', '');
  if (initial && panelByKey(initial)) {
    activate(initial, false);
  }

  /* ── "İş fırsatlarına açığım" — değişince otomatik kaydet ───────────── */
  var otwForm = dash.querySelector('[data-otw-form]');
  var otwToggle = dash.querySelector('[data-otw-toggle]');
  if (otwForm && otwToggle) {
    otwToggle.addEventListener('change', function () {
      otwForm.classList.toggle('is-on', otwToggle.checked);
      otwForm.submit();
    });
  }

  /* ── Koleksiyon arama / filtre / sıralama ──────────────────────────── */
  function setupCollection(coll) {
    var grid = coll.querySelector('[data-coll-grid]');
    if (!grid) return; // boş durum
    var cards   = Array.prototype.slice.call(grid.querySelectorAll('[data-card]'));
    var q       = coll.querySelector('[data-coll-q]');
    var filters = Array.prototype.slice.call(coll.querySelectorAll('[data-coll-filter]'));
    var sortSel = coll.querySelector('[data-coll-sort]');
    var nomatch = coll.querySelector('.sk-coll-nomatch');
    var order   = cards.slice(); // orijinal (yeni→eski) sıra

    function apply() {
      var term = (q && q.value ? q.value : '').trim().toLocaleLowerCase('tr');
      var fv = {};
      filters.forEach(function (f) { fv[f.getAttribute('data-coll-filter')] = f.value; });

      var visible = 0;
      cards.forEach(function (c) {
        var ok = true;
        if (term && c.getAttribute('data-title').indexOf(term) === -1) ok = false;
        if (ok && fv.loc && c.getAttribute('data-loc') !== fv.loc) ok = false;
        if (ok && fv.model && c.getAttribute('data-model') !== fv.model) ok = false;
        if (ok && fv.sector && c.getAttribute('data-sector') !== fv.sector) ok = false;
        c.style.display = ok ? '' : 'none';
        if (ok) visible++;
      });
      if (nomatch) nomatch.hidden = visible !== 0;

      // sıralama
      var mode = sortSel ? sortSel.value : 'new';
      var sorted = order.slice();
      if (mode === 'old') {
        sorted.reverse();
      } else if (mode === 'salary') {
        sorted.sort(function (a, b) { return (+b.getAttribute('data-salary')) - (+a.getAttribute('data-salary')); });
      } else if (mode === 'company') {
        sorted.sort(function (a, b) {
          return a.getAttribute('data-company').localeCompare(b.getAttribute('data-company'), 'tr');
        });
      }
      sorted.forEach(function (c) { grid.appendChild(c); });
    }

    if (q) q.addEventListener('input', apply);
    filters.forEach(function (f) { f.addEventListener('change', apply); });
    if (sortSel) sortSel.addEventListener('change', apply);
  }
  Array.prototype.slice.call(dash.querySelectorAll('[data-coll]')).forEach(setupCollection);

  /* ── Şirket Sorularım — cevap düzenle/aç-kapa ──────────────────────── */
  Array.prototype.slice.call(dash.querySelectorAll('[data-q]')).forEach(function (q) {
    var view = q.querySelector('.sk-q-view');
    var form = q.querySelector('.sk-q-form');
    var edit = q.querySelector('[data-q-edit]');
    var cancel = q.querySelector('[data-q-cancel]');
    if (!view || !form || !edit) return;
    edit.addEventListener('click', function () {
      view.hidden = true; form.hidden = false;
      var ta = form.querySelector('textarea');
      if (ta) ta.focus();
    });
    if (cancel) cancel.addEventListener('click', function () {
      form.hidden = true; view.hidden = false;
    });
  });

  /* ── "Hemen Başla" → Profilim'e geç + kariyer bölümünü aç ──────────── */
  var goto = dash.querySelector('[data-goto-section]');
  if (goto) {
    goto.addEventListener('click', function () {
      activate('profilim');
      var sec = document.getElementById('sec-' + goto.getAttribute('data-goto-section'));
      if (sec) {
        sec.scrollIntoView({ behavior: 'smooth', block: 'center' });
        var btn = sec.querySelector('[data-edit-toggle]');
        if (btn) setTimeout(function () { btn.click(); }, 350);
      }
    });
  }
})();
