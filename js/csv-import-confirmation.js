(function ($, Drupal, once) {
  console.log('CSV import confirmation script loaded');

  Drupal.behaviors.csvImportConfirmation = {
    attach: function (context, settings) {
      console.log('CSV import confirmation behavior attached');

      once('csv-import-confirmation', 'body', context).forEach(function () {
        $(document).on('dialogcreate dialogopen', function (event, ui) {
          console.log('Dialog event triggered:', event.type);
          var $dialog = $(event.target).closest('.ui-dialog');
          console.log('Dialog classes:', $dialog.attr('class'));
          console.log('Dialog content:', $dialog.find('.ui-dialog-content').html());

          if ($dialog.hasClass('confirm-import-dialog')) {
            console.log('Confirm import dialog detected');

            // Remove existing buttons
            $dialog.find('.ui-dialog-buttonpane').remove();

            // Create confirm and cancel buttons
            var $buttonPane = $('<div>').addClass('ui-dialog-buttonpane ui-widget-content ui-helper-clearfix');
            var $buttonSet = $('<div>').addClass('ui-dialog-buttonset');
            var $confirmButton = $('<button>').text('Confirm Import').addClass('button button--primary');
            var $cancelButton = $('<button>').text('Cancel').addClass('button');

            // Add click handlers
            $confirmButton.on('click', function() {
              console.log('Confirm button clicked');
              $('input[name="op"][value="Confirm Import"]').trigger('click');
              closeDialog($dialog);
            });

            $cancelButton.on('click', function() {
              console.log('Cancel button clicked');
              closeDialog($dialog);
            });

            // Append buttons to the dialog
            $buttonSet.append($confirmButton).append($cancelButton);
            $buttonPane.append($buttonSet);
            $dialog.append($buttonPane);
            console.log('Buttons appended to dialog');
          } else {
            console.log('This is not the confirm import dialog');
          }
        });
      });

      function closeDialog($dialog) {
        if ($dialog.hasClass('ui-dialog-content')) {
          $dialog.dialog('close');
        } else {
          $dialog.remove();
        }
      }
    }
  };
})(jQuery, Drupal, once);
