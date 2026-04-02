window.addEventListener("load", () => {
  const splash = document.getElementById("splash");
  const main = document.getElementById("main");

  if (!splash || !main) {
    return;
  }

  setTimeout(() => {
    splash.classList.add("hide");
    main.classList.add("show");
    main.setAttribute("aria-hidden", "false");

    setTimeout(() => {
      splash.remove();
    }, 450);
  }, 1700);
});
