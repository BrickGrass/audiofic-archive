function toggleNsfwContent(show) {
  $('.work-card-nsfw').each(function(i, obj) {
    $(obj).toggleClass("d-none")
  });

  $('.work-card-nsfw-text').each(function(i, obj) {
    $(obj).toggleClass("d-none")
  });
}

function redirectIfNecessary() {
  if ($(".full-work-explicit").length > 0) {
    window.location.href = `${location.protocol}//${location.host}`;
  }
}

window.addEventListener('load', function () {
  let nsfwConsentStatus = Cookies.get("nsfwConsentStatus");

  if (nsfwConsentStatus == null) {
    const nsfwConsentModal = new bootstrap.Modal("#nsfwConsentModal");
    nsfwConsentModal.show();
    nsfwConsentStatus = false;
  } else {
    nsfwConsentStatus = nsfwConsentStatus === "show";
  }

  document.getElementById("nsfwConsentModalShowButton").onclick = function () {
    Cookies.set("nsfwConsentStatus", "show", { expires: 365, samesite: "strict" });
    toggle.checked = true;
  };

  document.getElementById("nsfwConsentModalHideButton").onclick = function () {
    Cookies.set("nsfwConsentStatus", "hide", { expires: 365, samesite: "strict" });
    toggleNsfwContent();
    redirectIfNecessary();
  };

  let toggle = document.getElementById("nsfwConsentToggle");
  toggle.checked = nsfwConsentStatus;

  if (!nsfwConsentStatus) {
    toggleNsfwContent();
    redirectIfNecessary();
  }

  toggle.onchange = function () {
    let value = toggle.checked ? "show" : "hide";
    Cookies.set("nsfwConsentStatus", value, { expires: 365, samesite: "strict" });
    toggleNsfwContent();

    if (value === "hide") {
      redirectIfNecessary();
    }
  }
})
