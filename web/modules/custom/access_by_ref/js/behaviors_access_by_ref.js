(function (Drupal, once) {
    Drupal.behaviors.access_by_ref = {
        attach(context) {

            /* Remove groups from the field selector to keep it clean */
            once('init', '#edit-bundle', context).forEach((bundle) => {
                // First create a copy of all the bundles.
                const selectField = document.querySelector('#edit-field');
                const selectGroups = [];
                selectField.querySelectorAll('optgroup').forEach(group => selectGroups.push(group));

                // Add listner to the bundle select.
                bundle.addEventListener('change', (event) => {
                    // Get selected value, required for shared fields that occur in multiple groups / nodettypes (issue #3378478)
                    const selectedValue = selectField.value;

                    // Remove all existing groups from select.
                    selectField.querySelectorAll('optgroup').forEach(group => group.remove());
                    // Loop through all bundle option groups.
                    selectGroups.forEach((group) => {
                        // If bundle is the one we want, add to select.
                        if (group.getAttribute('label') == event.target.value) {
                            selectField.appendChild(group);
                        }
                    });

                    // Set selected value, required for shared fields that occur in multiple groups / nodettypes (issue #3378478)
                    selectField.value = selectedValue;
                });
                bundle.dispatchEvent(new Event("change")); // Trigger the change on initial load.
            });

            /* Only show Extra field when "Profile value" selected. */
            once('init-extra', '#edit-reference-type', context).forEach((value) => {
                // Hide the extra field as default.
                const html_field = document.querySelector('.js-form-item-extra');
                const html_field_select = document.querySelector('.js-form-item-extra select');

                // Unhide the field if a relevant options is selected.
                value.addEventListener('change', (event) => {
                    if (event.target.value !== 'shared') {
                        html_field.style.display = "none";
                        html_field_select.selectedIndex = 0;
                    }
                    else {
                        html_field.style.display = "";
                    }
                });
                value.dispatchEvent(new Event("change")); // Trigger the change on initial load.
            });

            /* Only show "Rights type" when "Inherit from parent" selected. */
            once('init-rights-type', '#edit-reference-type', context).forEach((value) => {
                // Hide the rights type field as default.
                const html_field = document.querySelector('.js-form-item-rights-type');
                const html_field_select = document.querySelector('.js-form-item-rights-type select');

                // Unhide the field if a relevant options is selected.
                value.addEventListener('change', (event) => {
                    if (event.target.value !== 'inherit') {
                        html_field.style.display = "none";
                        html_field_select.selectedIndex = 0;

                    }
                    else {
                        html_field.style.display = "";
                    }
                });
                value.dispatchEvent(new Event("change")); // Trigger the change on initial load.
            });
        }
    }
})(Drupal, once);
