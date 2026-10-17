/**
 * PGM – kreator nagród: pola zależne od rodzaju nagrody, podgląd na żywo,
 * odsłanianie kodów i potwierdzenia usuwania. Formularz działa też bez JS.
 */
(function () {
  "use strict";

  document.addEventListener("DOMContentLoaded", function () {
    initConfirmForms();
    initCodeReveal();

    var form = document.getElementById("reward-form");
    if (!form) return;

    var card = document.querySelector("[data-preview-card]");
    var valueLabel = form.querySelector("[data-value-label]");

    function selected(name) {
      var el = form.querySelector('input[name="' + name + '"]:checked');
      return el ? el.value : null;
    }
    function field(name) {
      var el = form.querySelector('[name="' + name + '"]');
      return el ? el.value.trim() : "";
    }

    function formatMoney(num) {
      var fixed = Math.round(num * 100) / 100;
      var s = fixed % 1 === 0 ? String(fixed) : fixed.toFixed(2);
      return s.replace(".", ",").replace(/\B(?=(\d{3})+(?!\d))/g, " ");
    }

    function update() {
      var type = selected("reward_type") || "discount_code";
      var kind = selected("discount_kind") || "percent";
      var isGift = type === "gift_card";

      // pola tylko dla kodów rabatowych
      form.querySelectorAll("[data-only-for]").forEach(function (el) {
        el.hidden = el.getAttribute("data-only-for") !== type;
      });

      if (valueLabel) {
        valueLabel.firstChild.textContent = isGift
          ? "Wartość karty (zł) "
          : (kind === "percent" ? "Wysokość rabatu (%) " : "Kwota rabatu (zł) ");
      }

      if (!card) return;
      card.classList.toggle("reward-card--gift", isGift);
      card.classList.toggle("reward-card--discount", !isGift);
      set("type", isGift ? "Karta podarunkowa" : "Kod rabatowy");

      var raw = parseFloat(field("value_amount").replace(/\s/g, "").replace(",", "."));
      var value = "—";
      if (isFinite(raw) && raw > 0) {
        if (isGift) value = formatMoney(raw) + " zł";
        else value = kind === "percent" ? "−" + formatMoney(raw) + "%" : "−" + formatMoney(raw) + " zł";
      }
      set("value", value);
      set("partner_name", field("partner_name") || "Nazwa sklepu");
      set("title", field("title") || "Nazwa nagrody");
      set("cost_points", field("cost_points") || "0");

      var until = field("valid_until");
      if (until) {
        var p = until.split("-");
        set("valid_until", "Ważna do " + p[2] + "." + p[1] + "." + p[0]);
      } else {
        set("valid_until", "Bez terminu ważności");
      }
    }

    function set(key, text) {
      var el = card.querySelector('[data-preview="' + key + '"]');
      if (el) el.textContent = text;
    }

    // Po poprawieniu pola chowamy jego komunikat błędu (serwer i tak sprawdzi wszystko ponownie)
    form.addEventListener("input", function (e) {
      var wrap = e.target.closest(".field.has-error");
      if (!wrap) return;
      wrap.classList.remove("has-error");
      e.target.removeAttribute("aria-invalid");
      var msg = wrap.querySelector(".field__error");
      if (msg) msg.hidden = true;
    });

    form.addEventListener("input", update);
    form.addEventListener("change", update);
    update();
  });

  /** Formularze z atrybutem data-confirm pytają o potwierdzenie przed wysłaniem. */
  function initConfirmForms() {
    document.querySelectorAll("form[data-confirm]").forEach(function (form) {
      form.addEventListener("submit", function (e) {
        if (!window.confirm(form.getAttribute("data-confirm"))) e.preventDefault();
      });
    });
  }

  /** „Pokaż / Ukryj” przy zamaskowanym kodzie. */
  function initCodeReveal() {
    document.querySelectorAll("[data-reveal]").forEach(function (btn) {
      var code = btn.previousElementSibling;
      if (!code) return;
      var masked = code.textContent;
      btn.setAttribute("aria-pressed", "false");
      btn.addEventListener("click", function () {
        var show = btn.getAttribute("aria-pressed") !== "true";
        code.textContent = show ? code.getAttribute("data-code") : masked;
        btn.textContent = show ? "Ukryj" : "Pokaż";
        btn.setAttribute("aria-pressed", String(show));
      });
    });
  }
})();
