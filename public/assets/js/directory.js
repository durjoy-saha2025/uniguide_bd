(function () {
  "use strict";

  const universities = window.UniGuideDemoData || [];
  const list = document.querySelector("#directory-list");
  const form = document.querySelector("#directory-filters");
  const query = document.querySelector("#directory-query");
  const location = document.querySelector("#directory-location");
  const subject = document.querySelector("#directory-subject");
  const sort = document.querySelector("#directory-sort");
  const status = document.querySelector("#directory-status");
  const empty = document.querySelector("#directory-empty");
  const selectionCount = document.querySelector("#selection-count");
  const compareLink = document.querySelector("#compare-selected");
  const storageKey = "uniguide-compare";

  function readSelection() {
    return JSON.parse(localStorage.getItem(storageKey) || "[]");
  }

  function writeSelection(ids) {
    localStorage.setItem(storageKey, JSON.stringify(ids));
    document.querySelectorAll("[data-compare-count]").forEach(function (badge) {
      badge.textContent = String(ids.length);
    });
    selectionCount.textContent = ids.length + " selected";
    const canCompare = ids.length >= 2 && ids.length <= 4;
    compareLink.classList.toggle("is-disabled", !canCompare);
    compareLink.setAttribute("aria-disabled", String(!canCompare));
  }

  function makeCard(university, selectedIds) {
    const card = document.createElement("article");
    card.className = "university-card directory-card";

    const art = document.createElement("div");
    art.className = "card-art " + university.accent;
    art.setAttribute("aria-hidden", "true");
    const monogram = document.createElement("span");
    monogram.className = "card-monogram";
    monogram.textContent = university.monogram;
    const illustration = document.createElement("span");
    illustration.className = "card-art-artifact";
    const demoLabel = document.createElement("span");
    demoLabel.className = "card-demo-label";
    demoLabel.textContent = "Illustrative demo";
    art.append(monogram, illustration, demoLabel);

    const body = document.createElement("div");
    body.className = "card-body";
    const meta = document.createElement("div");
    meta.className = "card-meta";
    const locationLabel = document.createElement("span");
    locationLabel.className = "card-location";
    locationLabel.textContent = university.location + " · sample";
    const tag = document.createElement("span");
    tag.className = "card-tag";
    tag.textContent = "Not verified";
    meta.append(locationLabel, tag);
    const heading = document.createElement("h2");
    heading.textContent = university.name;
    const description = document.createElement("p");
    description.textContent = "Demo subject examples: " + university.subject + ". Confirm programs and details with the university.";

    const actions = document.createElement("div");
    actions.className = "directory-card-actions";
    const profileLink = document.createElement("a");
    profileLink.className = "text-link";
    profileLink.href = "university.html?id=" + encodeURIComponent(university.id);
    profileLink.textContent = "View sample profile →";
    const compareLabel = document.createElement("label");
    compareLabel.className = "compare-checkbox";
    const checkbox = document.createElement("input");
    checkbox.type = "checkbox";
    checkbox.value = university.id;
    checkbox.checked = selectedIds.includes(university.id);
    checkbox.setAttribute("aria-label", "Select " + university.name + " to compare");
    checkbox.addEventListener("change", function () {
      const current = readSelection();
      const next = checkbox.checked
        ? current.concat(university.id).slice(0, 4)
        : current.filter(function (id) { return id !== university.id; });
      writeSelection(next);
      if (checkbox.checked && !next.includes(university.id)) checkbox.checked = false;
    });
    const compareText = document.createElement("span");
    compareText.textContent = "Compare";
    compareLabel.append(checkbox, compareText);
    actions.append(profileLink, compareLabel);
    body.append(meta, heading, description, actions);
    card.append(art, body);
    return card;
  }

  function render() {
    const text = query.value.trim().toLocaleLowerCase();
    const chosenLocation = location.value;
    const chosenSubject = subject.value.toLocaleLowerCase();
    let results = universities.filter(function (university) {
      const searchable = (university.name + " " + university.subject).toLocaleLowerCase();
      return (!text || searchable.includes(text))
        && (!chosenLocation || university.location === chosenLocation)
        && (!chosenSubject || university.subject.toLocaleLowerCase().includes(chosenSubject));
    });
    results = results.slice().sort(function (left, right) {
      return sort.value === "name-desc"
        ? right.name.localeCompare(left.name)
        : left.name.localeCompare(right.name);
    });

    list.replaceChildren();
    const selectedIds = readSelection();
    results.forEach(function (university) {
      list.append(makeCard(university, selectedIds));
    });
    list.hidden = results.length === 0;
    empty.hidden = results.length > 0;
    status.textContent = "Showing " + results.length + " of " + universities.length + " illustrative sample listings.";
  }

  form.addEventListener("input", render);
  form.addEventListener("change", render);
  document.querySelector("#reset-directory").addEventListener("click", function () {
    form.reset();
    render();
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
  compareLink.addEventListener("click", function (event) {
    const count = readSelection().length;
    if (count < 2 || count > 4) {
      event.preventDefault();
      status.textContent = "Select between two and four sample listings to compare.";
    }
  });

  writeSelection(readSelection().filter(function (id) {
    return universities.some(function (university) { return university.id === id; });
  }).slice(0, 4));
  render();
}());
