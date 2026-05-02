(function ($) {
  function detailsClickCallback() {
    let childrenLoaded = $( this ).attr("data-children-loaded") === "true";
    console.log(`Children loaded: ${childrenLoaded}`);

    if (childrenLoaded) {
      return;
    }

    let detailsElem = $( this );
    let tid = $( this ).attr("data-tid");
    let list = $( this ).find("ul");

    $( list ).load(`/admin/structure/taxonomy/wrangling/browse-canonical-fandoms/${tid}`, function() {
      $( detailsElem ).attr("data-children-loaded", "true");
      $( this ).find("details").on("click", detailsClickCallback);
    })
  }

  $(".fandom-tree details").on("click", detailsClickCallback);
})(jQuery);