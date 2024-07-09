<?php

namespace Drupal\pi_comp\Form\Invitation;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;

class InviteeListExportForm extends FormBase {

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
    return 'invitee_list_export_form';
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

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Export CSV'),
    ];

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $invitee_list_id = $form_state->getValue('invitee_list');
    $invitee_list = $this->entityTypeManager->getStorage('node')->load($invitee_list_id);

    if ($invitee_list) {
      $users = $invitee_list->get('field_users')->referencedEntities();

      $csv_data = "Name,Email\n";
      foreach ($users as $user) {
        $csv_data .= $user->getAccountName() . ',' . $user->getEmail() . "\n";
      }

      $filename = 'invitee_list_' . $invitee_list_id . '.csv';

      $response = new Response($csv_data);
      $response->headers->set('Content-Type', 'text/csv');
      $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');

      $form_state->setResponse($response);
    } else {
      $this->messenger()->addError($this->t('Invalid invitee list selected.'));
    }
  }
}
