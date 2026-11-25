(function () {
  "use strict";

  class ApiError extends Error {
    constructor(message, status, code) {
      super(message);
      this.name = "ApiError";
      this.status = status;
      this.code = code;
    }
  }

  async function request(path, options) {
    const settings = options || {};
    const method = settings.method || "GET";
    const headers = { Accept: "application/json" };
    if (settings.body !== undefined) headers["Content-Type"] = "application/json";
    if (method !== "GET") {
      const csrf = await fetch("api/csrf.php", { credentials: "same-origin" });
      const csrfPayload = await csrf.json();
      if (!csrf.ok) throw new ApiError("Could not start a secure request.", csrf.status, "csrf_unavailable");
      headers["X-CSRF-Token"] = csrfPayload.data.token;
    }

    const response = await fetch(path, {
      method: method,
      credentials: "same-origin",
      headers: headers,
      body: settings.body === undefined ? undefined : JSON.stringify(settings.body)
    });
    const payload = await response.json();
    if (!response.ok) {
      throw new ApiError(
        payload.error ? payload.error.message : "The request could not be completed.",
        response.status,
        payload.error ? payload.error.code : "request_failed"
      );
    }
    return payload;
  }

  window.UniGuideApi = {
    get: function (path) {
      return request(path);
    },
    send: function (path, method, body) {
      return request(path, { method: method, body: body });
    },
    ApiError: ApiError
  };
}());
