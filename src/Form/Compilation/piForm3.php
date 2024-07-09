<?php

namespace Drupal\pi_comp\Form\Compilation;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\node\Entity\Node;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\pi_comp\ParserInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\csv_importer\Plugin\ImporterManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
/**
 * Provides CSV importer form.
 */
class piForm3 extends FormBase {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity field manager service.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * The entity bundle info service.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $entityBundleInfo;

  /**
   * The parser service.
   *
   * @var \Drupal\pi_comp\ParserInterface
   */
  protected $parser;

  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * The importer plugin manager service.
   *
   * @var \Drupal\csv_importer\Plugin\ImporterManager
   */
  protected $importer;

  /**
   * ImporterForm class constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager service.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_bundle_info
   *   The entity bundle info service.
   * @param \Drupal\pi_comp\ParserInterface $parser
   *   The parser service.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer service.
   * @param \Drupal\csv_importer\Plugin\ImporterManager $importer
   *   The importer plugin manager service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, EntityFieldManagerInterface $entity_field_manager, EntityTypeBundleInfoInterface $entity_bundle_info, ParserInterface $parser, RendererInterface $renderer, ImporterManager $importer) {
    $this->entityTypeManager = $entity_type_manager;
    $this->entityFieldManager = $entity_field_manager;
    $this->entityBundleInfo = $entity_bundle_info;
    $this->parser = $parser;
    $this->renderer = $renderer;
    $this->importer = $importer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('entity_field.manager'),
      $container->get('entity_type.bundle.info'),
      $container->get('pi_comp.parser'),
      $container->get('renderer'),
      $container->get('plugin.manager.importer')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'pi_comp_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['importer'] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => 'pi-importer',
      ],
    ];
    $form['importer']['delimiter'] = [
      '#type' => 'select',
      '#title' => $this->t('Select delimiter'),
      '#options' => [
        ',' => ',',
        '~' => '~',
        ';' => ';',
        ':' => ':',
      ],
      '#default_value' => ',',
      '#required' => TRUE,
      '#weight' => 10,
    ];


    //


    $form['importer']['csv'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Select CSV file'),
      '#required' => TRUE,
      '#autoupload' => TRUE,
      '#upload_validators' => ['file_validate_extensions' => ['csv']],
      '#weight' => 10,
    ];

    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Import'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Get the CSV file and parse it.
    $csv = current($form_state->getValue('csv'));
    $delimiter = $form_state->getValue('delimiter');
    $csv_parse = $this->parser->getCsvById($csv, $delimiter);

    // Define the column mappings for both CSV formats
    $column_mappings = [
      'format1' => [
        'AwardNumber' => 'field_award_number',
        'Title' => 'title',
        'NSFOrganization' => 'field_project_institution',
        'Program(s)' => 'field_project_type',
        'EndDate' => 'field_project_performance_period',
        'PrincipalInvestigator' => 'field_project_lead_pi',
        'PIEmailAddress' => 'field_piemailaddress',
        'Co-PIName(s)' => 'field_project_co_pis',
        'Abstract' => 'body',
      ],
      'format2' => [
        'title' => 'title',
        'field_project_lead_pi' => 'field_project_lead_pi',
        'field_project_performance_period' => 'field_project_performance_period',
        'Award Number' => 'field_award_number',
      ],
    ];

    // Determine which format we're dealing with
    $format = isset($csv_parse[0]['AwardNumber']) ? 'format1' : 'format2';
    $mapping = $column_mappings[$format];

    foreach ($csv_parse as $csv_entry) {
      // Load existing project node by award number.
      $nodes = $this->entityTypeManager->getStorage('node')->loadByProperties([
        'type' => 'project',
        'field_award_number' => $csv_entry[$format == 'format1' ? 'AwardNumber' : 'Award Number'],
      ]);

      $node = reset($nodes);

      // If no existing node, create a new one.
      if (!$node) {
        $node = $this->entityTypeManager->getStorage('node')->create([
          'type' => 'project',
        ]);
      }

      // Map CSV columns to Drupal fields.
      foreach ($mapping as $csv_column => $drupal_field) {
        if (isset($csv_entry[$csv_column])) {
          // Check if the field exists on the node
          if ($node->hasField($drupal_field)) {
            if ($drupal_field == 'field_project_performance_period') {
              // Handle date range field
              $date_string = trim($csv_entry[$csv_column]);
              try {
                $end_date = DrupalDateTime::createFromFormat('Y-m-d', $date_string);
                if (!$end_date) {
                  // Try another common format
                  $end_date = DrupalDateTime::createFromFormat('m/d/Y', $date_string);
                }
                if (!$end_date) {
                  throw new \Exception("Unable to parse date: $date_string");
                }
                $start_date = clone $end_date;
                $start_date->modify('-1 year'); // Assume 1 year project duration
                $node->set($drupal_field, [
                  'value' => $start_date->format('Y-m-d'),
                  'end_value' => $end_date->format('Y-m-d'),
                ]);
              } catch (\Exception $e) {
                // Log the error and set a default date range
                \Drupal::logger('pi_comp')->error('Failed to parse date: @date. Error: @error', [
                  '@date' => $date_string,
                  '@error' => $e->getMessage(),
                ]);
                // Set a default date range (e.g., current year)
                $current_year = date('Y');
                $node->set($drupal_field, [
                  'value' => "$current_year-01-01",
                  'end_value' => "$current_year-12-31",
                ]);
              }
            } elseif ($drupal_field == 'body') {
              $node->set($drupal_field, [
                'value' => $csv_entry[$csv_column],
                'format' => 'full_html',
              ]);
            } else {
              $node->set($drupal_field, $csv_entry[$csv_column]);
            }
          } else {
            \Drupal::logger('pi_comp')->warning('Field @field does not exist on the project content type.', [
              '@field' => $drupal_field,
            ]);
          }
        }
      }
      $node->save();
    }

    $this->messenger()->addMessage($this->t('CSV entries have been imported as projects.'));
  }
}

