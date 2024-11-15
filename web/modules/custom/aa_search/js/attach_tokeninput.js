(function ($) {
  $(document).ready(function() {
    $(".audiofic-autocomplete-widget").each(function() {
      let autocomplete_url = $(this).attr("data-autocomplete-url");
      let placeholder = $(this).attr("data-placeholder");
      let prePopulate = [];
      let selector = $(this).attr("data-drupal-selector");
      let id = this.id;

      try {
        prePopulate = JSON.parse($(this).attr("data-value"));
      } catch (e) {
        console.log(`Invalid value provided in ${this} data-value`);
      }

      $(this).tokenInput(autocomplete_url, {
        preventDuplicates: true,
        allowTabOut: true,
        placeholder: placeholder,
        prePopulate: prePopulate,
        selector: selector,
        resultsFormatter: function(item) {
          var string = item.name;
          return "<li id=\"" + id + "-" + item.id + "\">" + string + "</li>";
        }
      });
    });
  })
}(jQuery));