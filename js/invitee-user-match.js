(function ($, Drupal, once) {
  'use strict';

  Drupal.behaviors.inviteeUserMatch = {
    attach: function (context, settings) {
      once('invitee-user-match', 'input[name="match_users"]', context).forEach(function(button) {
        $(button).on('click', function(e) {
          e.preventDefault();
          console.log('Match users button clicked');
          Drupal.ajax({
            url: Drupal.url('pi-comp/match-users'),
            element: button,
            progress: { type: 'throbber', message: Drupal.t('Matching users...') },
            success: function(response, status) {
              console.log('AJAX request successful');
            },
            error: function(xhr, status, error) {
              console.error('AJAX request failed:', error);
              Drupal.message('An error occurred while matching users.', 'error');
            }
          }).execute();
        });
      });

      once('create-users-form', '#create-users-form', context).forEach(function(form) {
        $(form).on('submit', function(e) {
          e.preventDefault();
          console.log('Create users form submitted');
          var selectedPIs = $('input[name="create_users[]"]:checked', form).map(function() {
            return this.value;
          }).get();

          if (selectedPIs.length > 0) {
            $.ajax({
              url: Drupal.url('pi-comp/create_invitee_users'),
              type: 'POST',
              data: JSON.stringify(selectedPIs),
              contentType: 'application/json',
              success: function(response) {
                console.log('Users created successfully:', response);
                Drupal.message('Users created successfully', 'status');
              },
              error: function(xhr, status, error) {
                console.error('Error creating users:', error);
                Drupal.message('Error creating users: ' + error, 'error');
              }
            });
          } else {
            console.log('No users selected for creation');
            Drupal.message('No users selected for creation', 'warning');
          }
        });
      });
    }
  };

})(jQuery, Drupal, once);
