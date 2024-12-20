(function ($, Drupal, once) {
  'use strict';

  Drupal.behaviors.projectRemove = {
    attach: function (context, settings) {
      once('project-remove', '[data-drupal-selector^="edit-actions-submit"]', context).forEach(function(button) {
        $(button).on('click', function(e) {
          // Prevent double submission
          if ($(this).hasClass('is-disabled')) {
            e.preventDefault();
            return;
          }

          // Add disabled state
          $(this).addClass('is-disabled');

          // Remove disabled state after response
          $(document).ajaxComplete(function() {
            $(button).removeClass('is-disabled');
          });
        });
      });

      // Update project count after successful removal
      $(document).on('dialogclose', function(e) {
        if ($(e.target).find('form[id^="pimm-project-remove-form"]').length) {
          // Wait for removal animation
          setTimeout(function() {
            const projectCount = $('.projects-table tbody tr').length;
            $('.project-count').text(projectCount);

            // Show empty state if no projects
            if (projectCount === 0) {
              $('.projects-table').hide();
              $('.empty-state').removeClass('hidden');
            }
          }, 300);
        }
      });
    }
  };
})(jQuery, Drupal, once);
