(function () {
  "use strict";

  const profileForm = document.querySelector("#student-profile-form");
  const profileStatus = document.querySelector("#profile-status");
  const completionLabel = document.querySelector("#profile-completion");
  const progress = document.querySelector("#profile-progress");

  function listFromApi(resource) {
    return window.UniGuideApi.get("api/student/discovery.php?resource=" + resource).then(function (response) {
      return response.data;
    });
  }

  function showRecords(element, items, resource) {
    element.replaceChildren();
    if (!items.length) {
      const empty = document.createElement("li");
      empty.textContent = "Nothing saved yet.";
      element.append(empty);
      return;
    }
    items.forEach(function (university) {
      const item = document.createElement("li");
      const link = document.createElement("a");
      link.href = "university.html?slug=" + encodeURIComponent(university.slug);
      link.textContent = university.name;
      const remove = document.createElement("button");
      remove.type = "button";
      remove.className = "text-button";
      remove.textContent = "Remove";
      remove.addEventListener("click", async function () {
        remove.disabled = true;
        try {
          await window.UniGuideApi.send("api/student/discovery.php?resource=" + resource, "DELETE", {
            university_id: Number(university.id)
          });
          refreshLists();
        } catch (error) {
          remove.disabled = false;
          window.alert(error.message);
        }
      });
      item.append(link, remove);
      element.append(item);
    });
  }

  async function refreshLists() {
    const [saved, comparison] = await Promise.all([listFromApi("saved"), listFromApi("comparison")]);
    showRecords(document.querySelector("#saved-universities"), saved, "saved");
    showRecords(document.querySelector("#student-comparison"), comparison, "comparison");
  }

  function profilePayload() {
    const formData = new FormData(profileForm);
    const result = {};
    for (const [field, raw] of formData.entries()) {
      if (field === "scholarship_preference") {
        result[field] = raw === "" ? null : raw === "true";
      } else if (["ssc_gpa", "hsc_gpa", "maximum_budget"].includes(field)) {
        result[field] = raw === "" ? null : Number(raw);
      } else if (["ssc_passing_year", "hsc_passing_year"].includes(field)) {
        result[field] = raw === "" ? null : Number.parseInt(raw, 10);
      } else if (field === "equivalent_qualification") {
        result[field] = raw.trim() === "" ? null : JSON.parse(raw);
      } else {
        result[field] = raw;
      }
    }
    return result;
  }

  function fillProfile(profile) {
    if (!profile) return;
    Object.keys(profile).forEach(function (field) {
      const input = profileForm.elements.namedItem(field);
      if (!input) return;
      if (field === "scholarship_preference") {
        input.value = profile[field] === null ? "" : (profile[field] ? "true" : "false");
      } else if (field === "equivalent_qualification") {
        input.value = profile[field] ? JSON.stringify(profile[field], null, 2) : "";
      } else if (profile[field] !== null && profile[field] !== undefined) {
        input.value = profile[field];
      }
    });
    const fields = ["ssc_gpa", "ssc_group", "ssc_passing_year", "hsc_gpa", "hsc_group", "hsc_passing_year", "preferred_subject", "preferred_location"];
    const complete = fields.filter(function (field) {
      return profile[field] !== null && profile[field] !== undefined && profile[field] !== "";
    }).length;
    const percent = Math.round(complete / fields.length * 100);
    progress.value = percent;
    completionLabel.textContent = "Profile completion: " + percent + "%";
  }

  async function loadRecommendations() {
    const area = document.querySelector("#recommendations");
    const response = await window.UniGuideApi.get("api/student/planning.php?action=recommendations");
    document.querySelector("#recommendation-notice").textContent = response.data.notice;
    area.replaceChildren();
    response.data.items.forEach(function (program) {
      const card = document.createElement("article");
      const link = document.createElement("a");
      link.href = "university.html?slug=" + encodeURIComponent(program.university_slug);
      link.textContent = program.name + " · " + program.university_name;
      const reasons = document.createElement("p");
      reasons.textContent = program.match_reasons.join(" · ");
      const eligible = document.createElement("button");
      eligible.type = "button";
      eligible.className = "text-button";
      eligible.textContent = "Check eligibility";
      eligible.addEventListener("click", async function () {
        try {
          const result = await window.UniGuideApi.get("api/student/planning.php?action=eligibility&program_id=" + program.id);
          reasons.textContent = result.data.status.replaceAll("_", " ") + ": " + result.data.reasons.join("; ");
        } catch (error) {
          reasons.textContent = error.message;
        }
      });
      card.append(link, reasons, eligible);
      area.append(card);
    });
    if (!response.data.items.length) {
      const empty = document.createElement("p");
      empty.textContent = "No matching verified programs are available yet.";
      area.append(empty);
    }
  }

  async function loadUpdates() {
    const response = await window.UniGuideApi.get("api/catalog.php?resource=news&per_page=5");
    const area = document.querySelector("#student-updates");
    area.replaceChildren();
    response.data.forEach(function (update) {
      const item = document.createElement("article");
      const heading = document.createElement("h3");
      heading.textContent = update.title;
      const summary = document.createElement("p");
      summary.textContent = update.summary || "Verified update";
      const source = document.createElement("span");
      source.textContent = update.source_publisher || "Verified source";
      item.append(heading, summary, source);
      area.append(item);
    });
    if (!response.data.length) area.textContent = "No verified admission updates are available.";
  }

  async function initialize() {
    const user = await window.UniGuideApi.get("api/auth.php?action=me");
    if (user.data.role !== "student") {
      window.location.href = "admin.html";
      return;
    }
    document.querySelector("#student-name").textContent = user.data.name;
    const [profile] = await Promise.all([
      window.UniGuideApi.get("api/student-profile.php"),
      refreshLists(),
      loadRecommendations(),
      loadUpdates()
    ]);
    fillProfile(profile.data);
  }

  profileForm.addEventListener("submit", async function (event) {
    event.preventDefault();
    profileStatus.textContent = "Saving…";
    try {
      const saved = await window.UniGuideApi.send("api/student-profile.php", "PUT", profilePayload());
      fillProfile(saved.data);
      profileStatus.textContent = "Your profile has been saved.";
      loadRecommendations();
    } catch (error) {
      profileStatus.textContent = error.message;
    }
  });
  document.querySelector("#sign-out").addEventListener("click", async function () {
    try {
      await window.UniGuideApi.send("api/auth.php?action=logout", "POST", {});
      window.location.href = "login.html";
    } catch (error) {
      profileStatus.textContent = error.message;
    }
  });
  document.querySelector("#change-password-form").addEventListener("submit", async function (event) {
    event.preventDefault();
    const data = Object.fromEntries(new FormData(event.currentTarget).entries());
    const accountStatus = document.querySelector("#account-status");
    if (data.new_password !== data.password_confirmation) {
      accountStatus.textContent = "The new password confirmation does not match.";
      return;
    }
    delete data.password_confirmation;
    try {
      await window.UniGuideApi.send("api/auth.php?action=password", "POST", data);
      event.currentTarget.reset();
      accountStatus.textContent = "Your password has been updated.";
    } catch (error) {
      accountStatus.textContent = error.message;
    }
  });
  const deleteDialog = document.querySelector("#delete-account-dialog");
  document.querySelector("#delete-account").addEventListener("click", function () {
    document.querySelector("#delete-account-error").textContent = "";
    document.querySelector("#delete-account-password").value = "";
    deleteDialog.showModal();
  });
  document.querySelector("#cancel-account-delete").addEventListener("click", function () {
    deleteDialog.close();
  });
  document.querySelector("#delete-account-form").addEventListener("submit", async function (event) {
    event.preventDefault();
    const password = document.querySelector("#delete-account-password").value;
    try {
      await window.UniGuideApi.send("api/auth.php?action=delete-account", "DELETE", { password: password });
      window.location.href = "index.html";
    } catch (error) {
      document.querySelector("#delete-account-error").textContent = error.message;
    }
  });

  initialize().catch(function (error) {
    window.location.href = "login.html?message=" + encodeURIComponent(error.message);
  });
}());
