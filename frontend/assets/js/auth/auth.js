(() => {
  const tabs = {
    giris: {
      tab: document.getElementById("tab-giris"),
      panel: document.getElementById("panel-giris")
    },
    kayit: {
      tab: document.getElementById("tab-kayit"),
      panel: document.getElementById("panel-kayit")
    }
  };

  const setActive = (key) => {
    const selected = key === "kayit" ? "kayit" : "giris";

    Object.entries(tabs).forEach(([name, value]) => {
      const active = name === selected;
      value.tab.classList.toggle("is-active", active);
      value.tab.setAttribute("aria-selected", String(active));
      value.panel.classList.toggle("is-active", active);

      if (active) {
        value.panel.removeAttribute("hidden");
      } else {
        value.panel.setAttribute("hidden", "");
      }
    });
  };

  const syncFromHash = () => {
    const key = window.location.hash.replace("#", "");
    setActive(key);
  };

  const registerFlow = document.getElementById("register-flow");
  const chooseStep = document.getElementById("register-step-choose");
  const detailsStep = document.getElementById("register-step-details");
  const roleInput = document.getElementById("register-role");
  const usernameLabel = document.getElementById("register-username-label");
  const registerBack = document.getElementById("register-back");
  const roleCards = document.querySelectorAll(".role-card-option");
  const registerGoogle = document.getElementById("register-google");

  const setRegisterStep = (step) => {
    if (!registerFlow || !chooseStep || !detailsStep) {
      return;
    }

    const showDetails = step === "details";
    registerFlow.dataset.step = showDetails ? "details" : "choose";

    if (showDetails) {
      chooseStep.setAttribute("hidden", "");
      detailsStep.removeAttribute("hidden");
    } else {
      detailsStep.setAttribute("hidden", "");
      chooseStep.removeAttribute("hidden");
    }
  };

  const setSelectedRole = (role) => {
    if (!roleInput) {
      return;
    }

    roleInput.value = role;

    if (usernameLabel) {
      if (role === "employer") {
        usernameLabel.textContent = "Şirket Adı";
      } else if (role === "seeker") {
        usernameLabel.textContent = "Ad Soyad";
      } else {
        usernameLabel.textContent = "Kullanıcı Adı";
      }
    }

    roleCards.forEach((card) => {
      card.classList.toggle("is-selected", card.dataset.role === role);
    });

    if (registerGoogle) {
      registerGoogle.setAttribute(
        "href",
        "auth/google/start.php?role=" + encodeURIComponent(role)
      );
    }

    if (role === "employer" || role === "seeker") {
      setRegisterStep("details");
      return;
    }

    setRegisterStep("choose");
  };

  roleCards.forEach((card) => {
    card.addEventListener("click", () => {
      setSelectedRole(card.dataset.role || "");
    });
  });

  if (registerBack) {
    registerBack.addEventListener("click", () => {
      setSelectedRole("");
    });
  }

  syncFromHash();
  setSelectedRole(roleInput?.value || "");
  window.addEventListener("hashchange", syncFromHash);
})();
