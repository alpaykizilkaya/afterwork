(() => {
  const items = document.querySelectorAll('.mouse-follow-media');
  if (!items.length) {
    return;
  }

  const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const isCoarsePointer = window.matchMedia('(pointer: coarse)').matches;
  if (prefersReducedMotion || isCoarsePointer) {
    return;
  }

  const clamp = (value, min, max) => Math.min(max, Math.max(min, value));

  items.forEach((item) => {
    item.addEventListener('pointermove', (event) => {
      const rect = item.getBoundingClientRect();
      const x = (event.clientX - rect.left) / rect.width;
      const y = (event.clientY - rect.top) / rect.height;

      const shiftX = clamp((x - 0.5) * 10, -5, 5);
      const shiftY = clamp((y - 0.5) * 10, -5, 5);

      item.style.setProperty('--mouse-shift-x', `${shiftX}px`);
      item.style.setProperty('--mouse-shift-y', `${shiftY}px`);
    });

    item.addEventListener('pointerleave', () => {
      item.style.setProperty('--mouse-shift-x', '0px');
      item.style.setProperty('--mouse-shift-y', '0px');
    });
  });
})();
