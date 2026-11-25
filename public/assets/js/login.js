(function () {
  "use strict";

  const loginForm = document.querySelector("#login-form");
  const registerForm = document.querySelector("#register-form");
  const status = document.querySelector("#auth-status");
  const title = document.querySelector("#auth-title");

  function setMode(mode) {
    const registering = mode === "register";
    loginForm.hidden = registering;
    registerForm.hidden = !registering;
    title.textContent = registering ? "Create your account" : "Welcome back";
    document.querySelectorAll("[data-auth-mode]").forEach(function (button) {
      const active = button.dataset.authMode === mode;
      button.classList.toggle("is-active", active);
      button.setAttribute("aria-pressed", String(active));
    });
    status.textContent = "";
  }

  async function submit(event, action, form) {
    event.preventDefault();
    const button = form.querySelector('button[type="submit"]');
    button.disabled = true;
    status.textContent = "Please wait…";
    const body = Object.fromEntries(new FormData(form).entries());
    try {
      const response = await window.UniGuideApi.send("api/auth.php?action=" + action, "POST", body);
      window.location.href = response.data.role === "student" ? "student.html" : "admin.html";
    } catch (error) {
      status.textContent = error.message;
    } finally {
      button.disabled = false;
    }
  }

  document.querySelectorAll("[data-auth-mode]").forEach(function (button) {
    button.addEventListener("click", function () {
      setMode(button.dataset.authMode);
    });
  });
  loginForm.addEventListener("submit", function (event) {
    submit(event, "login", loginForm);
  });
  registerForm.addEventListener("submit", function (event) {
    const fields = new FormData(registerForm);
    if (fields.get("password") !== fields.get("password_confirmation")) {
      event.preventDefault();
      status.textContent = "The password confirmation does not match.";
      return;
    }
    submit(event, "register", registerForm);
  });
  window.UniGuideApi.get("api/auth.php?action=me").then(function (response) {
    window.location.href = response.data.role === "student" ? "student.html" : "admin.html";
  }).catch(function () {});
}());
