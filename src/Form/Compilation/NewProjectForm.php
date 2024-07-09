<?php

namespace Drupal\pi_comp\Form\Compilation;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Term;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form for creating a new project.
 */
class NewProjectForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'new_project_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Title'),
      '#required' => TRUE,
    ];

    $form['body'] = [
      '#type' => 'text_format',
      '#title' => $this->t('Abstract'),
      '#format' => 'full_html',
    ];

    $form['field_award_number'] = [
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('Award Number'),
      '#target_type' => 'taxonomy_term',
      '#selection_settings' => [
        'target_bundles' => ['award_numbers'],
      ],
    ];

    $form['field_project_co_pis_user'] = [
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('Co-PIs'),
      '#target_type' => 'user',
      '#multiple' => TRUE,
    ];

    $form['field_project_co_pis'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Co-PIs (text)'),
    ];

    $form['field_project_core_areas'] = [
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('Core Areas'),
      '#target_type' => 'taxonomy_term',
      '#selection_settings' => [
        'target_bundles' => ['umbrella_groups'],
      ],
      '#multiple' => TRUE,
    ];

    $form['field_display_videos'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Display Videos in Hero'),
    ];

    $form['field_project_institution'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Institution'),
    ];

    $form['field_project_keywords'] = [
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('Keywords'),
      '#target_type' => 'taxonomy_term',
      '#selection_settings' => [
        'target_bundles' => ['tools_keywords'],
      ],
      '#multiple' => TRUE,
    ];

    $form['field_project_lead_pi_user'] = [
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('Lead PI'),
      '#target_type' => 'user',
    ];

    $form['field_project_lead_pi'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Lead PI (text)'),
    ];

    $form['field_project_report_url'] = [
      '#type' => 'url',
      '#title' => $this->t('Outcomes Report URL'),
    ];

    $form['field_project_performance_period'] = [
      '#type' => 'daterange',
      '#title' => $this->t('Performance Period'),
    ];

    $form['field_project_type'] = [
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('Project Type'),
      '#target_type' => 'taxonomy_term',
      '#selection_settings' => [
        'target_bundles' => ['umbrella_groups'],
      ],
    ];

    $form['field_project_url'] = [
      '#type' => 'url',
      '#title' => $this->t('Project URL'),
    ];

    $form['field_project_researchers'] = [
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('Researchers'),
      '#target_type' => 'user',
      '#multiple' => TRUE,
    ];

    $form['field_project_sponsor'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Sponsor'),
    ];

    $form['field_project_sponsor_url'] = [
      '#type' => 'url',
      '#title' => $this->t('Sponsor Award URL'),
    ];

    $form['field_tags'] = [
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('Tags'),
      '#target_type' => 'taxonomy_term',
      '#selection_settings' => [
        'target_bundles' => ['keywords', 'topics'],
      ],
      '#multiple' => TRUE,
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Create Project'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();

    $node = Node::create([
      'type' => 'project',
      'title' => $values['title'],
      'body' => $values['body'],
      'field_award_number' => $values['field_award_number'],
      'field_project_co_pis_user' => $values['field_project_co_pis_user'],
      'field_project_co_pis' => $values['field_project_co_pis'],
      'field_project_core_areas' => $values['field_project_core_areas'],
      'field_display_videos' => $values['field_display_videos'],
      'field_project_institution' => $values['field_project_institution'],
      'field_project_keywords' => $values['field_project_keywords'],
      'field_project_lead_pi_user' => $values['field_project_lead_pi_user'],
      'field_project_lead_pi' => $values['field_project_lead_pi'],
      'field_project_report_url' => $values['field_project_report_url'],
      'field_project_performance_period' => $values['field_project_performance_period'],
      'field_project_type' => $values['field_project_type'],
      'field_project_url' => $values['field_project_url'],
      'field_project_researchers' => $values['field_project_researchers'],
      'field_project_sponsor' => $values['field_project_sponsor'],
      'field_project_sponsor_url' => $values['field_project_sponsor_url'],
      'field_tags' => $values['field_tags'],
    ]);

    $node->save();

    $this->messenger()->addMessage($this->t('Project created successfully.'));
  }
}
