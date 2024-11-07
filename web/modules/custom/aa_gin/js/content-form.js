(function ($) {
  $(".expand-help-button").on("click", function(event) {
    event.preventDefault();

    let target = $(this).attr("data-for");
    $(`#${target}`).toggleClass("expand-help-hide")

    $(this).text( $(this).text() === "Read More" ? "Read Less" : "Read More" );
  });
})(jQuery);
