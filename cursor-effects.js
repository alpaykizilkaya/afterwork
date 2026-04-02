(() => {
  const prefersReduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const coarsePointer = window.matchMedia('(pointer: coarse)').matches;
  if (prefersReduced || coarsePointer) {
    return;
  }

  const style = document.createElement('style');
  style.textContent = `
    .cursor-effects-layer {
      position: fixed;
      inset: 0;
      pointer-events: none;
      z-index: 9999;
      overflow: hidden;
    }

    .cursor-trail-dot {
      position: absolute;
      width: 5px;
      height: 5px;
      border-radius: 999px;
      background: rgba(8, 8, 8, 0.9);
      transform: translate(-50%, -50%);
      will-change: transform, opacity;
    }

    .cursor-root {
      position: absolute;
      width: 0;
      height: 0;
      transform: translate(-50%, -50%);
      opacity: 1;
      will-change: transform;
    }

    .cursor-root-branch {
      position: absolute;
      left: 0;
      top: 0;
      height: 1.6px;
      border-radius: 999px;
      background: rgba(0, 0, 0, 0.94);
      transform-origin: left center;
      animation: cursorBranchGrow 1s cubic-bezier(0.2, 0.8, 0.25, 1) forwards;
    }

    .cursor-root-branch::after {
      content: '';
      position: absolute;
      right: -2px;
      top: 50%;
      width: 3px;
      height: 3px;
      border-radius: 999px;
      background: rgba(0, 0, 0, 0.85);
      transform: translateY(-50%);
    }

    @keyframes cursorBranchGrow {
      from {
        transform: rotate(var(--angle)) scaleX(0);
        opacity: 0.08;
      }
      to {
        transform: rotate(var(--angle)) scaleX(1);
        opacity: 0.95;
      }
    }

  `;
  document.head.appendChild(style);

  const layer = document.createElement('div');
  layer.className = 'cursor-effects-layer';
  document.body.appendChild(layer);

  let pointerX = window.innerWidth / 2;
  let pointerY = window.innerHeight / 2;
  let lastTrailTs = 0;
  let lastTrailX = pointerX;
  let lastTrailY = pointerY;
  let idleTimer = null;
  let idleTriggered = false;
  let activeRoot = null;
  let isOverInteractive = false;

  const interactiveSelector = [
    'a',
    'button',
    'input',
    'select',
    'textarea',
    'summary',
    'label',
    '[role="button"]',
    '[role="tab"]',
    '[tabindex]:not([tabindex="-1"])'
  ].join(', ');

  const scheduleIdle = () => {
    if (isOverInteractive) {
      return;
    }
    if (idleTimer) {
      window.clearTimeout(idleTimer);
    }
    idleTimer = window.setTimeout(() => {
      if (!activeRoot) {
        spawnRoot(pointerX, pointerY);
        idleTriggered = true;
      }
    }, 4000);
  };

  const clearRoot = () => {
    if (activeRoot) {
      activeRoot.remove();
      activeRoot = null;
    }
    idleTriggered = false;
  };

  const clearIdleTimer = () => {
    if (idleTimer) {
      window.clearTimeout(idleTimer);
      idleTimer = null;
    }
  };

  const isInteractiveTarget = (target) => {
    if (!(target instanceof Element)) {
      return false;
    }
    return Boolean(target.closest(interactiveSelector));
  };

  const spawnTrail = (x, y) => {
    const now = performance.now();
    const dx = x - lastTrailX;
    const dy = y - lastTrailY;
    if (now - lastTrailTs < 42 || (dx * dx + dy * dy) < 80) {
      return;
    }

    lastTrailTs = now;
    lastTrailX = x;
    lastTrailY = y;

    const dot = document.createElement('span');
    dot.className = 'cursor-trail-dot';
    dot.style.left = `${x}px`;
    dot.style.top = `${y}px`;
    layer.appendChild(dot);

    dot.animate(
      [
        { opacity: 0.7, transform: 'translate(-50%, -50%) scale(1)' },
        { opacity: 0.42, transform: 'translate(-50%, -50%) scale(0.92)' },
        { opacity: 0, transform: 'translate(-50%, -50%) scale(0.65)' }
      ],
      { duration: 540, easing: 'cubic-bezier(0.23, 1, 0.32, 1)' }
    ).onfinish = () => dot.remove();
  };

  const spawnRoot = (x, y) => {
    const root = document.createElement('span');
    root.className = 'cursor-root';
    root.style.left = `${x}px`;
    root.style.top = `${y}px`;
    layer.appendChild(root);
    activeRoot = root;

    const branchCount = 7;
    const baseLength = 10;

    for (let i = 0; i < branchCount; i += 1) {
      const angle = (360 / branchCount) * i + (Math.random() * 14 - 7);
      const length = baseLength + Math.random() * 10;

      const branch = document.createElement('span');
      branch.className = 'cursor-root-branch';
      branch.style.width = `${length}px`;
      branch.style.setProperty('--angle', `${angle}deg`);
      branch.style.animationDelay = `${Math.random() * 90}ms`;
      root.appendChild(branch);

      if (Math.random() > 0.35) {
        const twig = document.createElement('span');
        twig.className = 'cursor-root-branch';
        twig.style.width = `${length * 0.34}px`;
        twig.style.left = `${length * 0.62}px`;
        twig.style.top = '0px';
        twig.style.setProperty('--angle', `${(Math.random() > 0.5 ? 1 : -1) * (10 + Math.random() * 16)}deg`);
        twig.style.animationDelay = `${70 + Math.random() * 120}ms`;
        twig.style.opacity = '0.9';
        branch.appendChild(twig);
      }
    }

  };

  window.addEventListener('pointermove', (event) => {
    pointerX = event.clientX;
    pointerY = event.clientY;

    const overInteractive = isInteractiveTarget(event.target);
    if (overInteractive) {
      isOverInteractive = true;
      clearRoot();
      clearIdleTimer();
      return;
    }

    if (isOverInteractive) {
      isOverInteractive = false;
      lastTrailTs = 0;
      lastTrailX = pointerX;
      lastTrailY = pointerY;
    }

    clearRoot();
    spawnTrail(pointerX, pointerY);
    scheduleIdle();
  }, { passive: true });

  window.addEventListener('pointerdown', (event) => {
    if (isInteractiveTarget(event.target)) {
      return;
    }
    pointerX = event.clientX;
    pointerY = event.clientY;
    spawnTrail(pointerX, pointerY);
  }, { passive: true });

  scheduleIdle();
})();
