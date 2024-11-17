(function ($) {
  $(document).ready(function() {
    $("#expand-filters").on("click", function(e) {
      // Only refocus to top of form when filters are expanded, not when contracted
      if ($(this).attr("aria-expanded") === "false") {
        // Jump back to filters expand/contract button when filters are contracted
        $('html,body').animate({scrollTop: $(this).attr("href").offset().top},'slow');
        return;
      }

      let form = $("#edit-submit-search--2")[0];
      if (form) {
        $(form).focus();
      }
    })
  });
})(jQuery);