'use strict';

document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('[data-ep-menu]').forEach((menuRoot) => {
    const trigger = menuRoot.querySelector('[data-ep-menu-trigger]');
    const menu = menuRoot.querySelector('.ep-menu');
    if (!trigger || !menu) return;

    const close = () => {
      menuRoot.classList.remove('is-open');
      menu.hidden = true;
      trigger.setAttribute('aria-expanded', 'false');
    };

    const open = () => {
      menuRoot.classList.add('is-open');
      menu.hidden = false;
      trigger.setAttribute('aria-expanded', 'true');
    };

    trigger.addEventListener('click', (e) => {
      e.stopPropagation();
      if (menuRoot.classList.contains('is-open')) {
        close();
      } else {
        open();
      }
    });

    document.addEventListener('click', (e) => {
      if (!menuRoot.contains(e.target)) close();
    });

    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && menuRoot.classList.contains('is-open')) close();
    });
  });

  // ── Notifications: mark-as-read (single + all) ──
  const notif = document.querySelector('.ep-notif');
  if (!notif) return;

  const badge = notif.querySelector('[data-notif-badge]');
  const countEl = notif.querySelector('[data-notif-count]');
  const readAllBtn = notif.querySelector('[data-notif-readall]');

  const applyUnread = (unread) => {
    if (badge) {
      if (unread > 0) {
        badge.textContent = unread > 9 ? '9+' : String(unread);
        badge.hidden = false;
      } else {
        badge.hidden = true;
      }
    }
    if (countEl) {
      countEl.textContent = String(unread);
      countEl.hidden = unread <= 0;
    }
    if (readAllBtn) readAllBtn.hidden = unread <= 0;
  };

  const post = (body) =>
    fetch('/bildirimler.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body,
    })
      .then((r) => (r.ok ? r.json() : null))
      .catch(() => null);

  notif.querySelectorAll('.ep-notif-item').forEach((item) => {
    item.addEventListener('click', () => {
      if (!item.classList.contains('is-unread')) return;
      const id = item.getAttribute('data-notif-id');
      item.classList.remove('is-unread'); // optimistic
      post('action=read&id=' + encodeURIComponent(id)).then((res) => {
        if (res && res.ok) applyUnread(res.unread);
      });
    });
  });

  if (readAllBtn) {
    readAllBtn.addEventListener('click', () => {
      notif.querySelectorAll('.ep-notif-item.is-unread').forEach((el) => el.classList.remove('is-unread'));
      applyUnread(0); // optimistic
      post('action=read_all').then((res) => {
        if (res && res.ok) applyUnread(res.unread);
      });
    });
  }
});
