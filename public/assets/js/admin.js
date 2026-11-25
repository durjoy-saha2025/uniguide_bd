(function () {
  "use strict";

  const definitions = {
    universities: {
      endpoint: "api/admin/universities.php",
      title: "Universities",
      search: "q",
      filters: { status: ["draft", "published", "archived"] },
      sortFields: ["name", "city", "status", "verification_status", "created_at", "updated_at", "id"],
      columns: ["name", "city", "verification_status", "status"],
      fields: [
        ["name", "University name", "text", true], ["slug", "URL slug", "slug", true],
        ["summary", "Short summary", "text"], ["description", "Description", "textarea"],
        ["city", "City", "text"], ["address", "Address", "text"], ["campus_type", "Campus type", "text"],
        ["website_url", "Official website URL", "url"], ["admissions_url", "Admissions URL", "url"],
        ["logo_url", "Logo URL", "url"], ["cover_image_url", "Cover image URL", "url"],
        ["source_id", "Verified source ID", "number"], ["ugc_status", "UGC status", "enum", ["unknown", "pending", "verified", "not_verified"]],
        ["verification_status", "Record verification", "enum", ["draft", "pending", "verified", "rejected"]],
        ["status", "Publication status", "enum", ["draft", "published", "archived"]]
      ]
    },
    sources: {
      endpoint: "api/admin/sources.php",
      title: "Sources",
      search: "q",
      filters: { verification_status: ["pending", "verified", "rejected"] },
      sortFields: ["title", "source_type", "publisher", "verification_status", "created_at", "updated_at", "id"],
      columns: ["title", "source_type", "publisher", "verification_status"],
      fields: [
        ["title", "Source title", "text", true], ["source_type", "Source type", "text", true],
        ["url", "Source URL", "url", true], ["publisher", "Publisher", "text"],
        ["notes", "Verification notes", "textarea"],
        ["verification_status", "Verification status", "enum", ["pending", "verified", "rejected"]]
      ]
    },
    programs: {
      endpoint: "api/admin/catalog.php?resource=programs", title: "Programs", search: "q",
      filters: { status: ["draft", "published", "archived"], verification_status: ["draft", "pending", "verified", "rejected"] },
      columns: ["name", "university_id", "field_of_study", "verification_status", "status"],
      fields: [
        ["university_id", "University ID", "number", true], ["slug", "Program slug", "slug", true],
        ["name", "Program name", "text", true], ["degree_level", "Degree level", "text"],
        ["field_of_study", "Field of study", "text"], ["duration_months", "Duration (months)", "number"],
        ["description", "Description", "textarea"], ["source_id", "Verified source ID", "number"],
        ["verification_status", "Verification status", "enum", ["draft", "pending", "verified", "rejected"]],
        ["status", "Publication status", "enum", ["draft", "published", "archived"]]
      ]
    },
    "program-fees": {
      endpoint: "api/admin/catalog.php?resource=program-fees", title: "Program fees", search: "q",
      filters: { verification_status: ["pending", "verified", "rejected"] }, columns: ["label", "program_id", "fee_type", "amount", "verification_status"],
      fields: [
        ["program_id", "Program ID", "number", true], ["source_id", "Verified source ID", "number"],
        ["fee_type", "Fee type", "enum", ["tuition", "admission", "semester", "lab", "other"], true],
        ["label", "Fee label", "text", true], ["amount", "Amount", "decimal", true],
        ["currency", "Currency (ISO code)", "text"], ["effective_from", "Effective from", "date"],
        ["effective_until", "Effective until", "date"], ["verification_status", "Verification status", "enum", ["pending", "verified", "rejected"]]
      ]
    },
    "admission-requirements": {
      endpoint: "api/admin/catalog.php?resource=admission-requirements", title: "Admission requirements", search: "q",
      filters: { verification_status: ["pending", "verified", "rejected"] }, columns: ["program_id", "minimum_ssc_gpa", "minimum_hsc_gpa", "verification_status"],
      fields: [
        ["program_id", "Program ID", "number", true], ["source_id", "Verified source ID", "number"],
        ["minimum_ssc_gpa", "Minimum SSC GPA", "decimal"], ["minimum_hsc_gpa", "Minimum HSC GPA", "decimal"],
        ["minimum_combined_gpa", "Minimum combined GPA", "decimal"], ["accepted_ssc_groups", "Accepted SSC groups (JSON array)", "json"],
        ["accepted_hsc_groups", "Accepted HSC groups (JSON array)", "json"], ["subject_prerequisites", "Subject prerequisites (JSON)", "json"],
        ["admission_test_required", "Admission test required", "checkbox"], ["additional_requirements", "Additional requirements", "textarea"],
        ["verification_status", "Verification status", "enum", ["pending", "verified", "rejected"]]
      ]
    },
    scholarships: {
      endpoint: "api/admin/catalog.php?resource=scholarships", title: "Scholarships", search: "q",
      filters: { status: ["draft", "published", "archived"], verification_status: ["pending", "verified", "rejected"] },
      columns: ["title", "university_id", "award_type", "award_value", "verification_status", "status"],
      fields: [
        ["university_id", "University ID", "number", true], ["program_id", "Program ID", "number"],
        ["source_id", "Verified source ID", "number"], ["title", "Scholarship title", "text", true],
        ["description", "Description", "textarea"], ["award_type", "Award type", "enum", ["percentage", "fixed_amount", "other"], true],
        ["award_value", "Award value", "decimal"], ["eligibility_rules", "Eligibility rules (JSON)", "json"],
        ["conditions", "Conditions", "textarea"], ["verification_status", "Verification status", "enum", ["pending", "verified", "rejected"]],
        ["status", "Publication status", "enum", ["draft", "published", "archived"]]
      ]
    },
    rankings: {
      endpoint: "api/admin/catalog.php?resource=rankings", title: "Rankings", search: "q",
      filters: { verification_status: ["pending", "verified", "rejected"] },
      columns: ["publisher", "edition", "university_id", "rank_label", "verification_status"],
      fields: [
        ["university_id", "University ID", "number", true], ["source_id", "Verified source ID", "number"],
        ["publisher", "Ranking publisher", "text", true], ["edition", "Edition", "text", true],
        ["publication_date", "Publication date", "date"], ["rank_label", "Rank or band", "text"],
        ["verification_status", "Verification status", "enum", ["pending", "verified", "rejected"]]
      ]
    },
    news: {
      endpoint: "api/admin/catalog.php?resource=news", title: "News and updates", search: "q",
      filters: { workflow_status: ["draft", "review", "approved", "published", "archived"], verification_status: ["pending", "verified", "rejected"] },
      columns: ["title", "category", "workflow_status", "verification_status"],
      fields: [
        ["university_id", "University ID (optional)", "number"], ["source_id", "Verified source ID", "number"],
        ["slug", "Article slug", "slug", true], ["title", "Title", "text", true], ["category", "Category", "text", true],
        ["summary", "Summary", "textarea"], ["body", "Article body", "textarea"],
        ["source_published_at", "Source publication date/time", "datetime"],
        ["workflow_status", "Workflow status", "enum", ["draft", "review", "approved", "published", "archived"]],
        ["verification_status", "Verification status", "enum", ["pending", "verified", "rejected"]]
      ]
    },
    guides: {
      endpoint: "api/admin/catalog.php?resource=guides", title: "Student guides", search: "q",
      filters: { status: ["draft", "published", "archived"] }, columns: ["title", "category", "status"],
      fields: [
        ["slug", "Guide slug", "slug", true], ["title", "Title", "text", true], ["category", "Category", "text", true],
        ["summary", "Summary", "textarea"], ["body", "Guide body", "textarea", true],
        ["seo_title", "SEO title", "text"], ["seo_description", "SEO description", "textarea"],
        ["status", "Publication status", "enum", ["draft", "published", "archived"]]
      ]
    }
  };
  const selector = document.querySelector("#admin-resource");
  const search = document.querySelector("#admin-search");
  const filter = document.querySelector("#admin-filter");
  const sort = document.querySelector("#admin-sort");
  const direction = document.querySelector("#admin-direction");
  const tableHead = document.querySelector("#admin-table-head");
  const tableBody = document.querySelector("#admin-table-body");
  const status = document.querySelector("#admin-status");
  const dialog = document.querySelector("#record-dialog");
  const form = document.querySelector("#record-form");
  const fields = document.querySelector("#record-fields");
  let page = 1;
  let totalPages = 0;
  let activeRecord = null;

  function definition() {
    return definitions[selector.value];
  }

  function endpoint(id) {
    const config = definition();
    return config.endpoint + (id ? (config.endpoint.includes("?") ? "&" : "?") + "id=" + id : "");
  }

  function currentListUrl() {
    const config = definition();
    const params = new URLSearchParams({ page: String(page), per_page: "20" });
    if (search.value.trim()) params.set(config.search, search.value.trim());
    if (filter.value) params.set(filter.dataset.field, filter.value);
    if (sort.value) params.set("sort", sort.value);
    params.set("direction", direction.value);
    const base = config.endpoint + (config.endpoint.includes("?") ? "&" : "?");
    return base + params.toString();
  }

  function buildFilter() {
    filter.replaceChildren();
    const all = document.createElement("option");
    all.value = "";
    all.textContent = "All statuses";
    filter.append(all);
    const entries = Object.entries(definition().filters);
    filter.hidden = entries.length === 0;
    if (!entries.length) return;
    filter.dataset.field = entries[0][0];
    entries[0][1].forEach(function (value) {
      const option = document.createElement("option");
      option.value = value;
      option.textContent = value.replaceAll("_", " ");
      filter.append(option);
    });
  }

  function buildSort() {
    sort.replaceChildren();
    const config = definition();
    let options = config.sortFields;
    if (!options) {
      options = config.columns.filter(function (column) {
        return column !== "Actions";
      });
      if (config.endpoint.includes("catalog.php")) options.push("id");
    }
    options.forEach(function (field) {
      const option = document.createElement("option");
      option.value = field;
      option.textContent = field.replaceAll("_", " ");
      sort.append(option);
    });
    sort.value = config.endpoint.includes("catalog.php") ? (options[0] || "id") : "updated_at";
  }

  function renderHeader() {
    const row = document.createElement("tr");
    ["id"].concat(definition().columns, ["Actions"]).forEach(function (column) {
      const heading = document.createElement("th");
      heading.scope = "col";
      heading.textContent = column.replaceAll("_", " ");
      row.append(heading);
    });
    tableHead.replaceChildren(row);
  }

  function renderRows(items) {
    tableBody.replaceChildren();
    items.forEach(function (item) {
      const row = document.createElement("tr");
      ["id"].concat(definition().columns).forEach(function (column) {
        const cell = document.createElement("td");
        cell.textContent = item[column] === null || item[column] === undefined ? "—" : String(item[column]);
        row.append(cell);
      });
      const actions = document.createElement("td");
      [["View", "view"], ["Edit", "edit"], ["Delete", "delete"]].forEach(function (action) {
        const button = document.createElement("button");
        button.type = "button";
        button.className = "text-button";
        button.textContent = action[0];
        button.addEventListener("click", function () {
          if (action[1] === "view" || action[1] === "edit") {
            window.UniGuideApi.get(endpoint(item.id)).then(function (response) {
              if (action[1] === "view") showDetails(response.data);
              else openForm(response.data);
            }).catch(function (error) {
              status.textContent = error.message;
            });
          }
          if (action[1] === "delete") removeRecord(item);
        });
        actions.append(button, document.createTextNode(" "));
      });
      row.append(actions);
      tableBody.append(row);
    });
    if (!items.length) {
      const row = document.createElement("tr");
      const cell = document.createElement("td");
      cell.colSpan = definition().columns.length + 2;
      cell.textContent = "No records found. Add a record or change your filters.";
      row.append(cell);
      tableBody.append(row);
    }
  }

  async function load() {
    status.textContent = "Loading " + definition().title.toLowerCase() + "…";
    renderHeader();
    try {
      const result = await window.UniGuideApi.get(currentListUrl());
      renderRows(result.data);
      totalPages = result.meta.total_pages;
      status.textContent = result.meta.total + " record(s).";
      const nav = document.querySelector("#admin-pagination");
      nav.hidden = totalPages <= 1;
      nav.querySelector("[data-page-label]").textContent = totalPages ? "Page " + page + " of " + totalPages : "";
      nav.querySelector("[data-page=previous]").disabled = page <= 1;
      nav.querySelector("[data-page=next]").disabled = page >= totalPages;
    } catch (error) {
      tableBody.replaceChildren();
      status.textContent = error.message;
    }
  }

  function makeInput(field, labelText, type, required, choices, value) {
    const label = document.createElement("label");
    label.textContent = labelText;
    let input;
    if (type === "enum") {
      input = document.createElement("select");
      const empty = document.createElement("option");
      empty.value = "";
      empty.textContent = "Choose…";
      input.append(empty);
      choices.forEach(function (choice) {
        const option = document.createElement("option");
        option.value = choice;
        option.textContent = choice.replaceAll("_", " ");
        input.append(option);
      });
    } else if (type === "textarea" || type === "json") {
      input = document.createElement("textarea");
      input.rows = type === "json" ? 4 : 5;
      if (type === "json") input.dataset.json = "true";
    } else {
      input = document.createElement("input");
      input.type = type === "slug" ? "text" : (type === "decimal" ? "number" : (type === "datetime" ? "datetime-local" : type));
      if (type === "number" || type === "decimal") input.step = type === "decimal" ? "0.01" : "1";
    }
    input.name = field;
    input.required = Boolean(required);
    if (type === "checkbox") {
      input.checked = value === true || value === 1;
      label.prepend(input);
      label.className = "admin-checkbox-label";
    } else {
      if (value !== null && value !== undefined) {
        if (input.dataset.json) {
          let jsonValue = value;
          if (typeof value === "string") {
            try { jsonValue = JSON.parse(value); } catch (_error) { jsonValue = value; }
          }
          input.value = typeof jsonValue === "string" ? jsonValue : JSON.stringify(jsonValue, null, 2);
        } else if (type === "datetime" && typeof value === "string") {
          input.value = value.replace(" ", "T").slice(0, 16);
        } else {
          input.value = String(value);
        }
      }
      label.append(input);
    }
    return label;
  }

  function openForm(record) {
    activeRecord = record || null;
    fields.replaceChildren();
    document.querySelector("#record-dialog-title").textContent = record ? "Edit " + definition().title.toLowerCase() : "Add " + definition().title.toLowerCase();
    document.querySelector("#record-error").textContent = "";
    definition().fields.forEach(function (field) {
      fields.append(makeInput(field[0], field[1], field[2], field[3], field[4], record ? record[field[0]] : null));
    });
    dialog.showModal();
  }

  function showDetails(record) {
    const details = document.querySelector("#record-details");
    details.replaceChildren();
    Object.keys(record).forEach(function (key) {
      const term = document.createElement("dt");
      term.textContent = key.replaceAll("_", " ");
      const value = document.createElement("dd");
      value.textContent = record[key] === null ? "—" : (typeof record[key] === "object" ? JSON.stringify(record[key]) : String(record[key]));
      details.append(term, value);
    });
    document.querySelector("#details-dialog").showModal();
  }

  function payloadFromForm() {
    const payload = {};
    for (const [field, raw] of new FormData(form).entries()) {
      const input = form.elements.namedItem(field);
      if (input.dataset.json) {
        payload[field] = raw.trim() === "" ? null : JSON.parse(raw);
      } else if (input.type === "checkbox") {
        payload[field] = input.checked;
      } else if (input.type === "number") {
        payload[field] = raw === "" ? null : Number(raw);
      } else if (input.type === "datetime-local") {
        payload[field] = raw === "" ? null : raw.replace("T", " ") + (raw.length === 16 ? ":00" : "");
      } else {
        payload[field] = raw === "" ? null : raw;
      }
    }
    form.querySelectorAll('input[type="checkbox"]').forEach(function (input) {
      payload[input.name] = input.checked;
    });
    return payload;
  }

  async function saveRecord(event) {
    event.preventDefault();
    const error = document.querySelector("#record-error");
    const button = form.querySelector('button[type="submit"]');
    button.disabled = true;
    try {
      const body = payloadFromForm();
      const method = activeRecord ? "PUT" : "POST";
      await window.UniGuideApi.send(endpoint(activeRecord ? activeRecord.id : null), method, body);
      dialog.close();
      status.textContent = "Record saved.";
      await load();
    } catch (exception) {
      error.textContent = exception instanceof SyntaxError ? "Enter valid JSON in JSON fields." : exception.message;
    } finally {
      button.disabled = false;
    }
  }

  async function removeRecord(record) {
    if (!window.confirm("Delete this record? Related data may also be removed.")) return;
    try {
      await window.UniGuideApi.send(endpoint(record.id), "DELETE", {});
      status.textContent = "Record deleted.";
      await load();
    } catch (error) {
      status.textContent = error.message;
    }
  }

  selector.addEventListener("change", function () {
    page = 1;
    filter.value = "";
    buildFilter();
    buildSort();
    load();
  });
  document.querySelector("#run-search").addEventListener("click", function () {
    page = 1;
    load();
  });
  search.addEventListener("keydown", function (event) {
    if (event.key === "Enter") {
      event.preventDefault();
      page = 1;
      load();
    }
  });
  filter.addEventListener("change", function () {
    page = 1;
    load();
  });
  sort.addEventListener("change", function () {
    page = 1;
    load();
  });
  direction.addEventListener("change", function () {
    page = 1;
    load();
  });
  document.querySelector("#add-record").addEventListener("click", function () {
    openForm(null);
  });
  form.addEventListener("submit", saveRecord);
  document.querySelectorAll("[data-close-dialog]").forEach(function (button) {
    button.addEventListener("click", function () {
      button.closest("dialog").close();
    });
  });
  document.querySelector("#admin-pagination").addEventListener("click", function (event) {
    const control = event.target.closest("[data-page]");
    if (!control || control.disabled) return;
    page += control.dataset.page === "next" ? 1 : -1;
    load();
  });
  document.querySelector("#admin-sign-out").addEventListener("click", async function () {
    try {
      await window.UniGuideApi.send("api/auth.php?action=logout", "POST", {});
      window.location.href = "login.html";
    } catch (error) {
      status.textContent = error.message;
    }
  });

  window.UniGuideApi.get("api/auth.php?action=me").then(function (response) {
    if (!["admin", "super_admin"].includes(response.data.role)) {
      window.location.href = "login.html";
      return;
    }
    buildFilter();
    buildSort();
    load();
  }).catch(function () {
    window.location.href = "login.html";
  });
}());
