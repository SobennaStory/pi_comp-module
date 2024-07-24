<?php

namespace Drupal\pi_comp\Form\Invitation;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RequestStack;

class InvitationFilterForm extends FormBase {
  protected $entityTypeManager;
  protected $entityFieldManager;
  protected $requestStack;

  public function __construct(EntityTypeManagerInterface $entity_type_manager, EntityFieldManagerInterface $entity_field_manager, RequestStack $request_stack) {
    $this->entityTypeManager = $entity_type_manager;
    $this->entityFieldManager = $entity_field_manager;
    $this->requestStack = $request_stack;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('entity_field.manager'),
      $container->get('request_stack')
    );
  }

  public function getFormId() {
    return 'invitation_filter_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['filters'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Filter Projects'),
    ];

    $form['filters']['field'] = [
      '#type' => 'select',
      '#title' => $this->t('Field'),
      '#options' => $this->getFilterableFields(),
      '#ajax' => [
        'callback' => '::updateFilterOptions',
        'wrapper' => 'filter-options-wrapper',
      ],
    ];

    $form['filters']['options_wrapper'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'filter-options-wrapper'],
    ];

    $selected_field = $form_state->getValue('field');
    $this->buildFilterOptions($form, $form_state, $selected_field);

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Apply Filters'),
    ];

    return $form;
  }

  public function updateFilterOptions(array &$form, FormStateInterface $form_state) {
    return $form['filters']['options_wrapper'];
  }

  protected function buildFilterOptions(array &$form, FormStateInterface $form_state, $field) {
    $options = &$form['filters']['options_wrapper'];
    $options['#tree'] = TRUE;

    if (!$field) {
      return;
    }

    $fields = $this->entityFieldManager->getFieldDefinitions('node', 'project');
    $field_definition = $fields[$field] ?? null;

    if (!$field_definition) {
      return;
    }

    $field_type = $field_definition->getType();

    switch ($field_type) {
      case 'daterange':
        $options['operator'] = [
          '#type' => 'select',
          '#title' => $this->t('Operator'),
          '#options' => [
            'before' => $this->t('Before'),
            'after' => $this->t('After'),
            'between' => $this->t('Between'),
          ],
        ];
        $options['value'] = [
          '#type' => 'date',
          '#title' => $this->t('Date'),
        ];
        $options['value2'] = [
          '#type' => 'date',
          '#title' => $this->t('End Date'),
          '#states' => [
            'visible' => [
              ':input[name="options_wrapper[operator]"]' => ['value' => 'between'],
            ],
          ],
        ];
        break;

      case 'string':
      case 'text':
      case 'text_long':
        $options['operator'] = [
          '#type' => 'select',
          '#title' => $this->t('Operator'),
          '#options' => [
            'contains' => $this->t('Contains'),
            'starts_with' => $this->t('Starts with'),
            'ends_with' => $this->t('Ends with'),
          ],
        ];
        $options['value'] = [
          '#type' => 'textfield',
          '#title' => $this->t('Value'),
        ];
        break;
    }
  }

  protected function getFilterableFields() {
    $filterable_fields = [
      'title' => $this->t('Title'),
      'field_project_performance_period' => $this->t('Performance Period'),
      'field_project_institution' => $this->t('Institution'),
      'field_project_lead_pi' => $this->t('Lead PI'),
    ];

    return $filterable_fields;
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $filters = $form_state->getValue('options_wrapper');
    $field = $form_state->getValue('field');

    $active_filters = [
      'field' => $field,
      'operator' => $filters['operator'],
      'value' => $filters['value'],
    ];

    if (isset($filters['value2'])) {
      $active_filters['value2'] = $filters['value2'];
    }

    $this->requestStack->getCurrentRequest()->getSession()->set('project_filters', $active_filters);

    // Maintain selected projects
    $selected_projects = $this->requestStack->getCurrentRequest()->request->all()['selected_projects'] ?? [];
    $this->requestStack->getCurrentRequest()->getSession()->set('selected_projects', $selected_projects);

    $form_state->setRedirect('pi_comp.invitation_list');
  }
}
