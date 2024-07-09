<?php

namespace Drupal\pi_comp\Controller\Invitation;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\Core\Session\SessionManagerInterface;

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
    $build = [];

    $this->sessionManager->start();
    $session = $this->requestStack->getCurrentRequest()->getSession();

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

    // Only show the table, export form, and manual add form if no projects are selected
    if (empty($selected_projects)) {
      $build['table'] = $this->buildTable();
      $build['export_form'] = $this->formBuilder->getForm('Drupal\pi_comp\Form\Invitation\InviteeListExportForm');
      $build['manual_add_form'] = $this->formBuilder->getForm('Drupal\pi_comp\Form\Invitation\ManualInviteeAddForm');
    }

    return $build;
  }

  private function buildTable() {
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
      'project_type' => $this->t('Project Type'),
      'core_areas' => $this->t('Core Areas'),
    ];

    $query = $this->entityTypeManager->getStorage('node')->getQuery()
      ->condition('type', 'project')
      ->sort('created', 'DESC')
      ->pager(50)
      ->accessCheck(false);
    $nids = $query->execute();
    $nodes = $this->entityTypeManager->getStorage('node')->loadMultiple($nids);

    $rows = [];
    foreach ($nodes as $node) {
      $lead_pi = $node->get('field_project_lead_pi_user')->entity;
      $lead_pi_name = $lead_pi ? $lead_pi->getDisplayName() : $node->get('field_project_lead_pi')->value;

      $core_areas = [];
      foreach ($node->get('field_project_core_areas')->referencedEntities() as $term) {
        $core_areas[] = $term->label();
      }

      $project_type = $node->get('field_project_type')->entity;

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
        'project_type' => $project_type ? $project_type->label() : '',
        'core_areas' => implode(', ', $core_areas),
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
          '#value' => $this->t('Match Selected Projects'),
        ],
      ],
    ];

    $build['pager'] = [
      '#type' => 'pager',
    ];

    $build['#attached']['library'][] = 'pi_comp/invitee-table';

    return $build;
  }
}
