<?php

namespace Drupal\pi_comp\Form\Registration;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class RegistrationListSelectForm extends FormBase {

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
    return 'registration_list_select_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $registration_lists = $this->entityTypeManager->getStorage('node')
      ->loadByProperties(['type' => 'registration_list']);

    $options = [];
    foreach ($registration_lists as $registration_list) {
      $options[$registration_list->id()] = $registration_list->label();
    }

    $form['registration_list'] = [
      '#type' => 'select',
      '#title' => $this->t('Select Registration List'),
      '#options' => $options,
      '#empty_option' => $this->t('- Select -'),
      '#required' => TRUE,
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('View Registration List'),
    ];

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $registration_list_id = $form_state->getValue('registration_list');
    if ($registration_list_id) {
      $form_state->setRedirect('pi_comp.registration_list_view', ['registration_list_id' => $registration_list_id]);
    }
    else {
      \Drupal::messenger()->addError($this->t('Please select an registration list.'));
    }
  }
}
