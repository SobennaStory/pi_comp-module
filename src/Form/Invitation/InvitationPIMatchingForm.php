<?php

namespace Drupal\pi_comp\Form\Invitation;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Session\SessionManagerInterface;

class InvitationPIMatchingForm extends FormBase {

  protected $entityTypeManager;
  protected $sessionManager;

  public function __construct(EntityTypeManagerInterface $entity_type_manager, SessionManagerInterface $session_manager) {
    $this->entityTypeManager = $entity_type_manager;
    $this->sessionManager = $session_manager;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('session_manager')
    );
  }

  public function getFormId() {
    return 'invitation_pi_matching_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state, array $selected_projects = []) {
    $this->sessionManager->start();
    $session = $this->getRequest()->getSession();

    if (empty($selected_projects)) {
      $selected_projects = $session->get('selected_projects', []);
    }

    if (empty($selected_projects)) {
      $form['message'] = [
        '#markup' => $this->t('Please select projects from the table below.'),
      ];
      return $form;
    }

    $projects_users = $this->getUsersFromProjects($selected_projects);

    $form['projects'] = [
      '#type' => 'details',
      '#title' => $this->t('Selected Projects and Users'),
      '#open' => TRUE,
    ];

    foreach ($projects_users as $project_title => $project_info) {
      $form['projects'][$project_title] = [
        '#type' => 'details',
        '#title' => $project_title,
        '#open' => TRUE,
      ];

      $form['projects'][$project_title]['users'] = [
        '#theme' => 'table',
        '#header' => [
          $this->t('Name'),
          $this->t('Role'),
        ],
        '#rows' => [],
      ];

      foreach ($project_info['users'] as $user_info) {
        $form['projects'][$project_title]['users']['#rows'][] = [
          $user_info['user']->getAccountName(),
          $user_info['role'],
        ];
      }
    }

    $form['invitee_list_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Invitee List Name'),
      '#required' => TRUE,
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Create Invitation List'),
    ];

    $form['actions']['cancel'] = [
      '#type' => 'submit',
      '#value' => $this->t('Cancel'),
      '#submit' => ['::cancelForm'],
      '#limit_validation_errors' => [],
    ];

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $invitee_list_name = $values['invitee_list_name'];

    $projects_users = $this->getUsersFromProjects($this->getRequest()->getSession()->get('selected_projects', []));

    $user_ids = [];
    foreach ($projects_users as $project_info) {
      foreach ($project_info['users'] as $user_info) {
        $user_ids[$user_info['user']->id()] = $user_info['user']->id();
      }
    }

    $invitee_list = $this->entityTypeManager->getStorage('node')->create([
      'type' => 'invitee_list',
      'title' => $invitee_list_name,
      'field_users' => array_map(function($uid) {
        return ['target_id' => $uid];
      }, $user_ids),
    ]);
    $invitee_list->save();

    $this->messenger()->addStatus($this->t('Created invitee list "@name" with @count users.', [
      '@name' => $invitee_list_name,
      '@count' => count($user_ids),
    ]));

    $this->getRequest()->getSession()->remove('selected_projects');

    $form_state->setRedirect('pi_comp.invitation_list');
  }

  public function cancelForm(array &$form, FormStateInterface $form_state) {
    $this->messenger()->addStatus($this->t('Operation cancelled.'));
    $this->getRequest()->getSession()->remove('selected_projects');
    $form_state->setRedirect('pi_comp.invitation_list');
  }

  private function getUsersFromProjects($selected_projects) {
    $projects_users = [];
    foreach ($selected_projects as $nid) {
      $node = $this->entityTypeManager->getStorage('node')->load($nid);
      if ($node) {
        $project_title = $node->getTitle();
        $projects_users[$project_title] = [
          'nid' => $nid,
          'users' => [],
        ];

        if ($node->hasField('field_project_lead_pi_user')) {
          $lead_pi_user = $node->get('field_project_lead_pi_user')->entity;
          if ($lead_pi_user) {
            $projects_users[$project_title]['users'][] = [
              'user' => $lead_pi_user,
              'role' => $this->t('PI'),
            ];
          }
        }
        if ($node->hasField('field_project_co_pis_user')) {
          $co_pi_users = $node->get('field_project_co_pis_user')->referencedEntities();
          foreach ($co_pi_users as $co_pi_user) {
            $projects_users[$project_title]['users'][] = [
              'user' => $co_pi_user,
              'role' => $this->t('Co-PI'),
            ];
          }
        }
      }
    }
    return $projects_users;
  }
}
