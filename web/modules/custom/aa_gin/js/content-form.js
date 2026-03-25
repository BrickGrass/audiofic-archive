(function ($) {
  function elementOverflowing(element) {
    return element.prop("scrollHeight") > element.prop("offsetHeight");
  }

  function elementDoesNotExist(element) {
    return element.length === 0;
  }

  function insertReadmoreIfNecessary() {
    let user_bio_div = $(".user-profile-bio");
    let user_bio_readmore_button = $(".expand-help-button");
    if (elementDoesNotExist(user_bio_div)) {
      return;
    }

    if (elementOverflowing(user_bio_div) && elementDoesNotExist(user_bio_readmore_button)) {
      // if readmore button does not already exist, insert it!
      $(".user-profile-bio-wrapper").append(`<a href="#" data-for="userProfileBio" class="expand-help-button">Read More</a>`)
      $(".expand-help-button").on("click", function(event) {
        event.preventDefault();

        let target = $(this).attr("data-for");
        $(`#${target}`).toggleClass("expand-help-hide")

        $(this).text( $(this).text() === "Read More" ? "Read Less" : "Read More" );
      });
    }
  }

  $(".expand-help-button").on("click", function(event) {
    event.preventDefault();

    let target = $(this).attr("data-for");
    $(`#${target}`).toggleClass("expand-help-hide")

    $(this).text( $(this).text() === "Read More" ? "Read Less" : "Read More" );
  });

  $( document ).ready(function() {
    insertReadmoreIfNecessary();
  });

  $( window ).on("resize", function(event) {
    insertReadmoreIfNecessary();
  })
})(jQuery);
