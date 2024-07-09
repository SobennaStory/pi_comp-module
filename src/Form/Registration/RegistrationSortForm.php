<?php

namespace Drupal\pi_comp\Form\Registration;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class RegistrationSortForm extends FormBase {

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
    return 'registration_sort_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state, $registration_list_id = NULL, $webforms = []) {
    $this->messenger()->addStatus($this->t('Invitee List ID: @id', ['@id' => $registration_list_id]));
    $this->messenger()->addStatus($this->t('Number of webforms: @count', ['@count' => count($webforms)]));

    foreach ($webforms as $webform) {
      $this->messenger()->addStatus($this->t('Webform ID: @id, Label: @label', [
        '@id' => $webform->id(),
        '@label' => $webform->label(),
      ]));
    }
    $form['registration_list_id'] = [
      '#type' => 'hidden',
      '#value' => $registration_list_id,
    ];

    $webform_options = [];
    $field_options = [];

    foreach ($webforms as $webform) {
      $webform_options[$webform->id()] = $webform->label();
      $elements = $webform->getElementsDecodedAndFlattened();
      foreach ($elements as $key => $element) {
        if (isset($element['#title'])) {
          $field_options[$webform->id()][$key] = $element['#title'];
        }
      }
    }

    $form['webform'] = [
      '#type' => 'select',
      '#title' => $this->t('Select Webform'),
      '#options' => $webform_options,
      '#required' => TRUE,
      '#ajax' => [
        'callback' => '::updateFieldOptions',
        'wrapper' => 'field-wrapper',
      ],
    ];

    $form['field'] = [
      '#type' => 'select',
      '#title' => $this->t('Sort by Field'),
      '#options' => $field_options[$form_state->getValue('webform')] ?? [],
      '#prefix' => '<div id="field-wrapper">',
      '#suffix' => '</div>',
      '#states' => [
        'visible' => [
          ':input[name="webform"]' => ['!value' => ''],
        ],
      ],
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Sort'),
    ];
    $form['#attached']['library'][] = 'pi_comp/registration-sort-form';

    return $form;
  }

  public function updateFieldOptions(array &$form, FormStateInterface $form_state) {
    return $form['field'];
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $registration_list_id = $form_state->getValue('registration_list_id');
    $webform_id = $form_state->getValue('webform');
    $field = $form_state->getValue('field');

    $form_state->setRedirect('pi_comp.registration_list_view', [
      'registration_list_id' => $registration_list_id,
      'webform_id' => $webform_id,
      'sort_field' => $field,
    ]);
  }
}
