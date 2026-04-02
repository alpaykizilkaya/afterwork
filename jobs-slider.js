(() => {
  const card = document.getElementById("job-card");
  const title = document.getElementById("job-title");
  const meta = document.getElementById("job-meta");
  const prev = document.getElementById("job-prev");
  const next = document.getElementById("job-next");

  if (!card || !title || !meta || !prev || !next) {
    return;
  }

  const jobs = [
    {
      title: "(1) İş İlanı Başlığı",
      meta: "(Konum • Çalışma Tipi)",
      href: "auth.php#giris"
    },
    {
      title: "(2) İş İlanı Başlığı",
      meta: "(Konum • Çalışma Tipi)",
      href: "auth.php#giris"
    },
    {
      title: "(3) İş İlanı Başlığı",
      meta: "(Konum • Çalışma Tipi)",
      href: "auth.php#giris"
    },
    {
      title: "(4) İş İlanı Başlığı",
      meta: "(Konum • Çalışma Tipi)",
      href: "auth.php#giris"
    }
  ];

  let current = 0;
  let animating = false;
  const HALF_PHASE_MS = 170;
  const FULL_PHASE_MS = 340;

  const render = () => {
    const job = jobs[current];
    title.textContent = job.title;
    meta.textContent = job.meta;
    card.setAttribute("href", job.href);
  };

  const animateTo = (direction) => {
    if (animating) {
      return;
    }

    animating = true;
    const leavingClass = direction > 0 ? "is-leaving-left" : "is-leaving-right";
    const enteringClass = direction > 0 ? "is-enter-from-right" : "is-enter-from-left";

    card.classList.add(leavingClass);

    setTimeout(() => {
      current = (current + direction + jobs.length) % jobs.length;
      render();

      card.classList.remove(leavingClass);
      card.classList.add(enteringClass);

      requestAnimationFrame(() => {
        requestAnimationFrame(() => {
          card.classList.remove(enteringClass);
        });
      });

      setTimeout(() => {
        animating = false;
      }, FULL_PHASE_MS);
    }, HALF_PHASE_MS);
  };

  prev.addEventListener("click", () => {
    animateTo(-1);
  });

  next.addEventListener("click", () => {
    animateTo(1);
  });

  render();
})();
