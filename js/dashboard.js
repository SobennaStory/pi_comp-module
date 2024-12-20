(function ($, Drupal, once) {
  'use strict';

  Drupal.behaviors.pimmDashboard = {
    // Cache selectors
    selectors: {
      dropdownTrigger: '[data-dropdown]',
      dropdownContent: '.dropdown-content',
      statusForm: 'form[id^="pimm-project-status-form"]',
      removeForm: 'form[id^="pimm-project-remove-form"]',
      cancelButton: '[data-drupal-selector$="cancel"]',
      notesToggle: '.notes-toggle',
      detailsToggle: '.toggle-details',
      dashboardTable: '.dashboard-table',
      emptyState: '[data-project-count="0"]',
      projectButtons: '[data-dropdown="bulk-add-project"], [data-dropdown="single-add-project"], [data-dropdown="add-project"]'
    },

    attach: function (context, settings) {
      this.initDropdowns(context);
      this.initStatusForms(context);
      this.initRemoveForms(context);
      this.initTableFeatures(context);
      this.initEmptyStateHandling(context);
      this.bindGlobalEvents();
    },

    initDropdowns: function(context) {
      const self = this;

      // Initialize dropdowns
      once('pimm-dropdown', this.selectors.dropdownTrigger, context).forEach(button => {
        const $button = $(button);
        const dropdownId = $button.data('dropdown');
        const $dropdown = $(`#${dropdownId}-dropdown`);

        $button.on('click', function(e) {
          e.preventDefault();
          e.stopPropagation();

          // Close other dropdowns first
          $(self.selectors.dropdownContent).not($dropdown).addClass('hidden');
          $dropdown.toggleClass('hidden');
        });
      });
    },

    initStatusForms: function(context) {
      const self = this;

      // Handle status form submissions
      once('status-form', this.selectors.statusForm, context).forEach(form => {
        const $form = $(form);

        $form.find('.status-submit-button').click(function(e) {
          console.log('Submit button clicked');  // Debug log
          const $button = $(this);
          const nid = $button.data('nid');

          // Create debug overlay
          const debugInfo = `
        <div style="position: fixed; top: 10px; right: 10px; background: #333; color: white; padding: 15px; z-index: 9999; border-radius: 5px;">
          <h4 style="margin: 0 0 10px 0;">Form Debug Info</h4>
          <p>Form ID: ${$form.attr('id')}</p>
          <p>NID: ${nid}</p>
          <p>Button ID: ${$button.attr('id')}</p>
          <p>Time: ${new Date().toLocaleTimeString()}</p>
        </div>
      `;

          // Remove any existing debug overlay
          $('#debug-overlay').remove();

          // Add new debug overlay
          $('body').append(debugInfo);

          // Remove after 5 seconds
          setTimeout(() => {
            $('#debug-overlay').fadeOut('slow', function() {
              $(this).remove();
            });
          }, 5000);
        });

        $form.on('submit', function(e) {
          e.preventDefault();

          const $submit = $form.find('[type="submit"]');
          const $dropdown = $form.closest(self.selectors.dropdownContent);

          if ($submit.prop('disabled')) {
            return;
          }

          // Disable submit and show loading state
          $submit.prop('disabled', true).addClass('is-loading');

          // Submit form via AJAX
          const ajax = Drupal.ajax({
            url: $form.attr('action'),
            submit: $form.serialize(),
            element: $submit[0],
            progress: { type: 'throbber' },
            success: function(response, status) {
              // Close dropdown and refresh page on success
              $dropdown.addClass('hidden');
              window.location.reload();
            },
            error: function(xhr, status, error) {
              // Re-enable submit button on error
              $submit.prop('disabled', false).removeClass('is-loading');
              console.error('Status update failed:', error);
            },
            complete: function() {
              $submit.prop('disabled', false).removeClass('is-loading');
            }
          });

          ajax.execute();
        });
      });
    },

    initRemoveForms: function(context) {
      const self = this;

      // Handle remove form submissions
      once('remove-form', this.selectors.removeForm, context).forEach(form => {
        const $form = $(form);

        $form.on('submit', function(e) {
          e.preventDefault();

          if (!confirm('Are you sure you want to remove this project from PIMM?')) {
            return;
          }

          const $submit = $form.find('[type="submit"]');
          const $dropdown = $form.closest(self.selectors.dropdownContent);

          if ($submit.prop('disabled')) {
            return;
          }

          // Disable submit and show loading state
          $submit.prop('disabled', true).addClass('is-loading');

          // Submit form via AJAX
          const ajax = Drupal.ajax({
            url: $form.attr('action'),
            submit: $form.serialize(),
            element: $submit[0],
            progress: { type: 'throbber' },
            success: function(response, status) {
              // Close dropdown and refresh page on success
              $dropdown.addClass('hidden');
              window.location.reload();
            },
            error: function(xhr, status, error) {
              // Re-enable submit button on error
              $submit.prop('disabled', false).removeClass('is-loading');
              console.error('Project removal failed:', error);
            },
            complete: function() {
              $submit.prop('disabled', false).removeClass('is-loading');
            }
          });

          ajax.execute();
        });

        // Handle cancel button
        $form.find(this.selectors.cancelButton).on('click', function(e) {
          e.preventDefault();
          $(this).closest(self.selectors.dropdownContent).addClass('hidden');
        });
      });
    },

    initTableFeatures: function(context) {
      // Handle notes toggles
      once('pimm-notes-toggle', this.selectors.notesToggle, context).forEach(button => {
        $(button).on('click', function(e) {
          e.preventDefault();
          $(`#${$(this).data('target')}`).toggleClass('hidden');
        });
      });

      // Handle details toggles
      once('pimm-details-toggle', this.selectors.detailsToggle, context).forEach(button => {
        $(button).on('click', function(e) {
          e.preventDefault();
          const $details = $(`#${$(this).data('target')}`);
          const isHidden = $details.hasClass('hidden');
          $details.toggleClass('hidden');
          $(this).text(isHidden ? 'Hide' : 'View Details');
        });
      });
    },

    initEmptyStateHandling: function(context) {
      once('empty-state-handler', this.selectors.emptyState, context).forEach(element => {
        const $buttons = $(this.selectors.projectButtons);

        $buttons.on('click', function(e) {
          e.preventDefault();
          e.stopPropagation();

          if (confirm('There are currently no projects available on the site. Would you like to be redirected to the project upload form?')) {
            window.location.href = '/pi-comp/projects';
          }
        });
      });
    },

    bindGlobalEvents: function() {
      // Close dropdowns when clicking outside
      $(document).on('click', (e) => {
        if (!$(e.target).closest('.dropdown').length) {
          $(this.selectors.dropdownContent).addClass('hidden');
        }
      });

      // Handle table responsiveness
      let resizeTimeout;
      $(window).on('resize', () => {
        clearTimeout(resizeTimeout);
        resizeTimeout = setTimeout(() => {
          $(this.selectors.dashboardTable).each(function() {
            const $table = $(this);
            const $container = $table.parent();
            $container.toggleClass('scroll-x', $table.width() > $container.width());
          });
        }, 250);
      });
    }
  };

})(jQuery, Drupal, once);
