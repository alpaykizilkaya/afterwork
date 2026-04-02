(() => {
  const sections = [
    { element: document.getElementById("is-ilanlari"), cssVar: "--finder-progress", threshold: 0.58 },
    { element: document.getElementById("isveren"), cssVar: "--employer-progress", threshold: 0.58 },
    { element: document.getElementById("afterspace"), cssVar: "--afterspace-progress", threshold: 0.62 }
  ].filter((item) => item.element);

  if (!sections.length) {
    return;
  }

  const prefersReducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

  if (prefersReducedMotion) {
    sections.forEach(({ element, cssVar }) => {
      element.style.setProperty(cssVar, "1");
      element.classList.add("is-active");
    });
    return;
  }

  let ticking = false;

  const clamp = (value, min, max) => Math.min(max, Math.max(min, value));

  const updateProgress = () => {
    const viewportHeight = window.innerHeight || 1;
    const startPoint = viewportHeight * 0.95;
    const endPoint = viewportHeight * 0.12;

    sections.forEach(({ element, cssVar, threshold }) => {
      const rect = element.getBoundingClientRect();
      const raw = (startPoint - rect.top) / (startPoint - endPoint);
      const progress = clamp(raw, 0, 1);

      element.style.setProperty(cssVar, progress.toFixed(3));
      element.classList.toggle("is-active", progress >= threshold);
    });

    ticking = false;
  };

  const requestTick = () => {
    if (ticking) {
      return;
    }

    ticking = true;
    window.requestAnimationFrame(updateProgress);
  };

  updateProgress();
  window.addEventListener("scroll", requestTick, { passive: true });
  window.addEventListener("resize", requestTick);
})();
