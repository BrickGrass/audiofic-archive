function toggleNsfwContent() {
  $('.work-card-nsfw').each(function(i, obj) {
    $(obj).toggleClass("d-none")
  });

  $('.work-card-nsfw-text').each(function(i, obj) {
    $(obj).toggleClass("d-none")
  });

  $('.full-work-player-nsfw').each(function(i, obj) {
    $(obj).toggleClass("d-none")
  });

  $('.full-work-player-all-nsfw').each(function(i, obj) {
    $(obj).toggleClass("d-none")
  });
}

function isWorkOrLegacyWork() {
  return $(".full-work-work").length > 0 || $(".full-work-legacy-work").length > 0;
}

function redirectIfNecessary() {
  if ($(".full-work-explicit").length > 0 && isWorkOrLegacyWork()) {
    window.location.href = `${location.protocol}//${location.host}`;
  }
}

function setToggleToShow() {
  let toggle = document.getElementById("nsfwConsentToggle");
  toggle.checked = true;
}

function showModal() {
  const nsfwConsentModal = new bootstrap.Modal("#nsfwConsentModal");
  nsfwConsentModal.show();

  $("#nsfwConsentModalShowButton").on("click", function() {
    Cookies.set("nsfwConsentStatus", "show", { expires: 365, samesite: "strict" });
    setToggleToShow();
  })

  $("#nsfwConsentModalHideButton").on("click", function() {
    Cookies.set("nsfwConsentStatus", "hide", { expires: 365, samesite: "strict" });
    toggleNsfwContent();
    redirectIfNecessary();
  })
}

$( document ).ready(function() {
  let showNsfw = Cookies.get("nsfwConsentStatus");

  // == so that undefined and null are caught
  if (showNsfw == null) {
    showModal();
  } else {
    showNsfw = showNsfw === "show";
  }

  if (!showNsfw) {
    toggleNsfwContent();
    redirectIfNecessary();
  }

  let toggle = document.getElementById("nsfwConsentToggle");
  toggle.checked = showNsfw;

  toggle.onchange = function () {
    let value = toggle.checked ? "show" : "hide";
    Cookies.set("nsfwConsentStatus", value, { expires: 365, samesite: "strict" });
    toggleNsfwContent();

    if (value === "hide") {
      redirectIfNecessary();
    }
  }
});
