(function () {
  "use strict";

  const resource = document.body.dataset.catalogResource;
  const params = new URLSearchParams(window.location.search);
  const slug = params.get("slug");
  const form = document.querySelector("#catalog-filters");
  const query = document.querySelector("#catalog-query");
  const category = document.querySelector("#catalog-category");
  const list = document.querySelector("#catalog-list");
  const article = document.querySelector("#catalog-article");
  const empty = document.querySelector("#catalog-empty");
  const errorState = document.querySelector("#catalog-error");
  const status = document.querySelector("#catalog-status");
  const pagination = document.querySelector("#catalog-pagination");
  const pageSize = 9;
  let page = 1;
  let totalPages = 0;

  function catalogUrl() {
    const request = new URLSearchParams({ resource: resource, page: String(page), per_page: String(pageSize) });
    if (query.value.trim()) request.set("q", query.value.trim());
    if (category.value) request.set("category", category.value);
    if (slug) request.set("slug", slug);
    return "api/catalog.php?" + request.toString();
  }

  function text(tag, className, value) {
    const element = document.createElement(tag);
    if (className) element.className = className;
    element.textContent = value || "";
    return element;
  }

  function renderCards(items) {
    list.replaceChildren();
    items.forEach(function (item) {
      const card = document.createElement("article");
      card.className = "catalog-card";
      const categoryLabel = text("span", "section-kicker", item.category);
      const title = text("h2", "", item.title);
      const summary = text("p", "", item.summary || "Open this item for more information.");
      const date = item.source_published_at ? new Date(item.source_published_at.replace(" ", "T")).toLocaleDateString() : "";
      const meta = text("small", "catalog-meta", date ? "Published " + date : "Published guidance");
      const link = document.createElement("a");
      link.className = "text-link";
      link.href = resource + ".html?slug=" + encodeURIComponent(item.slug);
      link.append(document.createTextNode(resource === "news" ? "Read update " : "Read guide "));
      link.append(text("span", "", "→"));
      card.append(categoryLabel, title, summary, meta, link);
      list.append(card);
    });
    list.hidden = items.length === 0;
  }

  function renderArticle(item) {
    article.replaceChildren();
    const back = document.createElement("a");
    back.className = "back-link";
    back.href = resource + ".html";
    back.textContent = "← All " + (resource === "news" ? "updates" : "guides");
    const categoryLabel = text("p", "section-kicker", item.category);
    const heading = text("h1", "", item.title);
    const summary = text("p", "catalog-article-summary", item.summary);
    const date = item.source_published_at ? new Date(item.source_published_at.replace(" ", "T")).toLocaleDateString() : "";
    const meta = text("p", "catalog-meta", date ? "Published " + date : "Verified guidance");
    const body = text("div", "catalog-article-body", item.body);
    article.append(back, categoryLabel, heading, summary, meta, body);
    if (resource === "news" && item.source_url) {
      const source = document.createElement("a");
      source.href = item.source_url;
      source.target = "_blank";
      source.rel = "noopener noreferrer";
      source.className = "text-link";
      source.textContent = "View original source: " + (item.source_title || "official reference");
      article.append(source);
    }
    article.hidden = false;
    list.hidden = true;
    form.hidden = true;
    pagination.hidden = true;
    document.title = (item.seo_title || item.title) + " | UniGuide BD";
    const description = document.querySelector('meta[name="description"]');
    if (item.seo_description || item.summary) description.content = item.seo_description || item.summary;
    status.textContent = resource === "news" ? "Verified source-linked update." : "Published student guide.";
  }

  function setCategories(items) {
    const selected = category.value;
    const values = Array.from(new Set(items.map(function (item) { return item.category; }).filter(Boolean))).sort();
    category.replaceChildren();
    const allOption = document.createElement("option");
    allOption.value = "";
    allOption.textContent = "All categories";
    category.append(allOption);
    values.forEach(function (value) {
      const option = document.createElement("option");
      option.value = value;
      option.textContent = value;
      category.append(option);
    });
    if (values.includes(selected)) category.value = selected;
  }

  async function load() {
    status.textContent = resource === "news" ? "Loading verified updates…" : "Loading published guides…";
    empty.hidden = true;
    errorState.hidden = true;
    list.setAttribute("aria-busy", "true");
    try {
      const response = await window.UniGuideApi.get(catalogUrl());
      if (slug) {
        const found = response.data.find(function (item) { return item.slug === slug; });
        if (!found) throw new Error("This published item could not be found.");
        renderArticle(found);
        return;
      }
      renderCards(response.data);
      setCategories(response.data);
      totalPages = response.meta.total_pages;
      pagination.hidden = totalPages <= 1;
      pagination.querySelector("[data-page-label]").textContent = totalPages ? "Page " + page + " of " + totalPages : "";
      pagination.querySelector("[data-page=previous]").disabled = page <= 1;
      pagination.querySelector("[data-page=next]").disabled = page >= totalPages;
      empty.hidden = response.data.length > 0;
      status.textContent = response.meta.total + (resource === "news" ? " verified update(s)." : " published guide(s).");
    } catch (exception) {
      if (slug) {
        article.replaceChildren(text("h1", "", "This item is unavailable"), text("p", "", exception.message));
        article.hidden = false;
        form.hidden = true;
      } else {
        errorState.querySelector("p").textContent = exception.message;
        errorState.hidden = false;
        list.hidden = true;
        pagination.hidden = true;
      }
      status.textContent = "Could not load " + (resource === "news" ? "updates." : "guides.");
    } finally {
      list.removeAttribute("aria-busy");
    }
  }

  form.addEventListener("submit", function (event) {
    event.preventDefault();
    page = 1;
    load();
  });
  category.addEventListener("change", function () {
    page = 1;
    load();
  });
  pagination.addEventListener("click", function (event) {
    const control = event.target.closest("[data-page]");
    if (!control || control.disabled) return;
    page += control.dataset.page === "next" ? 1 : -1;
    load();
  });
  errorState.querySelector("[data-retry]").addEventListener("click", load);
  load();
}());
