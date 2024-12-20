<?php

namespace Drupal\pi_comp\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\pi_comp\Services\PimmProjectManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Form\FormBuilderInterface;

/**
 * Controller for the PIMM dashboard.
 */
class DashboardController extends ControllerBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The date formatter.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The form builder.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

  /**
   * The project manager service.
   *
   * @var \Drupal\pi_comp\Services\PimmProjectManagerInterface
   */
  protected $projectManager;

  /**
   * Constructs a DashboardController object.
   */

  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    AccountInterface $current_user,
    DateFormatterInterface $date_formatter,
    Connection $database,
    FormBuilderInterface $form_builder,
    PimmProjectManagerInterface $project_manager

  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->currentUser = $current_user;
    $this->dateFormatter = $date_formatter;
    $this->database = $database;
    $this->formBuilder = $form_builder;
    $this->projectManager = $project_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('current_user'),
      $container->get('date.formatter'),
      $container->get('database'),
      $container->get('form_builder'),
      $container->get('pi_comp.project_manager')
    );
  }

  /**
   * Builds the dashboard page.
   */
  public function dashboard() {
    $build = [
      '#theme' => 'pimm_dashboard',
      '#attached' => [
        'library' => [
          'pi_comp/dashboard',
        ],
      ],
      '#data' => [
        'projects' => [
          'count' => 0,
          'rows' => [],
          'add_form' => $this->formBuilder->getForm('Drupal\pi_comp\Form\Compilation\ProjectAddForm'),
          'bulk_form' => $this->formBuilder->getForm('Drupal\pi_comp\Form\Compilation\ProjectBulkAddForm'),
          'status_counts' => [],
        ],
        'invitations' => [
          'count' => 0,
          'total_users' => 0,
          'lists' => [],
        ],
        'registrations' => [
          'count' => 0,
          'lists' => [],
        ],
      ],
    ];

    try {
      // Get project data
      if ($this->database->schema()->tableExists('pimm_tracked_projects')) {
        $build['#data']['projects'] = $this->getProjectData();
      }

      // Get invitation data
      $build['#data']['invitations'] = $this->getInvitationData();

      // Get registration data if needed
      $build['#data']['registrations'] = $this->getRegistrationData();

    } catch (\Exception $e) {
      \Drupal::logger('pi_comp')->error('Dashboard error: @message', ['@message' => $e->getMessage()]);
    }

    return $build;
  }

  protected function getProjectRows() {
    $query = $this->database->select('pimm_tracked_projects', 'p')
      ->fields('p')
      ->orderBy('added_date', 'DESC');

    $tracked = $query->execute()->fetchAll();
    if (empty($tracked)) {
      return [];
    }

    $project_data = [];
    $forms = [];

    foreach ($tracked as $record) {
      if ($node = $this->entityTypeManager->getStorage('node')->load($record->nid)) {
        $statusForm = new \Drupal\pi_comp\Form\Compilation\ProjectStatusForm($this->entityTypeManager, $this->database, $record->nid);
        $removeForm = new \Drupal\pi_comp\Form\Compilation\ProjectRemoveForm($this->projectManager, $this->entityTypeManager,  $record->nid);
        $forms[$record->nid] = [
          'status' => $this->formBuilder->getForm(
            $statusForm, $record->nid
          ),
          'remove' => $this->formBuilder->getForm(
            $removeForm, $record->nid
          ),
        ];

        $project_data[] = [
          'id' => $record->id,
          'nid' => $record->nid,
          'title' => $node->getTitle(),
          'award_number' => $this->getAwardNumber($node),
          'pi' => $this->getProjectPI($node),
          'status' => $record->status,
          'added_date' => $this->dateFormatter->format($record->added_date, 'custom', 'Y-m-d'),
          'notes' => $record->notes,
        ];
      }
    }

    return [
      'rows' => $project_data,
      'forms' => $forms,
    ];
  }

  protected function getProjectData() {
    // Get basic project data
    $projectData = $this->getProjectRows();
    $projects = $projectData['rows'];

    // Count by status
    $statusCounts = [];
    foreach ($projects as $project) {
      $status = $project['status'];
      $statusCounts[$status] = ($statusCounts[$status] ?? 0) + 1;
    }

    return [
      'count' => count($projects),
      'rows' => $projects,
      'forms' => $projectData['forms'],
      'add_form' => $this->formBuilder->getForm('Drupal\pi_comp\Form\Compilation\ProjectAddForm'),
      'bulk_form' => $this->formBuilder->getForm('Drupal\pi_comp\Form\Compilation\ProjectBulkAddForm'),
      'status_counts' => $statusCounts,
    ];
  }



  /**
   * Gets invitation list data.
   */
  protected function getInvitationData() {
    try {
      $lists = [];
      $all_users = []; // Track all unique users across all lists

      // Query for invitation list nodes
      $query = $this->entityTypeManager->getStorage('node')->getQuery()
        ->condition('type', 'invitee_list')
        ->accessCheck(FALSE)
        ->sort('created', 'DESC');

      $nids = $query->execute();

      if (!empty($nids)) {
        $nodes = $this->entityTypeManager->getStorage('node')->loadMultiple($nids);

        foreach ($nodes as $node) {
          if ($node->hasField('field_users')) {
            $users = $node->get('field_users')->referencedEntities();
            $user_list = [];
            $list_user_ids = []; // Track unique users in this list

            foreach ($users as $user) {
              if ($user && !in_array($user->id(), $list_user_ids)) {
                $user_list[] = [
                  'id' => $user->id(),
                  'name' => $user->getDisplayName(),
                  'email' => $user->getEmail(),
                ];
                $list_user_ids[] = $user->id();
                $all_users[$user->id()] = true; // Add to global unique users
              }
            }

            $lists[] = [
              'id' => $node->id(),
              'title' => $node->label(),
              'created' => $this->dateFormatter->format($node->getCreatedTime(), 'custom', 'Y-m-d'),
              'user_count' => count($list_user_ids), // Count of unique users in this list
              'users' => $user_list,
            ];
          }
        }
      }

      return [
        'count' => count($lists),
        'total_users' => count($all_users), // Total unique users across all lists
        'lists' => $lists,
      ];

    } catch (\Exception $e) {
      \Drupal::logger('pi_comp')->error('Error getting invitation data: @error', ['@error' => $e->getMessage()]);
      return [
        'count' => 0,
        'total_users' => 0,
        'lists' => [],
      ];
    }
  }

  /**
   * Gets registration list data.
   */
  protected function getRegistrationData() {
    try {
      $lists = [];
      $total_users = 0;

      $query = $this->entityTypeManager->getStorage('node')->getQuery()
        ->condition('type', 'registration_list')
        ->accessCheck(FALSE)
        ->sort('created', 'DESC');

      $nids = $query->execute();
      if (!empty($nids)) {
        $nodes = $this->entityTypeManager->getStorage('node')->loadMultiple($nids);
        foreach ($nodes as $node) {
          if ($node->hasField('field_regusers') && $node->hasField('field_regwebforms')) {
            $registrants = $this->getRegistrants($node);
            $lists[] = [
              'id' => $node->id(),
              'title' => $node->label(),
              'created' => $this->dateFormatter->format($node->getCreatedTime(), 'custom', 'Y-m-d'),
              'registrant_count' => count($registrants),
              'webform_count' => count($this->getWebforms($node)),
              'registrants' => $registrants,
              'webforms' => $this->getWebforms($node),
            ];
            $total_users += count($registrants);
          }
        }
      }

      return [
        'count' => count($lists),
        'total_users' => $total_users,
        'lists' => $lists,
      ];
    }
    catch (\Exception $e) {
      \Drupal::logger('pi_comp')->error('Error getting registration data: @error', ['@error' => $e->getMessage()]);
      return ['count' => 0, 'total_users' => 0, 'lists' => []];
    }
  }

  /**
   * Gets registrants for a registration list.
   */
  protected function getRegistrants($node) {
    $registrants = [];
    foreach ($node->get('field_regusers')->referencedEntities() as $user) {
      $submission_info = $this->getSubmissionInfo($user->id(), $node);
      $registrants[] = [
        'name' => $user->getDisplayName(),
        'email' => $user->getEmail(),
        'status' => $submission_info['status'],
        'submitted' => $submission_info['date'] ?
          $this->dateFormatter->format($submission_info['date'], 'custom', 'Y-m-d') : '',
        'count' => $submission_info['count'],
      ];
    }
    return $registrants;
  }

  /**
   * Gets submission info for a user.
   */
  protected function getSubmissionInfo($uid, $node) {
    $webform_ids = array_column($node->get('field_regwebforms')->getValue(), 'target_id');
    if (empty($webform_ids)) {
      return ['status' => 'No webforms', 'date' => NULL, 'count' => 0];
    }

    $query = $this->database->select('webform_submission', 'ws')
      ->condition('ws.webform_id', $webform_ids, 'IN')
      ->condition('ws.uid', $uid)
      ->fields('ws', ['created'])
      ->orderBy('ws.created', 'DESC');

    $results = $query->execute()->fetchAll();
    $count = count($results);

    return [
      'status' => $count > 0 ? 'Submitted' : 'Pending',
      'date' => $count > 0 ? $results[0]->created : NULL,
      'count' => $count,
    ];
  }

  /**
   * Helper function to get award number.
   */
  protected function getAwardNumber($node) {
    if ($node->hasField('field_award_number') && !$node->get('field_award_number')->isEmpty()) {
      $award_field = $node->get('field_award_number');
      return $award_field->entity ? $award_field->entity->label() : $award_field->value;
    }
    return 'N/A';
  }

  /**
   * Helper function to get project PI.
   */
  protected function getProjectPI($node) {
    return $node->hasField('field_project_lead_pi') ?
      ($node->get('field_project_lead_pi')->value ?? 'N/A') : 'N/A';
  }

  /**
   * Helper function to get webforms.
   */
  protected function getWebforms($node) {
    $webforms = [];
    foreach ($node->get('field_regwebforms')->referencedEntities() as $webform) {
      $webforms[] = [
        'id' => $webform->id(),
        'title' => $webform->label(),
      ];
    }
    return $webforms;
  }

  /**
   * Helper function to get users from a node.
   */
  protected function getUsersFromNode($node, $field) {
    $users = [];
    foreach ($node->get($field)->referencedEntities() as $user) {
      $users[] = [
        'name' => $user->getDisplayName(),
        'email' => $user->getEmail(),
        'role' => $this->getUserRole($user),
      ];
    }
    return $users;
  }

  /**
   * Helper function to get user role.
   */
  protected function getUserRole($user) {
    $roles = $user->getRoles();
    unset($roles[array_search('authenticated', $roles)]);
    return !empty($roles) ? ucfirst(reset($roles)) : 'User';
  }

}
