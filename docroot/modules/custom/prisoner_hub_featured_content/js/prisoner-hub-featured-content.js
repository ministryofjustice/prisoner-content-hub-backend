(function (Drupal, drupalSettings, $) {

  'use strict';

  Drupal.behaviors.prisonerHubFeaturedContent = {
    attach: function (context) {
      const $checkboxes = $('[name^="field_feature_on_category"]'); // Use name^ to account for multiple checkbox fields.
      const $categoryField = $('[name="field_moj_top_level_categories[]"], [name="field_category[]"]');
      const $seriesField = $('[name="field_moj_series"]');

      // Hide the entire fieldset upon page load.
      $checkboxes.closest('fieldset').once().hide();

      $categoryField
        .once()
        .on('change', (e) => {
          filterCheckboxes($(e.currentTarget).val());
        });
      // Trigger change event if value is not empty.
      if ($categoryField.val().length) {
        $categoryField.change();
      }

      $seriesField
        .once()
        .on('change', (e) => {
          // Retrieve the categories for the series via drupalSettings.
          const seriesVal = $(e.currentTarget).val();
          const selectedCategories = drupalSettings.prisonerHubFeaturedContent.seriesByCategory.hasOwnProperty(seriesVal) ? drupalSettings.prisonerHubFeaturedContent.seriesByCategory[seriesVal] : [];
          filterCheckboxes(selectedCategories);
        });
      // Trigger change event if value is not empty.
      if ($seriesField.val().length) {
        $seriesField.change();
      }

      function filterCheckboxes(selectedCategories) {
        $checkboxes
          .not(isCurrentSelectedCategory)
          .prop('checked', false)
          .closest('div.form-item')
          .hide();

        const count = $checkboxes
          .filter(isCurrentSelectedCategory)
          .closest('div.form-item')
          .show()
          .length;
        if (count) {
          $checkboxes.closest('fieldset').show();
        }
        else {
          $checkboxes.closest('fieldset').hide();
        }
        function isCurrentSelectedCategory(index, checkbox) {
          return selectedCategories.includes(checkbox.value);
        }
      }
    }
  };
})(Drupal, drupalSettings, jQuery);
