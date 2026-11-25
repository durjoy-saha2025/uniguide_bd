(function () {
  "use strict";

  const params = new URLSearchParams(window.location.search);
  const university = (window.UniGuideDemoData || []).find(function (item) {
    return item.id === params.get("id");
  });
  const name = document.querySelector("#profile-name");
  const location = document.querySelector("#profile-location");
  const monogram = document.querySelector("#profile-monogram");
  const subjects = document.querySelector("#profile-subjects");
  const compareLink = document.querySelector("#profile-compare");

  if (!university) {
    name.textContent = "Sample profile not found";
    location.textContent = "Choose a profile from the university directory.";
    monogram.textContent = "?";
    subjects.textContent = "No sample subject data is available.";
    compareLink.href = "universities.html";
    return;
  }

  name.textContent = university.name;
  location.textContent = university.location + " · illustrative location, not verified";
  monogram.textContent = university.monogram;
  university.subject.split(", ").forEach(function (item) {
    const entry = document.createElement("li");
    entry.textContent = item;
    subjects.append(entry);
  });
  compareLink.href = "universities.html";
}());
