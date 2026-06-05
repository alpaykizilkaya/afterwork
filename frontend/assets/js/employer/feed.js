// Akış filters — progressive enhancement.
//
// Replaces the native <select> UI with our own dropdown (a styled trigger +
// animated menu) while keeping the real <select> in the DOM for the form value
// and the no-JS fallback. Long lists (sector, etc.) get a live search box.
// Choosing an option auto-submits the form, matching the original behaviour.
(function () {
  'use strict';

  var form = document.querySelector('.ep-feed-filters');
  if (!form) {
    return;
  }

  var SEARCH_THRESHOLD = 8; // options needed before a search box appears

  function closeAll(except) {
    document.querySelectorAll('.ep-cs.is-open').forEach(function (cs) {
      if (cs !== except) {
        cs.classList.remove('is-open');
        var t = cs.querySelector('.ep-cs-trigger');
        var m = cs.querySelector('.ep-cs-menu');
        if (t) { t.setAttribute('aria-expanded', 'false'); }
        if (m) { m.hidden = true; }
      }
    });
  }

  form.querySelectorAll('select').forEach(enhance);

  function enhance(select) {
    var wrap = document.createElement('div');
    wrap.className = 'ep-cs';
    select.parentNode.insertBefore(wrap, select);
    wrap.appendChild(select);
    select.classList.add('ep-cs-native');
    select.setAttribute('tabindex', '-1');
    select.setAttribute('aria-hidden', 'true');

    // Trigger
    var trigger = document.createElement('button');
    trigger.type = 'button';
    trigger.className = 'ep-cs-trigger';
    trigger.setAttribute('aria-haspopup', 'listbox');
    trigger.setAttribute('aria-expanded', 'false');
    var value = document.createElement('span');
    value.className = 'ep-cs-value';
    var chevron = document.createElement('span');
    chevron.className = 'ep-cs-chevron';
    chevron.innerHTML = '<svg width="11" height="11" viewBox="0 0 10 10" fill="none"><path d="M2 4l3 3 3-3" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>';
    trigger.appendChild(value);
    trigger.appendChild(chevron);
    wrap.appendChild(trigger);

    // Menu
    var menu = document.createElement('div');
    menu.className = 'ep-cs-menu';
    menu.hidden = true;

    var searchInput = null;
    var useSearch = select.options.length > SEARCH_THRESHOLD;
    if (useSearch) {
      var searchWrap = document.createElement('div');
      searchWrap.className = 'ep-cs-search';
      searchInput = document.createElement('input');
      searchInput.type = 'text';
      searchInput.className = 'ep-cs-search-input';
      searchInput.placeholder = 'Ara…';
      searchInput.setAttribute('aria-label', 'Seçeneklerde ara');
      searchWrap.appendChild(searchInput);
      menu.appendChild(searchWrap);
    }

    var list = document.createElement('ul');
    list.className = 'ep-cs-list';
    list.setAttribute('role', 'listbox');
    menu.appendChild(list);

    var optionEls = [];
    Array.prototype.forEach.call(select.options, function (opt, i) {
      var li = document.createElement('li');
      li.className = 'ep-cs-option';
      li.setAttribute('role', 'option');
      li.textContent = opt.textContent;
      if (opt.selected) {
        li.classList.add('is-selected');
        li.setAttribute('aria-selected', 'true');
      }
      li.addEventListener('click', function (e) {
        e.stopPropagation();
        choose(i);
      });
      list.appendChild(li);
      optionEls.push(li);
    });

    var emptyNote = null;
    if (useSearch) {
      emptyNote = document.createElement('p');
      emptyNote.className = 'ep-cs-empty';
      emptyNote.textContent = 'Sonuç yok';
      emptyNote.hidden = true;
      list.appendChild(emptyNote);
    }

    wrap.appendChild(menu);

    function syncLabel() {
      var sel = select.options[select.selectedIndex];
      value.textContent = sel ? sel.textContent : '';
      trigger.classList.toggle('is-placeholder', !select.value);
    }
    syncLabel();

    function open() {
      closeAll(wrap);
      wrap.classList.add('is-open');
      trigger.setAttribute('aria-expanded', 'true');
      menu.hidden = false;
      if (searchInput) {
        searchInput.value = '';
        filterOptions('');
        // focus after the open animation starts
        window.setTimeout(function () { searchInput.focus(); }, 0);
      }
    }

    function close() {
      wrap.classList.remove('is-open');
      trigger.setAttribute('aria-expanded', 'false');
      menu.hidden = true;
    }

    function choose(i) {
      select.selectedIndex = i;
      optionEls.forEach(function (el, idx) {
        var on = idx === i;
        el.classList.toggle('is-selected', on);
        el.setAttribute('aria-selected', on ? 'true' : 'false');
      });
      syncLabel();
      close();
      select.dispatchEvent(new Event('change', { bubbles: true }));
    }

    function filterOptions(term) {
      var t = term.toLocaleLowerCase('tr');
      var anyVisible = false;
      optionEls.forEach(function (el) {
        var match = el.textContent.toLocaleLowerCase('tr').indexOf(t) !== -1;
        el.hidden = !match;
        if (match) { anyVisible = true; }
      });
      if (emptyNote) { emptyNote.hidden = anyVisible; }
    }

    trigger.addEventListener('click', function (e) {
      e.stopPropagation();
      if (wrap.classList.contains('is-open')) { close(); } else { open(); }
    });

    trigger.addEventListener('keydown', function (e) {
      if (e.key === 'ArrowDown' || e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        open();
      } else if (e.key === 'Escape') {
        close();
      }
    });

    if (searchInput) {
      searchInput.addEventListener('input', function () { filterOptions(searchInput.value); });
      searchInput.addEventListener('click', function (e) { e.stopPropagation(); });
      searchInput.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') { close(); trigger.focus(); }
      });
    }

    menu.addEventListener('click', function (e) { e.stopPropagation(); });
  }

  // Choosing an option auto-submits (mirrors the old data-feed-auto behaviour).
  form.querySelectorAll('select').forEach(function (select) {
    select.addEventListener('change', function () { form.submit(); });
  });

  document.addEventListener('click', function () { closeAll(null); });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') { closeAll(null); }
  });
})();
