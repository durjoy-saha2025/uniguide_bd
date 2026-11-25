(function () {
  "use strict";

  const list = document.querySelector("#directory-list");
  const form = document.querySelector("#directory-filters");
  const query = document.querySelector("#directory-query");
  const location = document.querySelector("#directory-location");
  const subject = document.querySelector("#directory-subject");
  const sort = document.querySelector("#directory-sort");
  const status = document.querySelector("#directory-status");
  const empty = document.querySelector("#directory-empty");
  const errorState = document.querySelector("#directory-error");
  const selectionCount = document.querySelector("#selection-count");
  const compareLink = document.querySelector("#compare-selected");
  const pagination = document.querySelector("#directory-pagination");
  const storageKey = "uniguide-compare";
  let page = 1;
  let totalPages = 0;
  let requestNumber = 0;

  function readSelection() {
    try {
      const selected = JSON.parse(localStorage.getItem(storageKey) || "[]");
      return Array.isArray(selected) ? selected.filter(function (slug) {
        return typeof slug === "string";
      }).slice(0, 4) : [];
    } catch (_error) {
      return [];
    }
  }

  function writeSelection(ids) {
    const limited = ids.slice(0, 4);
    localStorage.setItem(storageKey, JSON.stringify(limited));
    document.querySelectorAll("[data-compare-count]").forEach(function (badge) {
      badge.textContent = String(limited.length);
    });
    selectionCount.textContent = limited.length + " selected";
    const canCompare = limited.length >= 2 && limited.length <= 4;
    compareLink.classList.toggle("is-disabled", !canCompare);
    compareLink.setAttribute("aria-disabled", String(!canCompare));
  }

  function makeCard(university, selectedIds) {
    const card = document.createElement("article");
    card.className = "university-card directory-card";
    const art = document.createElement("div");
    art.className = "card-art";
    art.setAttribute("aria-hidden", "true");
    const monogram = document.createElement("span");
    monogram.className = "card-monogram";
    monogram.textContent = university.name.split(/\s+/).slice(0, 2).map(function (part) {
      return part.charAt(0);
    }).join("").toUpperCase();
    art.append(monogram);

    const body = document.createElement("div");
    body.className = "card-body";
    const meta = document.createElement("div");
    meta.className = "card-meta";
    const city = document.createElement("span");
    city.className = "card-location";
    city.textContent = university.city || "Location not listed";
    const badge = document.createElement("span");
    badge.className = "card-tag";
    badge.textContent = university.ugc_status === "verified" ? "UGC verified" : "Verified record";
    meta.append(city, badge);
    const heading = document.createElement("h2");
    heading.textContent = university.name;
    const description = document.createElement("p");
    description.textContent = university.summary || "Open the profile for programs and verified references.";

    const actions = document.createElement("div");
    actions.className = "directory-card-actions";
    const profileLink = document.createElement("a");
    profileLink.className = "text-link";
    profileLink.href = "university.html?slug=" + encodeURIComponent(university.slug);
    profileLink.textContent = "View profile →";
    const compareLabel = document.createElement("label");
    compareLabel.className = "compare-checkbox";
    const checkbox = document.createElement("input");
    checkbox.type = "checkbox";
    checkbox.value = university.slug;
    checkbox.checked = selectedIds.includes(university.slug);
    checkbox.setAttribute("aria-label", "Select " + university.name + " to compare");
    checkbox.addEventListener("change", function () {
      const current = readSelection();
      const next = checkbox.checked
        ? current.concat(university.slug)
        : current.filter(function (slug) { return slug !== university.slug; });
      if (next.length > 4) {
        checkbox.checked = false;
        status.textContent = "A comparison can include at most four universities.";
        return;
      }
      writeSelection(next);
    });
    const compareText = document.createElement("span");
    compareText.textContent = "Compare";
    compareLabel.append(checkbox, compareText);
    const saveButton = document.createElement("button");
    saveButton.type = "button";
    saveButton.className = "text-button";
    saveButton.textContent = "Save";
    saveButton.setAttribute("aria-label", "Save " + university.name + " to your student account");
    saveButton.addEventListener("click", async function () {
      saveButton.disabled = true;
      try {
        await window.UniGuideApi.send("api/student/discovery.php?resource=saved", "POST", {
          university_id: Number(university.id)
        });
        saveButton.textContent = "Saved";
        status.textContent = university.name + " was saved to your student account.";
      } catch (error) {
        saveButton.disabled = false;
        if (error.status === 401 || error.status === 403) {
          saveButton.textContent = "Sign in to save";
          status.textContent = "Sign in to your student account before saving universities.";
        } else {
          status.textContent = error.message;
        }
      }
    });
    actions.append(profileLink, compareLabel, saveButton);
    body.append(meta, heading, description, actions);
    card.append(art, body);
    return card;
  }

  function updatePagination() {
    const previous = pagination.querySelector("[data-page=previous]");
    const next = pagination.querySelector("[data-page=next]");
    const label = pagination.querySelector("[data-page-label]");
    label.textContent = totalPages ? "Page " + page + " of " + totalPages : "";
    previous.disabled = page <= 1;
    next.disabled = page >= totalPages;
    pagination.hidden = totalPages <= 1;
  }

  async function load() {
    const currentRequest = ++requestNumber;
    const params = new URLSearchParams({ page: String(page), per_page: "12" });
    if (query.value.trim()) params.set("q", query.value.trim());
    if (location.value) params.set("city", location.value);
    if (subject.value) params.set("subject", subject.value);
    status.textContent = "Loading verified universities…";
    errorState.hidden = true;
    list.setAttribute("aria-busy", "true");
    try {
      const result = await window.UniGuideApi.get("api/universities.php?" + params.toString());
      if (currentRequest !== requestNumber) return;
      let items = result.data.slice();
      if (sort.value === "name-desc") items.reverse();
      list.replaceChildren();
      const selectedIds = readSelection();
      items.forEach(function (university) {
        list.append(makeCard(university, selectedIds));
      });
      totalPages = result.meta.total_pages;
      empty.hidden = result.meta.total > 0;
      list.hidden = result.meta.total === 0;
      empty.querySelector("h2").textContent = "No verified universities found";
      empty.querySelector("p").textContent = "Try another subject or location, or check back when more verified records are added.";
      status.textContent = result.meta.total
        ? "Showing " + (result.meta.total ? (page - 1) * result.meta.per_page + 1 : 0)
          + "–" + Math.min(page * result.meta.per_page, result.meta.total)
          + " of " + result.meta.total + " verified universities."
        : "No verified listings match these filters.";
      updatePagination();
    } catch (error) {
      if (currentRequest !== requestNumber) return;
      list.replaceChildren();
      list.hidden = true;
      empty.hidden = true;
      errorState.hidden = false;
      errorState.querySelector("p").textContent = error.message;
      status.textContent = "Could not load university listings.";
    } finally {
      if (currentRequest === requestNumber) list.removeAttribute("aria-busy");
    }
  }

  let searchTimer;
  form.addEventListener("input", function () {
    window.clearTimeout(searchTimer);
    searchTimer = window.setTimeout(function () {
      page = 1;
      load();
    }, 250);
  });
  form.addEventListener("change", function () {
    window.clearTimeout(searchTimer);
    page = 1;
    load();
  });
  document.querySelector("#reset-directory").addEventListener("click", function () {
    form.reset();
    page = 1;
    load();
    query.focus();
  });
  document.querySelectorAll("[data-view]").forEach(function (button) {
    button.addEventListener("click", function () {
      const isList = button.dataset.view === "list";
      list.classList.toggle("is-list", isList);
      document.querySelectorAll("[data-view]").forEach(function (viewButton) {
        const active = viewButton === button;
        viewButton.classList.toggle("is-active", active);
        viewButton.setAttribute("aria-pressed", String(active));
      });
    });
  });
  pagination.addEventListener("click", function (event) {
    const target = event.target.closest("[data-page]");
    if (!target || target.disabled) return;
    page += target.dataset.page === "next" ? 1 : -1;
    load();
  });
  errorState.querySelector("[data-retry]").addEventListener("click", load);
  compareLink.addEventListener("click", function (event) {
    const count = readSelection().length;
    if (count < 2 || count > 4) {
      event.preventDefault();
      status.textContent = "Select between two and four verified universities to compare.";
    }
  });
  document.querySelector("#sync-comparison").addEventListener("click", async function () {
    const selected = readSelection();
    if (selected.length < 2 || selected.length > 4) {
      status.textContent = "Choose two to four universities before saving your shortlist.";
      return;
    }
    const button = document.querySelector("#sync-comparison");
    button.disabled = true;
    status.textContent = "Saving your comparison to your account…";
    try {
      for (const slug of selected) {
        const university = await window.UniGuideApi.get("api/universities.php?slug=" + encodeURIComponent(slug));
        await window.UniGuideApi.send("api/student/discovery.php?resource=comparison", "POST", {
          university_id: Number(university.data.id)
        });
      }
      status.textContent = "Your comparison is saved to your student account.";
    } catch (error) {
      status.textContent = error.status === 401 || error.status === 403
        ? "Sign in to a student account to save your comparison."
        : error.message;
    } finally {
      button.disabled = false;
    }
  });

  const params = new URLSearchParams(window.location.search);
  query.value = params.get("q") || "";
  location.value = params.get("city") || "";
  subject.value = params.get("subject") || "";
  writeSelection(readSelection());
  load();
}());
