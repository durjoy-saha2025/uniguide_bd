(function () {
  "use strict";

  const list = document.querySelector("#university-list");
  const form = document.querySelector("#discovery-search");
  const queryInput = document.querySelector("#query");
  const locationInput = document.querySelector("#location");
  const status = document.querySelector("#search-status");
  const emptyState = document.querySelector("#empty-state");
  const clearButton = document.querySelector("#clear-search");

  function renderUniversities(items) {
    list.replaceChildren();
    items.forEach(function (university) {
      const card = document.createElement("article");
      card.className = "university-card";
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
      const verified = document.createElement("span");
      verified.className = "card-tag";
      verified.textContent = "Verified";
      meta.append(city, verified);
      const heading = document.createElement("h3");
      heading.textContent = university.name;
      const description = document.createElement("p");
      description.textContent = university.summary || "View verified details and official references.";
      const footer = document.createElement("div");
      footer.className = "card-footer";
      const source = document.createElement("a");
      source.href = university.source_url;
      source.target = "_blank";
      source.rel = "noopener noreferrer";
      source.textContent = "Source";
      const action = document.createElement("a");
      action.href = "university.html?slug=" + encodeURIComponent(university.slug);
      action.setAttribute("aria-label", "View verified profile for " + university.name);
      action.append(document.createTextNode("View profile "));
      const arrow = document.createElement("span");
      arrow.setAttribute("aria-hidden", "true");
      arrow.textContent = "→";
      action.append(arrow);
      footer.append(source, action);
      body.append(meta, heading, description, footer);
      card.append(art, body);
      list.append(card);
    });
    emptyState.hidden = items.length > 0;
    list.hidden = items.length === 0;
  }

  function directoryUrl(subject) {
    const params = new URLSearchParams();
    if (queryInput.value.trim()) params.set("q", queryInput.value.trim());
    if (locationInput.value) params.set("city", locationInput.value);
    if (subject) params.set("subject", subject);
    const query = params.toString();
    window.location.href = "universities.html" + (query ? "?" + query : "");
  }

  async function loadFeatured() {
    status.textContent = "Loading verified university listings…";
    list.setAttribute("aria-busy", "true");
    try {
      const result = await window.UniGuideApi.get("api/universities.php?per_page=3");
      renderUniversities(result.data);
      status.textContent = result.meta.total
        ? result.meta.total + " verified universities in the directory."
        : "The verified directory is ready for its first university listing.";
    } catch (error) {
      list.replaceChildren();
      emptyState.hidden = false;
      list.hidden = true;
      emptyState.querySelector("h3").textContent = "University listings are unavailable";
      emptyState.querySelector("p").textContent = error.message;
      status.textContent = "Could not load verified university listings.";
    } finally {
      list.removeAttribute("aria-busy");
    }
  }

  async function loadLatestUpdates() {
    const area = document.querySelector("#latest-updates");
    if (!area) return;
    try {
      const response = await window.UniGuideApi.get("api/catalog.php?resource=news&per_page=3");
      area.replaceChildren();
      if (!response.data.length) {
        area.appendChild(Object.assign(document.createElement("p"), {
          className: "catalog-meta",
          textContent: "No verified announcements are published yet."
        }));
        return;
      }
      response.data.forEach(function (item) {
        const card = document.createElement("article");
        card.className = "catalog-card";
        const category = document.createElement("span");
        category.className = "section-kicker";
        category.textContent = item.category;
        const heading = document.createElement("h3");
        heading.textContent = item.title;
        const summary = document.createElement("p");
        summary.textContent = item.summary || "Open the update for verified details.";
        const source = document.createElement("a");
        source.className = "text-link";
        source.href = "news.html?slug=" + encodeURIComponent(item.slug);
        source.textContent = "Read source-linked update →";
        card.append(category, heading, summary, source);
        area.append(card);
      });
    } catch (error) {
      area.textContent = "Verified updates are temporarily unavailable: " + error.message;
    }
  }

  form.addEventListener("submit", function (event) {
    event.preventDefault();
    directoryUrl();
  });
  clearButton.addEventListener("click", function () {
    form.reset();
    loadFeatured();
    queryInput.focus();
  });
  document.querySelectorAll("[data-subject]").forEach(function (link) {
    link.addEventListener("click", function () {
      queryInput.value = link.dataset.subject;
      directoryUrl(link.dataset.subject);
    });
  });

  loadFeatured();
  loadLatestUpdates();
}());
