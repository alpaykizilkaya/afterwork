'use strict';

document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.ep-chip[data-target]').forEach((chip) => {
    chip.addEventListener('click', () => {
      const target = document.getElementById(chip.dataset.target);
      if (!target) return;

      const isActive = chip.classList.contains('is-active');

      if (isActive) {
        chip.classList.remove('is-active');
        target.hidden = true;
      } else {
        chip.classList.add('is-active');
        target.hidden = false;
        // Scroll the revealed panel into view smoothly
        target.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        // Focus the first input inside
        const firstInput = target.querySelector('input, select, textarea');
        if (firstInput) firstInput.focus();
      }
    });
  });
});
