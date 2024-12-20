<?php

namespace Drupal\pi_comp\Controller\Invitation;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\Core\Session\SessionManagerInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\DrupalDateTime;

class InvitationListCreationController extends ControllerBase {
  protected $formBuilder;
  protected $entityTypeManager;
  protected $requestStack;
  protected $sessionManager;
  protected $database;

  public function __construct(
    FormBuilderInterface $form_builder,
    EntityTypeManagerInterface $entity_type_manager,
    RequestStack $request_stack,
    SessionManagerInterface $session_manager,
    Connection $database
  ) {
    $this->formBuilder = $form_builder;
    $this->entityTypeManager = $entity_type_manager;
    $this->requestStack = $request_stack;
    $this->sessionManager = $session_manager;
    $this->database = $database;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('form_builder'),
      $container->get('entity_type.manager'),
      $container->get('request_stack'),
      $container->get('session_manager'),
      $container->get('database')
    );
  }

  public function build() {
    $build = [
      '#attached' => [
        'library' => [
          'pi_comp/pi_comp_styles',
        ],
      ],
    ];

    $this->sessionManager->start();
    $session = $this->requestStack->getCurrentRequest()->getSession();

    $filter_form = $this->formBuilder->getForm('Drupal\pi_comp\Form\Invitation\InvitationFilterForm');
    $build['filter_form'] = $filter_form;

    // Get selected projects from the request or session
    $selected_projects = $this->requestStack->getCurrentRequest()->request->all()['selected_projects'] ?? [];
    if (!empty($selected_projects)) {
      $session->set('selected_projects', $selected_projects);
    } else {
      $selected_projects = $session->get('selected_projects', []);
    }

    if (!is_array($selected_projects)) {
      $selected_projects = [$selected_projects];
    }

    // Pass selected projects to the form
    $build['form'] = $this->formBuilder->getForm('Drupal\pi_comp\Form\Invitation\InvitationPIMatchingForm', $selected_projects);

    // Always show the table
    $build['table'] = $this->buildTable($filter_form);

    $build['actions'] = [
      '#type' => 'details',
      '#title' => $this->t('Invitee List Actions'),
      '#open' => TRUE,
    ];

    $build['actions']['export'] = [
      '#type' => 'details',
      '#title' => $this->t('Export Invitee List'),
      'form' => $this->formBuilder->getForm('Drupal\pi_comp\Form\Invitation\InviteeListExportForm'),
    ];

    $build['actions']['add'] = [
      '#type' => 'details',
      '#title' => $this->t('Add Users to Invitee List'),
      'form' => $this->formBuilder->getForm('Drupal\pi_comp\Form\Invitation\ManualInviteeAddForm'),
    ];

    return $build;
  }

  private function buildTable($filter_form) {
    $header = [
      'checkbox' => [
        'data' => [
          '#type' => 'checkbox',
          '#title' => $this->t('Select all'),
          '#title_display' => 'invisible',
          '#attributes' => ['class' => ['select-all']],
        ],
      ],
      'title' => $this->t('Title'),
      'award_number' => $this->t('Award Number'),
      'lead_pi' => $this->t('Lead PI'),
      'institution' => $this->t('Institution'),
      'date_range' => $this->t('Date Range'),
    ];

    // First get tracked project IDs from pimm_tracked_projects
    $tracked_query = $this->database->select('pimm_tracked_projects', 'p')
      ->fields('p', ['nid'])
      ->condition('p.status', 'active');  // Only get active projects

    // Apply any additional filters from the filter form
    $this->applyFilters($tracked_query, $filter_form);

    $tracked_nids = $tracked_query->execute()->fetchCol();

    if (empty($tracked_nids)) {
      // Return empty table if no tracked projects
      return [
        '#type' => 'table',
        '#header' => $header,
        '#rows' => [],
        '#empty' => $this->t('No tracked projects available.'),
        '#attributes' => [
          'id' => 'pimm-project-table',
          'class' => ['table-responsive'],
        ],
      ];
    }

    // Load the actual nodes for these tracked projects
    $nodes = $this->entityTypeManager->getStorage('node')->loadMultiple($tracked_nids);

    $rows = [];
    foreach ($nodes as $node) {
      $lead_pi = $node->get('field_project_lead_pi_user')->entity;
      $lead_pi_name = $lead_pi ? $lead_pi->getDisplayName() : $node->get('field_project_lead_pi')->value;

      // Handle date range field
      $date_range = $node->get('field_project_performance_period')->getValue();
      $formatted_date_range = '';
      if (!empty($date_range)) {
        $start_date = new \DateTime($date_range[0]['value']);
        $end_date = new \DateTime($date_range[0]['end_value']);
        $formatted_date_range = $start_date->format('m/d/Y') . ' - ' . $end_date->format('m/d/Y');
      }

      $rows[] = [
        'checkbox' => [
          'data' => [
            '#type' => 'checkbox',
            '#name' => 'selected_projects[]',
            '#return_value' => $node->id(),
          ],
        ],
        'title' => $node->getTitle(),
        'award_number' => $node->get('field_award_number')->entity ? $node->get('field_award_number')->entity->label() : '',
        'lead_pi' => $lead_pi_name,
        'institution' => $node->get('field_project_institution')->value,
        'date_range' => $formatted_date_range,
      ];
    }

    $build = [
      '#attached' => [
        'library' => [
          'pi_comp/pi_comp_styles',
        ],
      ],
      '#type' => 'form',
      '#method' => 'post',
      'table_wrapper' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['table-responsive']],
        'table' => [
          '#type' => 'table',
          '#header' => $header,
          '#rows' => $rows,
          '#empty' => $this->t('No tracked projects match the filter criteria.'),
          '#attributes' => ['id' => 'pimm-project-table'],
        ],
      ],
      'actions' => [
        '#type' => 'actions',
        'submit' => [
          '#type' => 'submit',
          '#value' => $this->t('Create from Selected'),
          '#submit' => ['::createFromSelected'],
        ],
      ],
    ];

    $build['pager'] = [
      '#type' => 'pager',
    ];

    $build['#attached']['library'][] = 'pi_comp/invitee-table';

    return $build;
  }

  private function applyFilters($query, $filter_form) {
    $filters = $this->requestStack->getCurrentRequest()->getSession()->get('project_filters', []);
    if (empty($filters)) {
      return;
    }

    $field = $filters['field'] ?? NULL;
    $operator = $filters['operator'] ?? NULL;
    $value = $filters['value'] ?? NULL;

    if (!$field || !$operator || !$value) {
      return;
    }

    // Join with node table to filter on node fields
    $query->join('node_field_data', 'n', 'p.nid = n.nid');

    switch ($field) {
      case 'field_project_performance_period':
        $query->join('node__field_project_performance_period', 'pp', 'p.nid = pp.entity_id');
        $date = new DrupalDateTime($value);
        $formatted_date = $date->format('Y-m-d');

        if ($operator === 'before') {
          $query->condition('pp.field_project_performance_period_end_value', $formatted_date, '<=');
        }
        elseif ($operator === 'after') {
          $query->condition('pp.field_project_performance_period_value', $formatted_date, '>=');
        }
        elseif ($operator === 'between' && isset($filters['value2'])) {
          $end_date = new DrupalDateTime($filters['value2']);
          $formatted_end_date = $end_date->format('Y-m-d');
          $query->condition('pp.field_project_performance_period_value', $formatted_date, '>=');
          $query->condition('pp.field_project_performance_period_end_value', $formatted_end_date, '<=');
        }
        break;

      case 'title':
        switch ($operator) {
          case 'contains':
            $query->condition('n.title', '%' . $this->database->escapeLike($value) . '%', 'LIKE');
            break;
          case 'starts_with':
            $query->condition('n.title', $this->database->escapeLike($value) . '%', 'LIKE');
            break;
          case 'ends_with':
            $query->condition('n.title', '%' . $this->database->escapeLike($value), 'LIKE');
            break;
        }
        break;

      case 'field_project_institution':
      case 'field_project_lead_pi':
        $field_table = "node__$field";
        $field_column = "{$field}_value";
        $query->join($field_table, 'f', 'p.nid = f.entity_id');
        switch ($operator) {
          case 'contains':
            $query->condition("f.$field_column", '%' . $this->database->escapeLike($value) . '%', 'LIKE');
            break;
          case 'starts_with':
            $query->condition("f.$field_column", $this->database->escapeLike($value) . '%', 'LIKE');
            break;
          case 'ends_with':
            $query->condition("f.$field_column", '%' . $this->database->escapeLike($value), 'LIKE');
            break;
        }
        break;
    }
  }

  public function createFromSelected(array &$form, FormStateInterface $form_state) {
    $selected_projects = $form_state->getValue('selected_projects', []);
    $this->requestStack->getCurrentRequest()->getSession()->set('selected_projects', $selected_projects);
    $form_state->setRedirect('pi_comp.invitation_list_create');
  }
}
