'use strict';

/*
 * Seeker onboarding wizard — a 4-step carousel.
 * Each step's small bottom-right button validates its required fields, then
 * slides the track to the next step; the progress dots track where you are.
 * The last step's button is a real submit (saves the profile).
 */
(function () {
  const track = document.getElementById('sk-ob-track');
  const dotsWrap = document.getElementById('sk-ob-dots');
  if (!track) return;

  const steps = Array.from(track.querySelectorAll('.sk-ob-step'));
  const dots = dotsWrap ? Array.from(dotsWrap.querySelectorAll('.sk-ob-dot')) : [];
  let index = 0;

  function render() {
    track.style.transform = 'translateX(' + (-index * 100) + '%)';
    dots.forEach((d, i) => {
      d.classList.toggle('is-active', i === index);
      d.classList.toggle('is-done', i < index);
    });
    // Focus the first field of the now-visible step (after the slide settles).
    const field = steps[index] && steps[index].querySelector('input, select, textarea');
    if (field) setTimeout(() => field.focus({ preventScroll: true }), 380);
  }

  // Returns true if every [data-ob-required] field in the step has a value.
  function validate(step) {
    let ok = true;
    let firstBad = null;
    step.querySelectorAll('[data-ob-required]').forEach((el) => {
      const empty = String(el.value || '').trim() === '';
      const wrap = el.closest('.sk-field');
      if (empty) {
        ok = false;
        if (wrap) wrap.classList.add('has-error');
        if (!firstBad) firstBad = el;
      } else if (wrap) {
        wrap.classList.remove('has-error');
      }
    });
    if (firstBad) firstBad.focus({ preventScroll: true });
    return ok;
  }

  function next() {
    if (index >= steps.length - 1) return;
    if (!validate(steps[index])) return;
    index += 1;
    render();
  }

  // Clear a field's error state as soon as the user types/picks something.
  track.addEventListener('input', (e) => {
    const wrap = e.target.closest && e.target.closest('.sk-field.has-error');
    if (wrap && String(e.target.value || '').trim() !== '') wrap.classList.remove('has-error');
  });

  track.querySelectorAll('[data-ob-next]').forEach((btn) => {
    btn.addEventListener('click', next);
  });

  // Enter inside a text input advances instead of submitting the whole form.
  track.addEventListener('keydown', (e) => {
    if (e.key === 'Enter' && e.target.tagName === 'INPUT') {
      e.preventDefault();
      next();
    }
  });

  render();
})();
