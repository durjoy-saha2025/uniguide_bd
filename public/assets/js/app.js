(function () {
  "use strict";

  const universities = window.UniGuideDemoData || [];
  const list = document.querySelector("#university-list");
  const form = document.querySelector("#discovery-search");
  const queryInput = document.querySelector("#query");
  const locationInput = document.querySelector("#location");
  const status = document.querySelector("#search-status");
  const emptyState = document.querySelector("#empty-state");
  const clearButton = document.querySelector("#clear-search");
  function renderUniversities(items) {
    list.replaceChildren();

    items.forEach(function (university, index) {
      const card = document.createElement("article");
      card.className = "university-card";

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
      const location = document.createElement("span");
      location.className = "card-location";
      location.textContent = university.location;
      const tag = document.createElement("span");
      tag.className = "card-tag";
      tag.textContent = "Sample listing";
      meta.append(location, tag);

      const heading = document.createElement("h3");
      heading.textContent = university.name;
      const description = document.createElement("p");
      description.textContent = "Example discovery card. Confirm programs and other details with the university.";

      const footer = document.createElement("div");
      footer.className = "card-footer";
      const sample = document.createElement("span");
      sample.textContent = "Demo item " + String(index + 1).padStart(2, "0");
      const action = document.createElement("a");
      action.href = "university.html?id=" + encodeURIComponent(university.id);
      action.setAttribute("aria-label", "View demo profile for " + university.name);
      action.append(document.createTextNode("View demo profile "));
      const arrow = document.createElement("span");
      arrow.setAttribute("aria-hidden", "true");
      arrow.textContent = "→";
      action.append(arrow);
      footer.append(sample, action);

      body.append(meta, heading, description, footer);
      card.append(art, body);
      list.append(card);
    });

    emptyState.hidden = items.length > 0;
    list.hidden = items.length === 0;
  }

  function applySearch(event) {
    if (event) event.preventDefault();
    const query = queryInput.value.trim().toLocaleLowerCase();
    const location = locationInput.value;
    const results = universities.filter(function (university) {
      const matchesQuery = !query || (university.name + " " + university.subject).toLocaleLowerCase().includes(query);
      const matchesLocation = !location || university.location === location;
      return matchesQuery && matchesLocation;
    });
    status.textContent = results.length
      ? "Showing " + results.length + " illustrative sample " + (results.length === 1 ? "option" : "options") + ". Details are not verified."
      : "No illustrative sample options matched. Try a broader search.";
    renderUniversities(results);
  }

  form.addEventListener("submit", applySearch);
  clearButton.addEventListener("click", function () {
    form.reset();
    status.textContent = "Search the illustrative sample below. University details are demo content and need verification.";
    renderUniversities(universities);
    queryInput.focus();
  });

  document.querySelectorAll("[data-subject]").forEach(function (link) {
    link.addEventListener("click", function () {
      queryInput.value = link.dataset.subject;
      applySearch();
      queryInput.focus();
    });
  });

  document.querySelector("#current-year").textContent = String(new Date().getFullYear());
  renderUniversities(universities);
}());
