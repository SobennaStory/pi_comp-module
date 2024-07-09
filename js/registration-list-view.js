(function (Drupal, drupalSettings) {
  'use strict';

  Drupal.behaviors.registrationListView = {
    attach: function (context, settings) {
      context.querySelectorAll('[data-drupal-selector="edit-view-button"]').forEach(function(button) {
        button.addEventListener('click', function(e) {
          e.preventDefault();
          console.log('Button clicked');

          var selectList = context.querySelector('[data-drupal-selector="edit-select-list"]');
          if (selectList) {
            var selectedId = selectList.value;

            if (selectedId && drupalSettings.pi_comp && drupalSettings.pi_comp.viewUrl) {
              console.log('Redirecting to:', drupalSettings.pi_comp.viewUrl + '/' + selectedId);
              window.location.href = drupalSettings.pi_comp.viewUrl + '/' + selectedId;
            }
          } else {
            console.log('Select list not found');
          }
        });
      });
    }
  };
})(Drupal, drupalSettings);
