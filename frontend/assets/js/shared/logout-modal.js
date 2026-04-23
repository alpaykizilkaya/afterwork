'use strict';

document.addEventListener('DOMContentLoaded', () => {
  const modal = document.getElementById('logout-modal');
  if (!modal) return;

  const triggers = document.querySelectorAll('[data-logout-trigger]');
  const closers = modal.querySelectorAll('[data-logout-close]');
  const backdrop = modal.querySelector('.logout-modal__backdrop');

  const open = () => {
    modal.classList.add('is-open');
    document.body.classList.add('logout-modal-open');
    const btn = modal.querySelector('.logout-modal__btn--danger');
    if (btn) btn.focus();
  };

  const close = () => {
    modal.classList.remove('is-open');
    document.body.classList.remove('logout-modal-open');
  };

  triggers.forEach((t) => t.addEventListener('click', (e) => {
    e.preventDefault();
    open();
  }));

  closers.forEach((c) => c.addEventListener('click', (e) => {
    e.preventDefault();
    close();
  }));

  if (backdrop) backdrop.addEventListener('click', close);

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && modal.classList.contains('is-open')) close();
  });
});
