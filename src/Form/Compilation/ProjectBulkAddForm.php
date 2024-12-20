<?php

namespace Drupal\pi_comp\Form\Compilation;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\pi_comp\Services\PimmProjectManagerInterface;
use Drupal\Core\Database\Connection;

class ProjectBulkAddForm extends FormBase {

  protected $entityTypeManager;
  protected $projectManager;
  protected $database;

  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    PimmProjectManagerInterface $project_manager,
    Connection $database
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->projectManager = $project_manager;
    $this->database = $database;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('pi_comp.project_manager'),
      $container->get('database')
    );
  }

  public function getFormId() {
    return 'pimm_project_bulk_add_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    // Get count of untracked projects
    $total_projects = $this->getUntrackedProjectCount();

    $form['info'] = [
      '#type' => 'markup',
      '#markup' => $this->t('<p>This will add all untracked projects to PIMM.</p><p>Found @count untracked projects.</p>', [
        '@count' => $total_projects,
      ]),
    ];

    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add All Projects to PIMM'),
      '#button_type' => 'primary',
      '#disabled' => ($total_projects === 0),
    ];

    return $form;
  }

  private function getUntrackedProjectCount() {
    // Get all project node IDs
    $query = $this->entityTypeManager->getStorage('node')->getQuery()
      ->condition('type', 'project')
      ->accessCheck(FALSE);
    $project_nids = $query->execute();

    // Get tracked project IDs
    $tracked_projects = $this->database->select('pimm_tracked_projects', 'p')
      ->fields('p', ['nid'])
      ->execute()
      ->fetchCol();

    // Return count of untracked projects
    return count(array_diff($project_nids, $tracked_projects));
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Get untracked projects
    $query = $this->entityTypeManager->getStorage('node')->getQuery()
      ->condition('type', 'project')
      ->accessCheck(FALSE);
    $project_nids = $query->execute();

    $tracked_projects = $this->database->select('pimm_tracked_projects', 'p')
      ->fields('p', ['nid'])
      ->execute()
      ->fetchCol();

    $untracked_nids = array_diff($project_nids, $tracked_projects);

    if (empty($untracked_nids)) {
      $this->messenger()->addStatus($this->t('No untracked projects found.'));
      return;
    }

    // Log the start of batch creation
    \Drupal::logger('pi_comp')->notice('Creating batch for @count projects', [
      '@count' => count($untracked_nids)
    ]);

    $operations = [];
    foreach (array_chunk($untracked_nids, 20) as $chunk) {
      $operations[] = [
        [$this, 'processProjectBatch'],
        [$chunk]
      ];
    }

    $batch = [
      'title' => $this->t('Adding projects to PIMM'),
      'init_message' => $this->t('Preparing to add projects...'),
      'progress_message' => $this->t('Processed @current out of @total projects.'),
      'error_message' => $this->t('Error adding projects'),
      'operations' => $operations,
      'finished' => [$this, 'batchFinished'],
    ];

    batch_set($batch);

    $batch_id = 58; // The ID from your URL
    $batch_record = $this->database->select('batch', 'b')
      ->fields('b')
      ->condition('bid', $batch_id)
      ->execute()
      ->fetchAssoc();

    \Drupal::logger('pi_comp')->notice('Batch record: @record', [
      '@record' => print_r($batch_record, TRUE)
    ]);
    // Remove the redirect - batch will handle it
  }
  public function processProjectBatch($nids, &$context) {
    \Drupal::logger('pi_comp')->notice('Processing batch of @count projects', [
      '@count' => count($nids)
    ]);
    if (!isset($context['results']['added'])) {
      $context['results']['added'] = 0;
      $context['results']['failed'] = 0;
    }

    foreach ($nids as $nid) {
      $node = $this->entityTypeManager->getStorage('node')->load($nid);
      if ($node && $this->projectManager->addProject($node, 'Added via bulk import')) {
        $context['results']['added']++;
        $context['message'] = $this->t('Added project: @title', [
          '@title' => $node->getTitle(),
        ]);
      } else {
        $context['results']['failed']++;
        $context['message'] = $this->t('Failed to add project with ID: @nid', [
          '@nid' => $nid,
        ]);
      }
    }
  }

  public function batchFinished($success, $results, $operations) {
    \Drupal::logger('pi_comp')->notice('Batch finished. Success: @success', [
      '@success' => $success ? 'true' : 'false'
    ]);
    if ($success) {
      if (!empty($results['added'])) {
        $message = $this->t('Successfully added @added projects to PIMM.', [
          '@added' => $results['added'],
        ]);
        if (!empty($results['failed'])) {
          $message .= ' ' . $this->t('@failed projects failed to add.', [
              '@failed' => $results['failed'],
            ]);
        }
        $this->messenger()->addStatus($message);
      } else {
        $this->messenger()->addStatus($this->t('No new projects were added to PIMM.'));
      }
    }
    else {
      $this->messenger()->addError($this->t('An error occurred while adding projects to PIMM.'));
    }
  }
}
