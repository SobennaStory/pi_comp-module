<?php

namespace Drupal\pi_comp\Controller\Compilation;

use Drupal\Core\Controller\ControllerBase;

/**
 * Controller for displaying the view page.
 */
class PICompViewController extends ControllerBase {

  /**
   * Displays the forms.
   *
   * @return array
   *   A render array containing the forms.
   */
  public function build() {
    $build = [
      '#attached' => [
        'library' => [
          'pi_comp/pi_comp_styles',
        ],
      ],
      'pi_form' => $this->formBuilder()->getForm('Drupal\pi_comp\Form\Compilation\piForm3'),
      'new_project_form' => $this->formBuilder()->getForm('Drupal\pi_comp\Form\Compilation\NewProjectForm'),
    ];
    return $build;
  }

}
