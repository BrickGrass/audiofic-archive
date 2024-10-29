var duration_mapping = {};

function create_callbacks(id) {
  let inputs = $(`input[data-aa-duration-for=${id}]`);

  duration_mapping[id] = {};

  $(inputs).each(function(_) {
    duration_mapping[id][$(this).attr("placeholder")] = this;

    $(this).on("input", function(_) {
      let h = Number(duration_mapping[id]["HH"].value);
      let m = Number(duration_mapping[id]["MM"].value);
      let s = Number(duration_mapping[id]["SS"].value);
      let total = s + (60 * m) + (3600 * h)
      total = total === 0 ? "" : total;

      $(`#${id}`).val(total);
    });

    $(this).on("keydown", function(e) {
      // Always allow backspace, delete, tab & enter
      if (["Backspace", "Delete", "Enter", "Tab"].includes(e.key)) {
        return;
      }

      // Prevent entering anything but the characters 0-9
      if (!e.key.match(/^[0-9]+$/)) {
        e.preventDefault();
        return;
      }

      // Don't allow numbers greater than 59 in the minute or second inputs
      if (["MM", "SS"].includes($(this).attr("placeholder"))) {
        let before_insert = this.value.substring(0, this.selectionStart);
        let after_insert = this.value.substring(this.selectionEnd, this.value.length);
        let result = Number(before_insert + e.key + after_insert);

        if (result > 59) {
          e.preventDefault();
        }
      }
    });
  });
}

$(document).ready(function() {
  $(".aa-duration-hidden").each(function(_) {
    let id = $(this).attr("id");
    create_callbacks(id);
  });
});
