(function ($) {
  $(document).ready(function() {
    $(".audiofic-autocomplete-widget").each(function() {
      let autocomplete_url = $(this).attr("data-autocomplete-url");
      let placeholder = $(this).attr("data-placeholder");
      let prePopulate = [];
      try {
        prePopulate = JSON.parse($(this).attr("data-value"));
      } catch (e) {
        console.log(`Invalid value provided in ${this} data-value`);
      }

      $(this).tokenInput(autocomplete_url, {
        preventDuplicates: true,
        placeholder: placeholder,
        prePopulate: prePopulate
      });
    });
  })
}(jQuery));