<?php

namespace Drupal\pi_comp\Form\Registration;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\Entity\Node;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Database\Connection;

//THIS CLASS USES A INVITEE LIST DROPDOWN, A FORM SELECTION, AND A TITLE INPUT FIELD TO CREATE A REGISTRATION LIST
class RegistrationListCreation extends FormBase {
  //inviteelistform
  protected $entityTypeManager;
  protected $database;

  public function __construct(EntityTypeManagerInterface $entity_type_manager, Connection $database) {
    $this->entityTypeManager = $entity_type_manager;
    $this->database = $database;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('database')
    );
  }
  public function getFormId() {
    return 'registration_list_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {

    $form['title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Title'),
      '#required' => TRUE,
    ];

    $invitee_lists = $this->entityTypeManager->getStorage('node')->loadByProperties(['type' => 'invitee_list']);

    $invitee_list_options = [];
    foreach ($invitee_lists as $invitee_list) {
      $invitee_list_options[$invitee_list->id()] = $invitee_list->label();
    }

    $form['invitee_list'] = [
      '#type' => 'select',
      '#title' => $this->t('Select Invitee List (List of invited Users)'),
      '#options' => $invitee_list_options,
      '#required' => TRUE,
    ];

    $webforms = $this->entityTypeManager->getStorage('webform')->loadMultiple();

    $webform_options = [];
    foreach ($webforms as $webform) {
      $webform_options[$webform->id()] = $webform->label();
    }

    $form['webforms'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Select Webforms (To see which users actually responded)'),
      '#options' => $webform_options,
      '#required' => TRUE,
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
    ];

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {

    $invitee_list_id = $form_state->getValue('invitee_list');
    $selected_webforms = array_filter($form_state->getValue('webforms'));

    $invitee_list = $this->entityTypeManager->getStorage('node')->load($invitee_list_id);
    $invitee_users = $invitee_list->get('field_users')->getValue();
    $invitee_user_ids = array_column($invitee_users, 'target_id');

    $user_ids = [];
    $user_names = [];

    $webform_names = [];

    if (empty($invitee_user_ids)) {
      $this->messenger()->addError($this->t('The selected invitee list is empty. Please add users to the invitee list before creating a registration list.'));
      return;
    }

    foreach ($selected_webforms as $webform_id) {
      $webform = $this->entityTypeManager->getStorage('webform')->load($webform_id);
      $webform_names[] = $webform->label();

      $query = $this->database->select('webform_submission', 'ws');
      $query->join('users_field_data', 'ufd', 'ws.uid = ufd.uid');
      $query->fields('ufd', ['uid', 'name']);
      $query->condition('ws.webform_id', $webform_id);
      if (!empty($invitee_user_ids)) {
        $query->condition('ufd.uid', $invitee_user_ids, 'IN');
      }
      $results = $query->execute()->fetchAll();

      foreach ($results as $result) {
        $user_ids[] = $result->uid;
        $user_names[] = $result->name;
      }
    }


    $user_ids = array_unique($user_ids);
    $user_names = array_unique($user_names);

    if (empty($user_ids)) {
      $this->messenger()->addWarning($this->t('No users found who have submitted the selected webforms from the invitee list. The registration list will be created without users.'));
    }

    $node = Node::create([
      'type' => 'registration_list',
      'title' => $form_state->getValue('title'),
      'field_regusers' => $user_ids,
      'field_inviteelist' => ['target_id' => $invitee_list_id],
    ]);

    foreach ($selected_webforms as $webform_id) {
      $node->field_regwebforms[] = ['target_id' => $webform_id];
    }

    $node->save();


    $this->messenger()->addStatus($this->t('Registration list "@title" has been created.', [
      '@title' => $form_state->getValue('title'),
    ]));

    $this->messenger()->addStatus($this->t('Invitee list used: @invitee_list', [
      '@invitee_list' => $invitee_list->label(),
    ]));

    $this->messenger()->addStatus($this->t('Webforms used: @webforms', [
      '@webforms' => implode(', ', $webform_names),
    ]));

    $this->messenger()->addStatus($this->t('@count users were added to the registration list: @users', [
      '@count' => count($user_names),
      '@users' => implode(', ', $user_names),
    ]));
  }
}

