/* Seeker portföyü — sürükle-bırak yükleme, silme ve üst-üste deste galeri.
   Yükleme /yukle.php'ye fetch ile gider, silme /medya-sil.php'ye. */
(function () {
  'use strict';

  var pf = document.querySelector('[data-portfolio]');
  if (!pf) return;

  var input = pf.querySelector('[data-pf-input]');
  var drop  = pf.querySelector('[data-pf-drop]');
  var msg   = pf.querySelector('[data-pf-msg]');

  function setMsg(text, isErr) {
    if (!msg) return;
    msg.textContent = text || '';
    msg.hidden = !text;
    msg.classList.toggle('is-err', !!isErr);
  }
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }
  function ext(name) { var m = String(name || '').split('.'); return (m.length > 1 ? m.pop() : 'DOC').toUpperCase().slice(0, 4); }

  function group(kind) { return pf.querySelector('[data-pf-group="' + kind + '"]'); }
  function showGroup(kind) { var g = group(kind); if (g) g.hidden = false; }

  function makeEl(html) { var t = document.createElement('template'); t.innerHTML = html.trim(); return t.content.firstChild; }

  function addItem(it) {
    var id = it.id, fp = esc(it.file_path), name = esc(it.original_name);
    if (it.kind === 'doc') {
      var g = group('doc'); showGroup('doc');
      g.querySelector('[data-pf-docs]').appendChild(makeEl(
        '<div class="sk-pf-doc" data-media-id="' + id + '">' +
          '<a class="sk-pf-doc-link" href="' + fp + '" target="_blank" rel="noopener">' +
            '<span class="sk-pf-doc-ic">' + esc(ext(it.original_name)) + '</span>' +
            '<span class="sk-pf-doc-name">' + name + '</span></a>' +
          '<button type="button" class="sk-pf-del" data-pf-del title="Kaldır">×</button></div>'));
    } else if (it.kind === 'video') {
      var gv = group('video'); showGroup('video');
      gv.querySelector('[data-pf-videos]').appendChild(makeEl(
        '<div class="sk-pf-video" data-media-id="' + id + '">' +
          '<video controls preload="metadata" src="' + fp + '"></video>' +
          '<button type="button" class="sk-pf-del" data-pf-del title="Kaldır">×</button></div>'));
    } else { // image (avatar dahil galeriye düşmez ama image düşer)
      var gi = group('image'); showGroup('image');
      gi.querySelector('[data-pf-stack]').appendChild(makeEl(
        '<figure class="sk-pf-slide" data-media-id="' + id + '">' +
          '<img src="' + fp + '" alt="' + name + '" loading="lazy">' +
          '<button type="button" class="sk-pf-del" data-pf-del title="Kaldır">×</button></figure>'));
      deckRefresh(gi.querySelectorAll('.sk-pf-slide').length - 1);
    }
  }

  /* ---- upload ---- */
  function upload(files) {
    if (!files || !files.length) return;
    var fd = new FormData();
    for (var i = 0; i < files.length; i++) fd.append('files[]', files[i]);
    setMsg('Yükleniyor…', false);
    drop && drop.classList.add('is-busy');
    fetch('/yukle.php', { method: 'POST', body: fd, credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        (res.items || []).forEach(addItem);
        if (res.errors && res.errors.length) setMsg(res.errors.join(' · '), true);
        else if ((res.items || []).length) setMsg((res.items.length) + ' dosya eklendi.', false);
        else setMsg('Dosya eklenemedi.', true);
        if ((res.items || []).length) setTimeout(function () { setMsg('', false); }, 2500);
      })
      .catch(function () { setMsg('Yükleme başarısız, tekrar dene.', true); })
      .finally(function () { drop && drop.classList.remove('is-busy'); if (input) input.value = ''; });
  }

  if (input) input.addEventListener('change', function () { upload(input.files); });
  if (drop) {
    ['dragenter', 'dragover'].forEach(function (ev) {
      drop.addEventListener(ev, function (e) { e.preventDefault(); drop.classList.add('is-over'); });
    });
    ['dragleave', 'drop'].forEach(function (ev) {
      drop.addEventListener(ev, function (e) { e.preventDefault(); if (ev === 'dragleave' && drop.contains(e.relatedTarget)) return; drop.classList.remove('is-over'); });
    });
    drop.addEventListener('drop', function (e) { if (e.dataTransfer && e.dataTransfer.files) upload(e.dataTransfer.files); });
  }

  /* ---- delete ---- */
  pf.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-pf-del]');
    if (!btn) return;
    e.preventDefault();
    var wrap = btn.closest('[data-media-id]');
    if (!wrap) return;
    var id = wrap.getAttribute('data-media-id');
    var fd = new FormData(); fd.append('id', id);
    btn.disabled = true;
    fetch('/medya-sil.php', { method: 'POST', body: fd, credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        if (!res.ok) { btn.disabled = false; return; }
        var isSlide = wrap.classList.contains('sk-pf-slide');
        var stack = wrap.parentElement;
        wrap.remove();
        if (isSlide) deckRefresh(0);
        ['doc', 'video', 'image'].forEach(function (k) {
          var g = group(k); if (!g) return;
          var box = g.querySelector('[data-pf-docs],[data-pf-videos],[data-pf-stack]');
          if (box && box.children.length === 0) g.hidden = true;
        });
      })
      .catch(function () { btn.disabled = false; });
  });

  /* ---- deck / carousel ---- */
  var deck = pf.querySelector('[data-pf-deck]');
  var deckIdx = 0;
  function deckRefresh(setTo) {
    if (!deck) return;
    var slides = Array.prototype.slice.call(deck.querySelectorAll('.sk-pf-slide'));
    var prev = deck.querySelector('[data-pf-prev]');
    var next = deck.querySelector('[data-pf-next]');
    var count = deck.querySelector('[data-pf-count]');
    if (typeof setTo === 'number') deckIdx = setTo;
    if (deckIdx >= slides.length) deckIdx = slides.length - 1;
    if (deckIdx < 0) deckIdx = 0;
    slides.forEach(function (s, i) { s.classList.toggle('is-active', i === deckIdx); s.style.zIndex = i === deckIdx ? 3 : 1; });
    var multi = slides.length > 1;
    deck.classList.toggle('is-multi', multi);
    if (prev) prev.style.display = multi ? '' : 'none';
    if (next) next.style.display = multi ? '' : 'none';
    if (count) { count.textContent = slides.length ? (deckIdx + 1) + ' / ' + slides.length : ''; count.style.display = multi ? '' : 'none'; }
  }
  if (deck) {
    deck.querySelector('[data-pf-prev]').addEventListener('click', function () {
      var n = deck.querySelectorAll('.sk-pf-slide').length; if (n) deckRefresh((deckIdx - 1 + n) % n);
    });
    deck.querySelector('[data-pf-next]').addEventListener('click', function () {
      var n = deck.querySelectorAll('.sk-pf-slide').length; if (n) deckRefresh((deckIdx + 1) % n);
    });
    deckRefresh(0);
  }
})();
