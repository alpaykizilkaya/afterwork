(() => {
  const groups = document.querySelectorAll(".afterspace-durations");

  if (!groups.length) {
    return;
  }

  groups.forEach((group) => {
    const chips = group.querySelectorAll(".afterspace-chip");

    chips.forEach((chip) => {
      chip.addEventListener("click", () => {
        chips.forEach((item) => item.classList.remove("is-active"));
        chip.classList.add("is-active");
      });
    });
  });
})();
