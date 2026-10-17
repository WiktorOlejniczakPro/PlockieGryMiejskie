/**
 * PGM – drobne zachowania interfejsu wspólne dla wszystkich stron.
 * Bez zależności. Strona działa także bez JavaScriptu.
 */
(function () {
  "use strict";

  document.documentElement.classList.add("js");

  document.addEventListener("DOMContentLoaded", function () {
    initPublicNav();
    initSidebar();
    initPasswordToggles();
    initFormValidation();
    initHistoryBack();
    initAccountMenu();
  });

  /* Menu strony publicznej na telefonach */
  function initPublicNav() {
    var toggle = document.querySelector("[data-nav-toggle]");
    var nav = document.getElementById("site-nav");
    if (!toggle || !nav) return;

    function setOpen(open) {
      toggle.setAttribute("aria-expanded", String(open));
      nav.classList.toggle("is-open", open);
    }
    toggle.addEventListener("click", function () {
      setOpen(toggle.getAttribute("aria-expanded") !== "true");
    });
    nav.addEventListener("click", function (e) {
      if (e.target.closest("a")) setOpen(false);
    });
    document.addEventListener("keydown", function (e) {
      if (e.key === "Escape") setOpen(false);
    });
  }

  /* Wysuwane menu boczne panelu na mniejszych ekranach */
  function initSidebar() {
    var toggle = document.querySelector("[data-sidebar-toggle]");
    var sidebar = document.getElementById("sidebar");
    var backdrop = document.querySelector("[data-sidebar-close]");
    if (!toggle || !sidebar) return;

    function setOpen(open) {
      toggle.setAttribute("aria-expanded", String(open));
      sidebar.classList.toggle("is-open", open);
      if (backdrop) backdrop.hidden = !open;
      if (open) {
        var first = sidebar.querySelector("a, button");
        if (first) first.focus();
      }
    }
    toggle.addEventListener("click", function () {
      setOpen(!sidebar.classList.contains("is-open"));
    });
    if (backdrop) backdrop.addEventListener("click", function () { setOpen(false); });
    document.addEventListener("keydown", function (e) {
      if (e.key === "Escape" && sidebar.classList.contains("is-open")) {
        setOpen(false);
        toggle.focus();
      }
    });
  }

  /* Przycisk „Pokaż / Ukryj” przy polu hasła */
  function initPasswordToggles() {
    document.querySelectorAll("[data-password-toggle]").forEach(function (btn) {
      var input = document.getElementById(btn.getAttribute("data-password-toggle"));
      if (!input) return;
      btn.addEventListener("click", function () {
        var show = input.type === "password";
        input.type = show ? "text" : "password";
        btn.textContent = show ? "Ukryj" : "Pokaż";
        btn.setAttribute("aria-pressed", String(show));
      });
    });
  }

  /* Walidacja po stronie przeglądarki – serwer i tak sprawdza wszystko ponownie */
  function initFormValidation() {
    document.querySelectorAll("form[data-validate]").forEach(function (form) {
      form.addEventListener("submit", function (e) {
        var firstInvalid = null;
        form.querySelectorAll("input[required], select[required], textarea[required]").forEach(function (field) {
          var valid = field.value.trim() !== "" && field.checkValidity();
          var wrapper = field.closest(".field");
          var msg = form.querySelector('[data-error-for="' + field.name + '"]');
          if (wrapper) wrapper.classList.toggle("has-error", !valid);
          if (msg) msg.hidden = valid;
          field.setAttribute("aria-invalid", String(!valid));
          if (msg) {
            msg.id = msg.id || "err-" + field.name;
            if (!valid) field.setAttribute("aria-describedby", msg.id);
            else field.removeAttribute("aria-describedby");
          }
          if (!valid && !firstInvalid) firstInvalid = field;
        });
        if (firstInvalid) {
          e.preventDefault();
          firstInvalid.focus();
          return;
        }
        // blokada podwójnego wysłania
        var submit = form.querySelector('[type="submit"]');
        if (submit) {
          submit.disabled = true;
          submit.setAttribute("aria-busy", "true");
        }
      });
    });
  }

  function initHistoryBack() {
    document.querySelectorAll("[data-history-back]").forEach(function (btn) {
      btn.addEventListener("click", function () { history.back(); });
    });
  }

  /* Zamykanie menu konta kliknięciem poza nim lub klawiszem Esc */
  function initAccountMenu() {
    var menu = document.querySelector(".account-menu");
    if (!menu) return;
    document.addEventListener("click", function (e) {
      if (menu.open && !menu.contains(e.target)) menu.open = false;
    });
    document.addEventListener("keydown", function (e) {
      if (e.key === "Escape" && menu.open) {
        menu.open = false;
        menu.querySelector("summary").focus();
      }
    });
  }
})();
