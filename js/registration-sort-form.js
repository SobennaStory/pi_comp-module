(function ($, Drupal) {
  Drupal.behaviors.registrationSortForm = {
    attach: function (context, settings) {
      $('#edit-webform', context).once('registration-sort-form').on('change', function () {
        var webformId = $(this).val();
        if (webformId) {
          $.getJSON('/pi-comp/webform-fields/' + webformId, function (data) {
            var $fieldSelect = $('#edit-field');
            $fieldSelect.empty();
            $.each(data, function (key, value) {
              $fieldSelect.append($('<option></option>').attr('value', key).text(value));
            });
          });
        }
      });
    }
  };
})(jQuery, Drupal);
