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

    $pis = $this->getPrincipalInvestigators($selected_projects);
    $matched_users = $this->matchUsers($pis);
    $unmatched_pis = array_diff($pis, array_keys($matched_users));

    $form['matched'] = [
      '#theme' => 'item_list',
      '#title' => $this->t('Matched Users'),
      '#items' => array_map(function($user) {
        return $user->getAccountName();
      }, $matched_users),
      '#empty' => $this->t('No users matched.'),
    ];

    $form['unmatched'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Unmatched Principal Investigators'),
    ];

    foreach ($unmatched_pis as $index => $pi) {
      $form['unmatched']["pi_$index"] = [
        '#type' => 'fieldset',
        '#title' => $pi,
      ];

      $form['unmatched']["pi_$index"]['action'] = [
        '#type' => 'radios',
        '#options' => [
          'create' => $this->t('Create new user'),
          'match' => $this->t('Match to existing user'),
        ],
        '#default_value' => 'create',
      ];

      $form['unmatched']["pi_$index"]['existing_user'] = [
        '#type' => 'entity_autocomplete',
        '#target_type' => 'user',
        '#title' => $this->t('Existing user'),
        '#states' => [
          'visible' => [
            ':input[name="pi_' . $index . '[action]"]' => ['value' => 'match'],
          ],
        ],
      ];
    }

    $form['invitee_list_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Invitee List Name'),
      '#required' => TRUE,
    ];

    $form['create_users'] = [
      '#type' => 'submit',
      '#value' => $this->t('Create Selected Users'),
      '#submit' => ['::createUsers'],
    ];

    $form['clear_selection'] = [
      '#type' => 'submit',
      '#value' => $this->t('Clear Selection'),
      '#submit' => ['::clearSelection'],
    ];

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    // This method is no longer needed as we're displaying results immediately
  }

  public function createUsers(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $created_users = [];
    $matched_users = [];
    $existing_users = [];
    $all_user_ids = [];

    foreach ($values as $key => $value) {
      if (strpos($key, 'pi_') === 0) {
        $pi = $form['unmatched'][$key]['#title'];
        $action = $value['action'];

        if ($action === 'create') {
          $username = $this->formatUsername($pi);
          $existing_user = $this->entityTypeManager->getStorage('user')
            ->loadByProperties(['name' => $username]);

          if (empty($existing_user)) {
            try {
              $user = $this->entityTypeManager->getStorage('user')->create([
                'name' => $username,
                'mail' => strtolower(str_replace(' ', '.', $username)) . '@example.com',
                'pass' => 'ben',
                'status' => 1,
              ]);
              $user->save();
              $created_users[] = $user->getAccountName();
              $all_user_ids[] = $user->id();
            } catch (\Exception $e) {
              $this->messenger()->addError($this->t('Error creating user @username: @error', ['@username' => $username, '@error' => $e->getMessage()]));
            }
          } else {
            $user = reset($existing_user);
            $existing_users[] = $user->getAccountName();
            $all_user_ids[] = $user->id();
          }
        } elseif ($action === 'match' && !empty($value['existing_user'])) {
          $user = $this->entityTypeManager->getStorage('user')->load($value['existing_user']);
          if ($user) {
            $matched_users[] = $user->getAccountName();
            $all_user_ids[] = $user->id();
          }
        }
      }
    }

    $matched_list = $form['matched']['#items'];
    foreach ($matched_list as $username) {
      $user = $this->entityTypeManager->getStorage('user')
        ->loadByProperties(['name' => $username]);
      if (!empty($user)) {
        $user = reset($user);
        $all_user_ids[] = $user->id();
      }
    }

    $all_user_ids = array_unique($all_user_ids);
    $invitee_list_name = $form_state->getValue('invitee_list_name');
    $invitee_list = $this->entityTypeManager->getStorage('node')->create([
      'type' => 'invitee_list',
      'title' => $invitee_list_name,
      'field_users' => array_map(function($uid) {
        return ['target_id' => $uid];
      }, $all_user_ids),
    ]);
    $invitee_list->save();

    if (!empty($created_users)) {
      $this->messenger()->addStatus($this->t('Created user accounts for: @users', ['@users' => implode(', ', $created_users)]));
      $this->messenger()->addStatus($this->t('All new users have been created with the password "ben".'));
    }

    if (!empty($matched_users)) {
      $this->messenger()->addStatus($this->t('Matched PIs to existing users: @users', ['@users' => implode(', ', $matched_users)]));
    }

    if (!empty($existing_users)) {
      $this->messenger()->addStatus($this->t('User accounts already exist for: @users', ['@users' => implode(', ', $existing_users)]));
    }

    $this->messenger()->addStatus($this->t('Created invitee list "@name" with @count users.', [
      '@name' => $invitee_list_name,
      '@count' => count($all_user_ids),
    ]));

    $this->getRequest()->getSession()->remove('selected_projects');

    $form_state->setRedirect('pi_comp.invitation_list_creation');
  }

  public function clearSelection(array &$form, FormStateInterface $form_state) {
    $this->getRequest()->getSession()->remove('selected_projects');
    $this->messenger()->addStatus($this->t('Selection cleared.'));
    $form_state->setRedirect('pi_comp.invitation_list_creation');
  }

  private function formatUsername($pi) {
    $parts = preg_split('/,\s*/', $pi, 2);
    if (count($parts) === 2) {
      $lastname = trim($parts[0]);
      $firstname = trim($parts[1]);
      $firstname = preg_replace('/\s+[A-Z]\.?$/', '', $firstname);
      return $firstname . ' ' . $lastname;
    }
    return $pi;
  }

  private function getPrincipalInvestigators($selected_projects) {
    $pis = [];
    foreach ($selected_projects as $nid) {
      $node = $this->entityTypeManager->getStorage('node')->load($nid);
      if ($node && $node->hasField('field_project_lead_pi_user')) {
        $lead_pi_user = $node->get('field_project_lead_pi_user')->entity;
        if ($lead_pi_user) {
          $pis[] = $lead_pi_user->getAccountName();
        } elseif ($node->hasField('field_project_lead_pi')) {
          $pi = trim($node->get('field_project_lead_pi')->value);
          if (!empty($pi)) {
            $pis[] = $pi;
          }
        }
      }
    }
    return array_unique($pis);
  }

  private function matchUsers($pis) {
    $matched_users = [];

    foreach ($pis as $pi) {
      $user = $this->entityTypeManager->getStorage('user')
        ->loadByProperties(['name' => $pi]);
      if (!empty($user)) {
        $matched_users[$pi] = reset($user);
      }
    }

    return $matched_users;
  }
}
