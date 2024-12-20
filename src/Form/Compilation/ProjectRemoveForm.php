<?php

namespace Drupal\pi_comp\Form\Compilation;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\pi_comp\Services\PimmProjectManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Form for removing a project from PIMM tracking.
 */
class ProjectRemoveForm extends FormBase {

  /**
   * The project manager service.
   *
   * @var \Drupal\pi_comp\Services\PimmProjectManagerInterface
   */
  protected $projectManager;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new ProjectRemoveForm.
   */

  protected $formNid;
  public function __construct(
    PimmProjectManagerInterface $project_manager,
    EntityTypeManagerInterface $entity_type_manager,
    $form_nid = NULL
  ) {
    $this->projectManager = $project_manager;
    $this->entityTypeManager = $entity_type_manager;
    $this->formNid = $form_nid;
  }


  public function getFormId() {
    return 'pimm_project_remove_form' . ($this->formNid ? '_' . $this->formNid : '');
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $nid = NULL) {
    if (!$nid) {
      return [];
    }

    $form['nid'] = [
      '#type' => 'hidden',
      '#value' => $nid,
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Remove'),
      '#attributes' => [
        'class' => ['button', 'button--danger', 'button--small'],
        'onclick' => 'return confirm("' . $this->t('Are you sure you want to remove this project?') . '");',
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $nid = $form_state->getValue('nid');

    if ($this->projectManager->removeProject($nid)) {
      $this->messenger()->addStatus($this->t('Project successfully removed.'));
    }
    else {
      $this->messenger()->addError($this->t('Failed to remove project.'));
    }

    $form_state->setRedirect('pi_comp.dashboard');
  }

}
