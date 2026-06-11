'use strict';

/*
 * Seeker profile — inline section editing + "Profilini geliştir" jumps +
 * school autocomplete.
 *
 * - Every [data-section] holds a .sk-sec-view (display) and .sk-sec-edit (form).
 *   [data-edit-toggle] opens that section's editor in place; [data-edit-cancel]
 *   closes it. The form is a normal POST that saves only its own fields and
 *   returns to the same profile page (anchored back to the section).
 * - [data-open-section="x"] (the tiered "+ ekle" buttons) scrolls to #sec-x and
 *   opens its editor.
 * - [data-school-ac] is a type-ahead over /okul-ara.php (Turkish schools).
 */
(function () {
  function openEditor(section) {
    const view = section.querySelector('.sk-sec-view');
    const edit = section.querySelector('.sk-sec-edit');
    if (!edit) return;
    if (view) view.hidden = true;
    edit.hidden = false;
    section.classList.add('is-editing');
    const first = edit.querySelector('input:not([type=hidden]), textarea, select');
    if (first) setTimeout(() => first.focus({ preventScroll: true }), 30);
  }

  function closeEditor(section) {
    const view = section.querySelector('.sk-sec-view');
    const edit = section.querySelector('.sk-sec-edit');
    if (edit) edit.hidden = true;
    if (view) view.hidden = false;
    section.classList.remove('is-editing');
  }

  document.querySelectorAll('[data-section]').forEach((section) => {
    section.querySelectorAll('[data-edit-toggle]').forEach((btn) =>
      btn.addEventListener('click', (e) => { e.preventDefault(); openEditor(section); }));
    section.querySelectorAll('[data-edit-cancel]').forEach((btn) =>
      btn.addEventListener('click', (e) => { e.preventDefault(); closeEditor(section); }));
  });

  // "Profilini geliştir" → jump to a section and open its editor.
  document.querySelectorAll('[data-open-section]').forEach((btn) => {
    btn.addEventListener('click', (e) => {
      e.preventDefault();
      const target = document.getElementById('sec-' + btn.getAttribute('data-open-section'));
      if (!target) return;
      target.scrollIntoView({ behavior: 'smooth', block: 'center' });
      openEditor(target);
    });
  });

  // Highlight the section just saved (after a #sec-… redirect).
  if (window.location.hash && window.location.hash.startsWith('#sec-')) {
    const target = document.querySelector(window.location.hash);
    if (target) {
      target.classList.add('is-saved');
      setTimeout(() => target.classList.remove('is-saved'), 1600);
    }
  }

  // ── School autocomplete ──
  document.querySelectorAll('[data-school-ac]').forEach((input) => {
    const list = input.parentElement.querySelector('.sk-ac');
    if (!list) return;
    let timer = null;
    let active = -1;

    const hide = () => { list.hidden = true; list.innerHTML = ''; active = -1; };

    const choose = (name) => { input.value = name; hide(); };

    const render = (items) => {
      if (!items.length) { hide(); return; }
      list.innerHTML = items.map((s, i) =>
        '<button type="button" class="sk-ac-item" data-i="' + i + '">' +
        '<span class="sk-ac-name"></span>' +
        (s.city ? '<span class="sk-ac-city"></span>' : '') +
        (s.kind === 'uni' ? '<span class="sk-ac-tag">Üniversite</span>' : '<span class="sk-ac-tag sk-ac-tag--lise">Lise</span>') +
        '</button>'
      ).join('');
      // fill text safely (avoid HTML injection from data)
      Array.from(list.querySelectorAll('.sk-ac-item')).forEach((el, i) => {
        el.querySelector('.sk-ac-name').textContent = items[i].name;
        const cityEl = el.querySelector('.sk-ac-city');
        if (cityEl) cityEl.textContent = items[i].city || '';
        el.addEventListener('mousedown', (e) => { e.preventDefault(); choose(items[i].name); });
      });
      list.hidden = false;
      active = -1;
    };

    const query = () => {
      const q = input.value.trim();
      if (q.length < 2) { hide(); return; }
      fetch('/okul-ara.php?q=' + encodeURIComponent(q))
        .then((r) => (r.ok ? r.json() : []))
        .then((items) => render(Array.isArray(items) ? items : []))
        .catch(() => hide());
    };

    input.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(query, 180); });
    input.addEventListener('blur', () => setTimeout(hide, 120));
    input.addEventListener('keydown', (e) => {
      const items = Array.from(list.querySelectorAll('.sk-ac-item'));
      if (list.hidden || !items.length) return;
      if (e.key === 'ArrowDown') { e.preventDefault(); active = Math.min(active + 1, items.length - 1); }
      else if (e.key === 'ArrowUp') { e.preventDefault(); active = Math.max(active - 1, 0); }
      else if (e.key === 'Enter') { if (active >= 0) { e.preventDefault(); items[active].dispatchEvent(new MouseEvent('mousedown')); } return; }
      else if (e.key === 'Escape') { hide(); return; }
      items.forEach((el, i) => el.classList.toggle('is-active', i === active));
    });
  });
})();
