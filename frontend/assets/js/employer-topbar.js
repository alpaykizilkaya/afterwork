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
});
