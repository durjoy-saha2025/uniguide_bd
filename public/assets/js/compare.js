(function () {
  "use strict";

  const storageKey = "uniguide-compare";
  const universities = window.UniGuideDemoData || [];
  const empty = document.querySelector("#comparison-empty");
  const content = document.querySelector("#comparison-content");
  const head = document.querySelector("#comparison-head");
  const body = document.querySelector("#comparison-body");
  const status = document.querySelector("#comparison-status");

  function readSelection() {
    return JSON.parse(localStorage.getItem(storageKey) || "[]");
  }

  function saveSelection(ids) {
    localStorage.setItem(storageKey, JSON.stringify(ids));
    document.querySelectorAll("[data-compare-count]").forEach(function (badge) {
      badge.textContent = String(ids.length);
    });
    render();
  }

  function appendCell(row, text, isHeader) {
    const cell = document.createElement(isHeader ? "th" : "td");
    cell.textContent = text;
    row.append(cell);
    return cell;
  }

  function render() {
    const selected = readSelection().filter(function (id) {
      return universities.some(function (university) { return university.id === id; });
    }).slice(0, 4);
    const compared = selected.map(function (id) {
      return universities.find(function (university) { return university.id === id; });
    });
    if (selected.length !== readSelection().length) localStorage.setItem(storageKey, JSON.stringify(selected));

    empty.hidden = compared.length >= 2;
    content.hidden = compared.length < 2;
    if (compared.length < 2) return;

    document.querySelector("#comparison-title").textContent = "Comparing " + compared.length + " universities";
    status.textContent = "Showing demo details for " + compared.length + " sample listings.";
    head.replaceChildren();
    body.replaceChildren();

    const headerRow = document.createElement("tr");
    appendCell(headerRow, "Details", true);
    compared.forEach(function (university) {
      const heading = document.createElement("th");
      heading.scope = "col";
      const title = document.createElement("strong");
      title.textContent = university.name;
      const profile = document.createElement("a");
      profile.href = "university.html?id=" + encodeURIComponent(university.id);
      profile.textContent = "View sample profile";
      const remove = document.createElement("button");
      remove.type = "button";
      remove.className = "remove-compare";
      remove.textContent = "Remove";
      remove.setAttribute("aria-label", "Remove " + university.name + " from comparison");
      remove.addEventListener("click", function () {
        saveSelection(readSelection().filter(function (id) { return id !== university.id; }));
      });
      heading.append(title, profile, remove);
      headerRow.append(heading);
    });
    head.append(headerRow);

    const rows = [
      ["Location", function (item) { return item.location + " (demo data)"; }],
      ["Subject examples", function (item) { return item.subject; }],
      ["Estimated program cost", function () { return "Not provided in this demo"; }],
      ["Tuition breakdown", function () { return "Not provided in this demo"; }],
      ["Admission requirements", function () { return "Not provided in this demo"; }],
      ["Scholarships", function () { return "Not provided in this demo"; }],
      ["Campus facilities", function () { return "Not provided in this demo"; }],
      ["Rankings and editions", function () { return "No verified records provided"; }],
      ["Verification status", function () { return "Not verified"; }],
      ["Official links", function () { return "Not available in this demo"; }]
    ];

    rows.forEach(function (entry) {
      const row = document.createElement("tr");
      appendCell(row, entry[0], true).scope = "row";
      compared.forEach(function (university) {
        appendCell(row, entry[1](university), false);
      });
      body.append(row);
    });
  }

  document.querySelector("#clear-comparison").addEventListener("click", function () {
    if (window.confirm("Remove all universities from your comparison?")) saveSelection([]);
  });
  render();
}());
