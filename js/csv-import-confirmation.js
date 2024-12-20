(function ($, Drupal, once) {
  'use strict';

  Drupal.behaviors.csvImportConfirmation = {
    attach: function (context, settings) {
      // Initialize confirmation dialog handling
      once('csv-import-confirmation', 'body', context).forEach(function () {
        // Watch for dialog creation
        $(document).on('dialogcreate dialogopen', function (event, ui) {
          var $dialog = $(event.target).closest('.ui-dialog');

          if ($dialog.hasClass('confirm-import-dialog')) {
            // Remove any existing button pane
            $dialog.find('.ui-dialog-buttonpane').remove();

            // Create new button pane
            var $buttonPane = $('<div>').addClass('ui-dialog-buttonpane ui-widget-content ui-helper-clearfix');
            var $buttonSet = $('<div>').addClass('ui-dialog-buttonset');

            // Create confirm button
            var $confirmButton = $('<button>')
              .text('Confirm Import')
              .addClass('button button--primary')
              .on('click', function() {
                // Set the hidden input value to true
                $('input[name="submit_uploads"]').val('true');

                // Find and trigger the hidden submit button
                var $submitButton = $('input[name="op"][value="Confirm Import"]');
                if ($submitButton.length) {
                  $submitButton.trigger('mousedown').trigger('mouseup').trigger('click');
                }

                // Close the dialog
                $dialog.dialog('close');
                return false;
              });

            // Create cancel button
            var $cancelButton = $('<button>')
              .text('Cancel')
              .addClass('button')
              .on('click', function() {
                // Set the hidden input value to false
                $('input[name="submit_uploads"]').val('false');

                // Close the dialog
                $dialog.dialog('close');
                return false;
              });

            // Add buttons to dialog
            $buttonSet.append($confirmButton).append($cancelButton);
            $buttonPane.append($buttonSet);
            $dialog.append($buttonPane);
          }
        });
      });
    }
  };
})(jQuery, Drupal, once);
