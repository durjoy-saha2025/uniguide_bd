(function () {
  "use strict";

  const params = new URLSearchParams(window.location.search);
  const slug = params.get("slug");
  const name = document.querySelector("#profile-name");
  const location = document.querySelector("#profile-location");
  const monogram = document.querySelector("#profile-monogram");
  const status = document.querySelector("#profile-status");
  const summary = document.querySelector("#profile-summary");
  const source = document.querySelector("#profile-source");
  const website = document.querySelector("#profile-website");
  const admissions = document.querySelector("#profile-admissions");
  const compareLink = document.querySelector("#profile-compare");

  compareLink.addEventListener("click", function (event) {
    const universitySlug = compareLink.dataset.slug;
    if (!universitySlug) {
      event.preventDefault();
      return;
    }
    let selected;
    try {
      selected = JSON.parse(localStorage.getItem("uniguide-compare") || "[]");
      if (!Array.isArray(selected)) selected = [];
    } catch (_error) {
      selected = [];
    }
    if (!selected.includes(universitySlug)) selected.push(universitySlug);
    if (selected.length > 4) {
      event.preventDefault();
      window.alert("A comparison can include at most four universities.");
      return;
    }
    localStorage.setItem("uniguide-compare", JSON.stringify(selected));
  });

  function setList(element, entries, emptyMessage, renderEntry) {
    element.replaceChildren();
    if (!entries.length) {
      const empty = document.createElement("li");
      empty.textContent = emptyMessage;
      element.append(empty);
      return;
    }
    entries.forEach(function (entry) {
      const item = document.createElement("li");
      item.textContent = renderEntry(entry);
      element.append(item);
    });
  }

  function query(resource, filters) {
    const parameters = new URLSearchParams({ resource: resource });
    Object.keys(filters || {}).forEach(function (key) {
      parameters.set(key, String(filters[key]));
    });
    return window.UniGuideApi.get("api/catalog.php?" + parameters.toString()).then(function (result) {
      return result.data;
    });
  }

  async function load() {
    if (!slug) throw new Error("Choose a university from the verified directory.");
    const result = await window.UniGuideApi.get("api/universities.php?slug=" + encodeURIComponent(slug));
    const university = result.data;
    name.textContent = university.name;
    location.textContent = [university.city, university.address].filter(Boolean).join(" · ") || "Location not listed";
    monogram.textContent = university.name.split(/\s+/).slice(0, 2).map(function (part) {
      return part.charAt(0);
    }).join("").toUpperCase();
    status.textContent = university.ugc_status === "verified" ? "Verified · UGC status verified" : "Verified record";
    summary.textContent = university.description || university.summary || "No overview has been published.";
    document.querySelector("#profile-ugc").textContent = university.ugc_status.replaceAll("_", " ");
    document.querySelector("#profile-verified-date").textContent = university.verified_at || "Not recorded";
    source.href = university.source_url;
    source.textContent = university.source_title + (university.source_publisher ? " · " + university.source_publisher : "");
    source.hidden = false;
    source.rel = "noopener noreferrer";
    source.target = "_blank";
    website.hidden = !university.website_url;
    admissions.hidden = !university.admissions_url;
    if (university.website_url) website.href = university.website_url;
    if (university.admissions_url) admissions.href = university.admissions_url;
    compareLink.dataset.slug = university.slug;

    const programs = await query("programs", { university_id: university.id });
    setList(document.querySelector("#profile-subjects"), programs, "No verified programs are listed.", function (program) {
      const details = [program.degree_level, program.field_of_study, program.duration_months ? program.duration_months + " months" : null].filter(Boolean);
      return program.name + (details.length ? " — " + details.join(" · ") : "");
    });
    const programDetails = await Promise.all(programs.map(async function (program) {
      const [fees, requirements] = await Promise.all([
        query("program-fees", { program_id: program.id }),
        query("admission-requirements", { program_id: program.id })
      ]);
      return { program: program, fees: fees, requirements: requirements };
    }));
    const feeEntries = [];
    const requirementEntries = [];
    programDetails.forEach(function (details) {
      details.fees.forEach(function (fee) {
        feeEntries.push(details.program.name + " — " + fee.label + ": " + fee.currency + " " + Number(fee.amount).toLocaleString());
      });
      details.requirements.forEach(function (requirement) {
        const criteria = [];
        if (requirement.minimum_ssc_gpa !== null) criteria.push("SSC GPA " + requirement.minimum_ssc_gpa + "+");
        if (requirement.minimum_hsc_gpa !== null) criteria.push("HSC GPA " + requirement.minimum_hsc_gpa + "+");
        if (requirement.minimum_combined_gpa !== null) criteria.push("combined GPA " + requirement.minimum_combined_gpa + "+");
        if (requirement.accepted_hsc_groups) criteria.push("HSC groups: " + requirement.accepted_hsc_groups.join(", "));
        if (requirement.admission_test_required) criteria.push("admission test required");
        requirementEntries.push(details.program.name + (criteria.length ? " — " + criteria.join("; ") : " — check official details"));
      });
    });
    setList(document.querySelector("#profile-fees"), feeEntries, "No verified fee entries are available.", function (item) { return item; });
    setList(document.querySelector("#profile-requirements"), requirementEntries, "No verified requirements are available.", function (item) { return item; });

    const [scholarships, rankings] = await Promise.all([
      query("scholarships", { university_id: university.id }),
      query("rankings", { university_id: university.id })
    ]);
    setList(document.querySelector("#profile-scholarships"), scholarships, "No verified scholarship records are available.", function (item) {
      const award = item.award_value === null ? "" : " — " + item.award_value + (item.award_type === "percentage" ? "%" : "");
      return item.title + award;
    });
    setList(document.querySelector("#profile-rankings"), rankings, "No verified ranking records are available.", function (item) {
      return item.publisher + " · " + item.edition + (item.rank_label ? " · " + item.rank_label : "");
    });
  }

  load().catch(function (error) {
    name.textContent = "University profile unavailable";
    location.textContent = error.message;
    status.textContent = "No verified profile loaded";
    document.querySelectorAll("[data-profile-section]").forEach(function (section) {
      section.hidden = true;
    });
  });
}());
