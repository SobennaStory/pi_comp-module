<?php

namespace Drupal\pi_comp\Form\Invitation;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class ManualInviteeAddForm extends FormBase {

  protected $entityTypeManager;

  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')
    );
  }

  public function getFormId() {
    return 'manual_invitee_add_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $invitee_lists = $this->entityTypeManager->getStorage('node')
      ->loadByProperties(['type' => 'invitee_list']);

    $options = [];
    foreach ($invitee_lists as $list) {
      $options[$list->id()] = $list->label();
    }

    $form['invitee_list'] = [
      '#type' => 'select',
      '#title' => $this->t('Select Invitee List'),
      '#options' => $options,
      '#empty_option' => $this->t('- Select -'),
      '#required' => TRUE,
    ];

    $form['users'] = [
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('Add Users'),
      '#target_type' => 'user',
      '#tags' => TRUE,
      '#description' => $this->t('Enter usernames to add to the invitee list.'),
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add Users to Invitee List'),
    ];

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $invitee_list_id = $form_state->getValue('invitee_list');
    $user_ids = array_column($form_state->getValue('users'), 'target_id');

    $invitee_list = $this->entityTypeManager->getStorage('node')->load($invitee_list_id);

    if ($invitee_list) {
      $current_users = $invitee_list->get('field_users')->getValue();
      $current_user_ids = array_column($current_users, 'target_id');

      $new_user_ids = array_unique(array_merge($current_user_ids, $user_ids));

      $invitee_list->set('field_users', $new_user_ids);
      $invitee_list->save();

      $added_count = count($new_user_ids) - count($current_user_ids);
      $this->messenger()->addStatus($this->t('@count users added to the invitee list.', ['@count' => $added_count]));
    } else {
      $this->messenger()->addError($this->t('Invalid invitee list selected.'));
    }
  }
}
