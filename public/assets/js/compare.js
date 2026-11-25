(function () {
  "use strict";

  const storageKey = "uniguide-compare";
  const empty = document.querySelector("#comparison-empty");
  const content = document.querySelector("#comparison-content");
  const head = document.querySelector("#comparison-head");
  const body = document.querySelector("#comparison-body");
  const status = document.querySelector("#comparison-status");
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

  function saveSelection(ids) {
    const limited = ids.slice(0, 4);
    localStorage.setItem(storageKey, JSON.stringify(limited));
    document.querySelectorAll("[data-compare-count]").forEach(function (badge) {
      badge.textContent = String(limited.length);
    });
    render();
  }

  function appendCell(row, text, isHeader) {
    const cell = document.createElement(isHeader ? "th" : "td");
    cell.textContent = text;
    row.append(cell);
    return cell;
  }

  async function universityData(slug) {
    const response = await window.UniGuideApi.get("api/universities.php?slug=" + encodeURIComponent(slug));
    const university = response.data;
    const programs = (await window.UniGuideApi.get("api/catalog.php?resource=programs&university_id=" + university.id)).data;
    const programDetails = await Promise.all(programs.map(async function (program) {
      const [fees, requirements] = await Promise.all([
        window.UniGuideApi.get("api/catalog.php?resource=program-fees&program_id=" + program.id),
        window.UniGuideApi.get("api/catalog.php?resource=admission-requirements&program_id=" + program.id)
      ]);
      return { program: program, fees: fees.data, requirements: requirements.data };
    }));
    const [scholarships, rankings] = await Promise.all([
      window.UniGuideApi.get("api/catalog.php?resource=scholarships&university_id=" + university.id),
      window.UniGuideApi.get("api/catalog.php?resource=rankings&university_id=" + university.id)
    ]);
    return {
      university: university,
      programs: programDetails,
      scholarships: scholarships.data,
      rankings: rankings.data
    };
  }

  function describe(item, key) {
    const university = item.university;
    if (key === "location") return [university.city, university.address].filter(Boolean).join(", ") || "Not listed";
    if (key === "programs") {
      return item.programs.length
        ? item.programs.map(function (entry) { return entry.program.name; }).join("; ")
        : "No verified programs listed";
    }
    if (key === "fees") {
      const entries = item.programs.flatMap(function (entry) {
        return entry.fees.map(function (fee) {
          return entry.program.name + ": " + fee.label + " " + fee.currency + " " + Number(fee.amount).toLocaleString();
        });
      });
      return entries.length
        ? "Verified listed components only — " + entries.join("; ") + ". Not a complete cost estimate."
        : "No verified fee entries listed.";
    }
    if (key === "requirements") {
      const entries = item.programs.flatMap(function (entry) {
        return entry.requirements.map(function (req) {
          const criteria = [];
          if (req.minimum_ssc_gpa !== null) criteria.push("SSC GPA " + req.minimum_ssc_gpa + "+");
          if (req.minimum_hsc_gpa !== null) criteria.push("HSC GPA " + req.minimum_hsc_gpa + "+");
          if (req.minimum_combined_gpa !== null) criteria.push("combined GPA " + req.minimum_combined_gpa + "+");
          return entry.program.name + ": " + (criteria.join(", ") || "See official requirements");
        });
      });
      return entries.join("; ") || "No verified requirements listed.";
    }
    if (key === "scholarships") {
      return item.scholarships.map(function (award) {
        return award.title + (award.award_value === null ? "" : " (" + award.award_value + (award.award_type === "percentage" ? "%" : "") + ")");
      }).join("; ") || "No verified scholarship listed.";
    }
    if (key === "rankings") {
      return item.rankings.map(function (ranking) {
        return ranking.publisher + " " + ranking.edition + (ranking.rank_label ? ": " + ranking.rank_label : "");
      }).join("; ") || "No verified ranking listed.";
    }
    if (key === "verification") return "Verified · " + (university.ugc_status || "UGC status not listed");
    if (key === "source") return university.source_title + (university.source_publisher ? " · " + university.source_publisher : "");
    return "Not available";
  }

  async function render() {
    const currentRequest = ++requestNumber;
    const selected = readSelection();
    empty.hidden = selected.length >= 2;
    content.hidden = selected.length < 2;
    if (selected.length < 2) {
      status.textContent = "Choose two to four verified universities from the directory.";
      return;
    }
    status.textContent = "Loading verified information…";
    try {
      const compared = await Promise.all(selected.map(universityData));
      if (currentRequest !== requestNumber) return;
      document.querySelector("#comparison-title").textContent = "Comparing " + compared.length + " universities";
      status.textContent = "All displayed facts come from verified published records.";
      head.replaceChildren();
      body.replaceChildren();

      const headerRow = document.createElement("tr");
      appendCell(headerRow, "Details", true);
      compared.forEach(function (item) {
        const university = item.university;
        const heading = document.createElement("th");
        heading.scope = "col";
        const title = document.createElement("strong");
        title.textContent = university.name;
        const profile = document.createElement("a");
        profile.href = "university.html?slug=" + encodeURIComponent(university.slug);
        profile.textContent = "View profile";
        const remove = document.createElement("button");
        remove.type = "button";
        remove.className = "remove-compare";
        remove.textContent = "Remove";
        remove.setAttribute("aria-label", "Remove " + university.name + " from comparison");
        remove.addEventListener("click", function () {
          saveSelection(readSelection().filter(function (slug) { return slug !== university.slug; }));
        });
        heading.append(title, profile, remove);
        headerRow.append(heading);
      });
      head.append(headerRow);

      const rows = [
        ["Location", "location"],
        ["Verified programs", "programs"],
        ["Published fee components", "fees"],
        ["Admission requirements", "requirements"],
        ["Scholarships", "scholarships"],
        ["Rankings and editions", "rankings"],
        ["Verification status", "verification"],
        ["Source reference", "source"]
      ];
      rows.forEach(function (entry) {
        const row = document.createElement("tr");
        appendCell(row, entry[0], true).scope = "row";
        compared.forEach(function (item) {
          appendCell(row, describe(item, entry[1]), false);
        });
        body.append(row);
      });
      const links = document.createElement("tr");
      appendCell(links, "Official links", true).scope = "row";
      compared.forEach(function (item) {
        const cell = document.createElement("td");
        const values = [
          ["Website", item.university.website_url],
          ["Admissions", item.university.admissions_url],
          ["Source", item.university.source_url]
        ].filter(function (entry) { return Boolean(entry[1]); });
        if (values.length) {
          values.forEach(function (entry) {
            const link = document.createElement("a");
            link.href = entry[1];
            link.target = "_blank";
            link.rel = "noopener noreferrer";
            link.textContent = entry[0];
            cell.append(link, document.createElement("br"));
          });
        } else {
          cell.textContent = "No links listed";
        }
        links.append(cell);
      });
      body.append(links);
    } catch (error) {
      if (currentRequest !== requestNumber) return;
      status.textContent = "Could not load comparison data: " + error.message;
    }
  }

  document.querySelector("#clear-comparison").addEventListener("click", function () {
    if (window.confirm("Remove all universities from your comparison?")) saveSelection([]);
  });
  render();
}());
