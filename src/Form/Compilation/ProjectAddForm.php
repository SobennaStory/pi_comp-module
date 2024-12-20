<?php

namespace Drupal\pi_comp\Form\Compilation;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\pi_comp\Services\PimmProjectManagerInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Ajax\HtmlCommand;

class ProjectAddForm extends FormBase {

  protected $entityTypeManager;
  protected $projectManager;

  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    PimmProjectManagerInterface $project_manager
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->projectManager = $project_manager;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('pi_comp.project_manager')
    );
  }

  public function getFormId() {
    return 'pimm_project_add_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['#tree'] = TRUE;

    // Project selector with autocomplete
    $form['project'] = [
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('Select Project'),
      '#target_type' => 'node',
      '#selection_settings' => [
        'target_bundles' => ['project'],
      ],
      '#required' => TRUE,
      '#maxlength' => 500,
      '#ajax' => [
        'callback' => '::updateProjectDetails',
        'wrapper' => 'project-details-wrapper',
        'event' => 'autocompleteclose change',
      ],
    ];

    // Container for project details that will be updated via AJAX
    $form['project_details'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'project-details-wrapper'],
    ];

    // Show project details if a project is selected
    if ($project_id = $form_state->getValue('project')) {
      $project = $this->entityTypeManager->getStorage('node')->load($project_id);
      if ($project) {
        $form['project_details']['info'] = [
          '#type' => 'details',
          '#title' => $this->t('Project Details'),
          '#open' => TRUE,
        ];

        // Show full title with possible truncation in UI
        $full_title = $project->getTitle();
        $display_title = strlen($full_title) > 100
          ? substr($full_title, 0, 97) . '...'
          : $full_title;

        $form['project_details']['info']['title'] = [
          '#type' => 'item',
          '#title' => $this->t('Title'),
          '#markup' => '<div class="project-title" title="' . $full_title . '">' . $display_title . '</div>',
        ];

        if ($project->hasField('field_award_number') && !$project->get('field_award_number')->isEmpty()) {
          $form['project_details']['info']['award'] = [
            '#type' => 'item',
            '#title' => $this->t('Award Number'),
            '#markup' => $project->get('field_award_number')->entity ?
              $project->get('field_award_number')->entity->label() :
              $project->get('field_award_number')->value,
          ];
        }

        if ($project->hasField('field_project_lead_pi') && !$project->get('field_project_lead_pi')->isEmpty()) {
          $form['project_details']['info']['pi'] = [
            '#type' => 'item',
            '#title' => $this->t('Lead PI'),
            '#markup' => $project->get('field_project_lead_pi')->value,
          ];
        }

        // Show tracking status
        $is_tracked = $this->projectManager->isProjectTracked($project_id);
        $form['project_details']['info']['status'] = [
          '#type' => 'item',
          '#title' => $this->t('PIMM Status'),
          '#markup' => $is_tracked ?
            '<span class="status-badge status-active">Currently Tracked</span>' :
            '<span class="status-badge status-inactive">Not Tracked</span>',
        ];
      }
    }

    $form['notes'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Notes'),
      '#description' => $this->t('Optional notes about this project in PIMM'),
    ];

    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add to PIMM'),
      '#button_type' => 'primary',
    ];

    $form['#attached']['library'][] = 'pi_comp/dashboard';

    return $form;
  }

  public function updateProjectDetails(array &$form, FormStateInterface $form_state) {
    return $form['project_details'];
  }

  public function validateForm(array &$form, FormStateInterface $form_state) {
    if ($project_id = $form_state->getValue('project')) {
      $project = $this->entityTypeManager->getStorage('node')->load($project_id);

      if (!$project) {
        $form_state->setError($form['project'], $this->t('Invalid project selected.'));
        return;
      }

      if ($this->projectManager->isProjectTracked($project->id())) {
        $form_state->setError($form['project'], $this->t('This project is already being tracked in PIMM.'));
      }
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $project = $this->entityTypeManager->getStorage('node')->load($form_state->getValue('project'));

    if ($this->projectManager->addProject($project, $form_state->getValue('notes'))) {
      $this->messenger()->addStatus($this->t('Project "@title" has been added to PIMM.', [
        '@title' => $project->getTitle(),
      ]));
    }
    else {
      $this->messenger()->addError($this->t('Failed to add project to PIMM.'));
    }

    $form_state->setRedirect('pi_comp.dashboard');
  }
}
