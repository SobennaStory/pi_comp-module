<?php

namespace Drupal\pi_comp\Controller\Invitation;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\Core\Session\SessionManagerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\Core\Datetime\DrupalDateTime;

class InvitationListCreationController extends ControllerBase {
  protected $formBuilder;
  protected $entityTypeManager;
  protected $requestStack;
  protected $sessionManager;

  public function __construct(FormBuilderInterface $form_builder, EntityTypeManagerInterface $entity_type_manager, RequestStack $request_stack, SessionManagerInterface $session_manager) {
    $this->formBuilder = $form_builder;
    $this->entityTypeManager = $entity_type_manager;
    $this->requestStack = $request_stack;
    $this->sessionManager = $session_manager;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('form_builder'),
      $container->get('entity_type.manager'),
      $container->get('request_stack'),
      $container->get('session_manager')
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

    $query = $this->entityTypeManager->getStorage('node')->getQuery()
      ->condition('type', 'project')
      ->sort('created', 'DESC')
      ->pager(50)
      ->accessCheck(false);

    $this->applyFilters($query, $filter_form);

    $nids = $query->execute();
    $nodes = $this->entityTypeManager->getStorage('node')->loadMultiple($nids);

    $rows = [];
    foreach ($nodes as $node) {
      $lead_pi = $node->get('field_project_lead_pi_user')->entity;
      $lead_pi_name = $lead_pi ? $lead_pi->getDisplayName() : $node->get('field_project_lead_pi')->value;

      // Correctly handle the date range field
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
          '#empty' => $this->t('No content available.'),
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

  public function createFromSelected(array &$form, FormStateInterface $form_state) {
    $selected_projects = $form_state->getValue('selected_projects', []);
    $this->requestStack->getCurrentRequest()->getSession()->set('selected_projects', $selected_projects);
    $form_state->setRedirect('pi_comp.invitation_list_create');
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

    switch ($field) {
      case 'field_project_performance_period':
        $start_date_field = $field . '.value';
        $end_date_field = $field . '.end_value';
        $date = new DrupalDateTime($value);
        $formatted_date = $date->format('Y-m-d');

        if ($operator === 'before') {
          $query->condition($end_date_field, $formatted_date, '<=');
        } elseif ($operator === 'after') {
          $query->condition($start_date_field, $formatted_date, '>=');
        } elseif ($operator === 'between' && isset($filters['value2'])) {
          $end_date = new DrupalDateTime($filters['value2']);
          $formatted_end_date = $end_date->format('Y-m-d');
          $query->condition($start_date_field, $formatted_date, '>=');
          $query->condition($end_date_field, $formatted_end_date, '<=');
        }
        break;

      case 'title':
      case 'field_project_institution':
      case 'field_project_lead_pi':
        switch ($operator) {
          case 'contains':
            $query->condition($field, '%' . $value . '%', 'LIKE');
            break;
          case 'starts_with':
            $query->condition($field, $value . '%', 'LIKE');
            break;
          case 'ends_with':
            $query->condition($field, '%' . $value, 'LIKE');
            break;
        }
        break;
    }
  }
}
