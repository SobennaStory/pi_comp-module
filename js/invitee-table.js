
(function ($, Drupal) {
  Drupal.behaviors.pimmProjectTable = {
    attach: function (context, settings) {
      console.log('Callback executed!');
      once('pimm-project-table', '#pimm-project-table', context).forEach(function(table) {
        var $table = $(table);
        var $selectAll = $table.find('th:first-child input[type="checkbox"]');
        var $checkboxes = $table.find('td:first-child input[type="checkbox"]');

        $selectAll.on('click', function() {
          var isChecked = $(this).prop('checked');
          $checkboxes.prop('checked', isChecked);
        });

        $checkboxes.on('click', function() {
          var allChecked = ($checkboxes.length === $checkboxes.filter(':checked').length);
          $selectAll.prop('checked', allChecked);
        });
      });
    }
  };
})(jQuery, Drupal);
