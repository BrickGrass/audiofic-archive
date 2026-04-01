(function ($) {
  $(document).ready(function() {
    let fandom = $(".form-item--field-fandom2-target-id .claro-autocomplete");
    $(fandom).append("<a aria-label='Open a new tab to add a new fandom tag' href='/admin/structure/taxonomy/manage/fandom/add' target='_blank' class='add-entity-link add-entity-link-margin-left'>+</a>");
    $(fandom).addClass("autocomplete-with-add-link")

    let relationship = $(".form-item--field-relationship-target-id .claro-autocomplete");
    $(relationship).append("<a aria-label='Open a new tab to add a new relationship tag' href='/admin/structure/taxonomy/manage/relationship/add' target='_blank' class='add-entity-link add-entity-link-margin-left'>+</a>");
    $(relationship).addClass("autocomplete-with-add-link")
  })
})(jQuery);