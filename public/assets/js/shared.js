(function () {
  "use strict";

  const menuToggle = document.querySelector(".menu-toggle");
  const nav = document.querySelector("#primary-nav");
  const count = document.querySelector("[data-compare-count]");

  function getComparedIds() {
    return JSON.parse(localStorage.getItem("uniguide-compare") || "[]");
  }

  if (menuToggle && nav) {
    menuToggle.addEventListener("click", function () {
      const isOpen = menuToggle.getAttribute("aria-expanded") === "true";
      menuToggle.setAttribute("aria-expanded", String(!isOpen));
      nav.classList.toggle("is-open", !isOpen);
    });
    nav.addEventListener("click", function (event) {
      if (event.target.closest("a")) {
        menuToggle.setAttribute("aria-expanded", "false");
        nav.classList.remove("is-open");
      }
    });
  }

  if (count) count.textContent = String(getComparedIds().length);
  const year = document.querySelector("#current-year");
  if (year) year.textContent = String(new Date().getFullYear());
}());
