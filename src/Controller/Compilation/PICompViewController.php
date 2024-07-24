<?php

namespace Drupal\pi_comp\Controller\Compilation;

use Drupal\Core\Controller\ControllerBase;

/**
 * Controller for displaying the view page.
 */
class PICompViewController extends ControllerBase {

  /**
   * Displays the forms in collapsible fieldsets.
   *
   * @return array
   *   A render array containing the forms in collapsible fieldsets.
   */
  public function build() {
    $build = [
      '#attached' => [
        'library' => [
          'pi_comp/pi_comp_styles',
        ],
      ],
      'import_projects' => [
        '#type' => 'details',
        '#title' => $this->t('Import Projects from CSV'),
        '#open' => TRUE, // This fieldset will be open by default
        'form' => $this->formBuilder()->getForm('Drupal\pi_comp\Form\Compilation\piForm3'),
      ],
      'new_project' => [
        '#type' => 'details',
        '#title' => $this->t('Create New Project'),
        '#open' => FALSE, // This fieldset will be closed by default
        'form' => $this->formBuilder()->getForm('Drupal\pi_comp\Form\Compilation\NewProjectForm'),
      ],
    ];
    return $build;
  }

}
