<?php

namespace Drupal\pi_comp\Form\Compilation;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\pi_comp\Services\PimmProjectManagerInterface;
use Drupal\Core\Database\Connection;

class ProjectStatusForm extends FormBase {
  protected $entityTypeManager;
  protected $database;
  protected $formNid;

  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    Connection $database,
    $form_nid = NULL
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->database = $database;
    $this->formNid = $form_nid;
  }

  public function getFormId() {
    return 'pimm_project_status_form' . ($this->formNid ? '_' . $this->formNid : '');
  }

  public function buildForm(array $form, FormStateInterface $form_state, $nid = NULL) {
    // Use set NID or passed NID
    $nid = $this->formNid ?? $nid;
    if (!$nid) {
      return [];
    }

    // Set unique form ID for this instance
    $this->formId = 'pimm_project_status_form_' . $nid;

    \Drupal::logger('pi_comp')->notice('Building form for NID: @nid with form ID: @form_id', [
      '@nid' => $nid,
      '@form_id' => $this->getFormId()
    ]);

    $form['#id'] = 'pimm-project-status-form-' . $nid;
    $form['form_id_field'] = [
      '#type' => 'hidden',
      '#value' => $this->getFormId(),
    ];

    $form['nid'] = [
      '#type' => 'hidden',
      '#value' => $nid,
    ];

    $current_status = $this->database->select('pimm_tracked_projects', 'p')
      ->fields('p', ['status', 'notes'])
      ->condition('nid', $nid)
      ->execute()
      ->fetchObject();

    $form['status'] = [
      '#type' => 'select',
      '#title' => $this->t('Status'),
      '#default_value' => $current_status ? $current_status->status : 'active',
      '#options' => [
        'active' => $this->t('Active'),
        'pending' => $this->t('Pending'),
        'inactive' => $this->t('Inactive'),
        'archived' => $this->t('Archived'),
      ],
      '#required' => TRUE,
    ];

    $form['notes'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Status Notes'),
      '#default_value' => $current_status ? $current_status->notes : '',
      '#description' => $this->t('Optional notes about this status change'),
    ];

    $form['debug_info'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['form-debug-info'],
        'data-nid' => $nid,
        'data-form-id' => $this->formId,
      ],
      'content' => [
        '#markup' => $this->t('Form ID: @form_id, NID: @nid', [
          '@form_id' => $this->formId,
          '@nid' => $nid,
        ]),
      ],
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Update Status'),
      '#button_type' => 'primary',
      '#id' => 'edit-submit-' . $nid,
      '#attributes' => [
        'class' => ['status-submit-button'],
        'data-nid' => $nid,
        'data-form-nid' => $nid,
      ],
    ];

    $form['#prefix'] = '<div id="pimm-project-status-form-wrapper-' . $nid . '">';
    $form['#suffix'] = '</div>';

    return $form;
  }


  public function submitForm(array &$form, FormStateInterface $form_state) {
    $nid = $form_state->getValue('nid');
    $status = $form_state->getValue('status');
    $notes = $form_state->getValue('notes');

    $request = \Drupal::request();
    if ($request->isMethod('POST')) {
      $submitted_form_id = $request->request->get('form_id_field');
      if ($submitted_form_id && $submitted_form_id != $this->formId) {
        // This isn't the form that was submitted, don't process it
        \Drupal::logger('pi_comp')->notice('Skipping submit for wrong form - Expected: @expected, Got: @submitted', [
          '@expected' => $this->formId,
          '@submitted' => $submitted_form_id
        ]);
        return;
      }
    }

    \Drupal::logger('pi_comp')->notice('Form submission - Form ID: @form_id, NID: @nid, Status: @status', [
      '@form_id' => $form_state->getValue('form_id_field'),
      '@nid' => $nid,
      '@status' => $status
    ]);

    try {
      \Drupal::logger('pi_comp')->notice('Status update attempted for nid: @nid', ['@nid' => $nid]);

      $result = $this->database->update('pimm_tracked_projects')
        ->fields([
          'status' => $status,
          'notes' => $notes,
        ])
        ->condition('nid', $nid)
        ->execute();

      if ($result) {
        $node = $this->entityTypeManager->getStorage('node')->load($nid);
        $this->messenger()->addStatus($this->t('Status updated for project "@title" (NID: @nid)', [
          '@title' => $node ? $node->getTitle() : $nid,
          '@nid' => $nid,
        ]));
      }
      else {
        $this->messenger()->addError($this->t('Failed to update project status for NID: @nid', ['@nid' => $nid]));
      }
    }
    catch (\Exception $e) {
      $this->logger('pi_comp')->error('Status update error for NID @nid: @message', [
        '@nid' => $nid,
        '@message' => $e->getMessage()
      ]);
      $this->messenger()->addError($this->t('An error occurred while updating the status.'));
    }

    $form_state->setRedirect('pi_comp.dashboard');
  }
}
