<?php

namespace Drupal\pi_comp\Form\Compilation;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\node\Entity\Node;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\pi_comp\Services\ParserInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\csv_importer\Plugin\ImporterManager;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Ajax\AlertCommand;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides CSV importer form.
 */
class piForm3 extends FormBase {

  protected $entityTypeManager;
  protected $entityFieldManager;
  protected $entityBundleInfo;
  protected $parser;
  protected $renderer;
  protected $importer;

  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    EntityFieldManagerInterface $entity_field_manager,
    EntityTypeBundleInfoInterface $entity_bundle_info,
    ParserInterface $parser,
    RendererInterface $renderer,
    ImporterManager $importer
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->entityFieldManager = $entity_field_manager;
    $this->entityBundleInfo = $entity_bundle_info;
    $this->parser = $parser;
    $this->renderer = $renderer;
    $this->importer = $importer;
  }

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

  public function getFormId() {
    return 'pi_comp_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['submit_uploads'] = [
      '#type' => 'hidden',
      '#default_value' => 'false',
    ];

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

    $form['importer']['csv'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Select CSV file'),
      '#required' => TRUE,
      '#upload_validators' => ['file_validate_extensions' => ['csv']],
      '#weight' => 10,
    ];

    $form['actions']['#type'] = 'actions';

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Import'),
      '#button_type' => 'primary',
      '#ajax' => [
        'callback' => '::importCallback',
        'event' => 'click',
        'progress' => [
          'type' => 'throbber',
          'message' => $this->t('Processing...'),
        ],
      ],
    ];

    $form['actions']['confirm_import'] = [
      '#type' => 'submit',
      '#value' => $this->t('Confirm Import'),
      '#submit' => ['::submitForm'],
      '#attributes' => ['class' => ['js-hide']],
    ];

    $form['#attached']['library'][] = 'core/drupal.dialog.ajax';
    $form['#attached']['library'][] = 'pi_comp/csv_import_confirmation';

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    if ($form_state->getValue('submit_uploads') === 'true') {
      $csv = current($form_state->getValue('csv'));
      $delimiter = $form_state->getValue('delimiter');
      $csv_parse = $this->parser->getCsvById($csv, $delimiter);

      $format = isset($csv_parse[0]['AwardNumber']) ? 'format1' : 'format2';
      $mapping = $this->getColumnMapping($format);

      $changes_made = false;

      foreach ($csv_parse as $csv_entry) {
        $award_number = $csv_entry[$format == 'format1' ? 'AwardNumber' : 'Award Number'];

        $award_terms = $this->entityTypeManager->getStorage('taxonomy_term')->loadByProperties([
          'vid' => 'award_numbers',
          'name' => $award_number,
        ]);

        if (empty($award_terms)) {
          $changes_made = true;
          $award_term = $this->entityTypeManager->getStorage('taxonomy_term')->create([
            'vid' => 'award_numbers',
            'name' => $award_number,
          ]);
          $award_term->save();
        } else {
          $award_term = reset($award_terms);
        }

        $nodes = $this->entityTypeManager->getStorage('node')->loadByProperties([
          'type' => 'project',
          'field_award_number' => $award_term->id(),
        ]);

        $node = reset($nodes);

        if (!$node) {
          $changes_made = true;
          $node = $this->entityTypeManager->getStorage('node')->create([
            'type' => 'project',
          ]);
        }

        $changes = $this->projectNeedsUpdate($node, $csv_entry, $format);
        if (!empty($changes)) {
          $changes_made = true;
          foreach ($changes as $change) {
            $this->updateNodeField($node, $change['field'], $change['new'], $format, $csv_entry);
          }

          $node->set("field_project_sponsor", [
            'value' => "NSF",
            'format' => 'full_html',
          ]);

          try {
            $node->save();
            \Drupal::logger('pi_comp')->notice('Project @award_number updated/created.', ['@award_number' => $award_number]);
          } catch (\Exception $e) {
            \Drupal::logger('pi_comp')->error('Failed to save project @award_number. Error: @error', [
              '@award_number' => $award_number,
              '@error' => $e->getMessage(),
            ]);
            $this->messenger()->addError($this->t('Failed to save project @award_number. Please check the logs for more information.', ['@award_number' => $award_number]));
          }
        }

        $pis = $this->extractPIs($csv_entry, $format);
        $matched_users = $this->matchUsers($pis);

        $lead_pi = $pis[0];
        $lead_pi_user = $node->get('field_project_lead_pi_user')->entity;
        if (!$lead_pi_user || $lead_pi_user->getAccountName() != $this->formatUsername($lead_pi['name'])) {
          if ($matched_users[$lead_pi['name']]['matched']) {
            $lead_pi_user = $matched_users[$lead_pi['name']]['user'];
          } else {
            $lead_pi_user = $this->createUser($lead_pi);
          }
          $node->set('field_project_lead_pi_user', ['target_id' => $lead_pi_user->id()]);
        }

        $existing_co_pis = $node->get('field_project_co_pis_user')->referencedEntities();
        $existing_co_pi_names = array_map(function ($user) {
          return $user->getAccountName();
        }, $existing_co_pis);

        $co_pi_users = [];
        foreach (array_slice($pis, 1) as $co_pi) {
          $formatted_name = $this->formatUsername($co_pi['name']);
          if (!in_array($formatted_name, $existing_co_pi_names)) {
            if ($matched_users[$co_pi['name']]['matched']) {
              $co_pi_user = $matched_users[$co_pi['name']]['user'];
            } else {
              $co_pi_user = $this->createUser($co_pi);
            }
            $co_pi_users[] = ['target_id' => $co_pi_user->id()];
          }
        }
        if (!empty($co_pi_users)) {
          $node->set('field_project_co_pis_user', array_merge($node->get('field_project_co_pis_user')->getValue(), $co_pi_users));
        }

        try {
          $node->save();
        } catch (\Exception $e) {
          \Drupal::logger('pi_comp')->error('Failed to save project @award_number after user updates. Error: @error', [
            '@award_number' => $award_number,
            '@error' => $e->getMessage(),
          ]);
          $this->messenger()->addError($this->t('Failed to save project @award_number after user updates. Please check the logs for more information.', ['@award_number' => $award_number]));
        }
      }

      if ($changes_made) {
        $this->messenger()->addMessage($this->t('CSV entries have been imported as projects.'));
      } else {
        $this->messenger()->addMessage($this->t('No project changes were made.'));
      }
    } else {
      $this->messenger()->addMessage($this->t('Page refreshed, no upload.'));
    }
  }

  private function getNodeFieldValue($node, $drupal_field) {
    if ($drupal_field == 'body') {
      return $node->get($drupal_field)->value;
    } elseif ($drupal_field == 'field_award_number') {
      return $node->get($drupal_field)->entity ? $node->get($drupal_field)->entity->getName() : '';
    } elseif ($drupal_field == 'field_project_performance_period') {
      $start = $node->get($drupal_field)->value;
      $end = $node->get($drupal_field)->end_value;
      return $this->formatDateRange($start, $end);
    } elseif ($drupal_field == 'field_awarded_amount_to_date') {
      return preg_replace('/[^0-9.]/', '', $node->get($drupal_field)->value);
    } else {
      return trim($node->get($drupal_field)->value);
    }
  }

  private function updateNodeField($node, $drupal_field, $csv_value, $format, $csv_column) {
    if ($drupal_field == 'field_award_number') {
      $node->set($drupal_field, ['target_id' => $this->getOrCreateAwardTerm($csv_value)->id()]);
    } elseif ($drupal_field == 'field_project_performance_period') {
      $this->updatePerformancePeriod($node, $csv_value, $format, $csv_column);
    } elseif ($drupal_field == 'field_awarded_amount_to_date') {
      $node->set($drupal_field, preg_replace('/[^0-9.]/', '', $csv_value));
    } elseif ($drupal_field == 'body') {
      $node->set($drupal_field, [
        'value' => mb_convert_encoding($csv_value, 'UTF-8', 'auto'),
        'format' => 'full_html',
      ]);
    } elseif ($drupal_field == 'field_project_lead_pi') {
      $node->set($drupal_field, $csv_value);
    } elseif ($drupal_field == 'field_project_co_pis') {
      $node->set($drupal_field, $csv_value);
    } else {
      $node->set($drupal_field, mb_convert_encoding($csv_value, 'UTF-8', 'auto'));
    }
  }

  private function getOrCreateAwardTerm($award_number) {
    $award_terms = $this->entityTypeManager->getStorage('taxonomy_term')->loadByProperties([
      'vid' => 'award_numbers',
      'name' => $award_number,
    ]);

    if (empty($award_terms)) {
      $award_term = $this->entityTypeManager->getStorage('taxonomy_term')->create([
        'vid' => 'award_numbers',
        'name' => $award_number,
      ]);
      $award_term->save();
      return $award_term;
    }

    return reset($award_terms);
  }

  private function updatePerformancePeriod($node, $csv_value, $format, $csv_column) {
    $current_value = $node->get('field_project_performance_period')->getValue();
    if (empty($current_value)) {
      $current_value = [['value' => NULL, 'end_value' => NULL]];
    }

    if ($format == 'format1') {
      $date = \DateTime::createFromFormat('n/j/Y', trim($csv_value));
      if ($date) {
        $current_value[0][$csv_column == 'StartDate' ? 'value' : 'end_value'] = $date->format('Y-m-d');
      }
    } elseif ($format == 'format2') {
      $date_range = explode('–', $csv_value);
      if (count($date_range) == 2) {
        $start_date = $this->parseDate(trim($date_range[0]));
        $end_date = $this->parseDate(trim($date_range[1]));
        if ($start_date) {
          $current_value[0]['value'] = $start_date;
        }
        if ($end_date) {
          $current_value[0]['end_value'] = $end_date;
        }
      }
    }

    // Only set the field if both values are not null
    if (!empty($current_value[0]['value']) && !empty($current_value[0]['end_value'])) {
      $node->set('field_project_performance_period', $current_value);
    } else {
      // Log a warning if we're not setting the field
      \Drupal::logger('pi_comp')->warning('Performance period not set for node @nid. Start: @start, End: @end', [
        '@nid' => $node->id(),
        '@start' => $current_value[0]['value'] ?? 'NULL',
        '@end' => $current_value[0]['end_value'] ?? 'NULL',
      ]);
    }
  }

  private function parseDate($date_string) {
    $formats = ['F Y', 'n/j/Y', 'Y-m-d'];
    foreach ($formats as $format) {
      $date = \DateTime::createFromFormat($format, $date_string);
      if ($date !== false) {
        return $date->format('Y-m-d');
      }
    }
    // If no valid date is found, return null instead of throwing an exception
    return null;
  }

  public function importCallback(array &$form, FormStateInterface $form_state) {
    try {
      $csv = current($form_state->getValue('csv'));
      $delimiter = $form_state->getValue('delimiter');
      $csv_parse = $this->parser->getCsvById($csv, $delimiter);

      $format = isset($csv_parse[0]['AwardNumber']) ? 'format1' : 'format2';

      $response = new AjaxResponse();

      $summary = $this->generateImportSummary($csv_parse, $format);

      $content = '';

      if ($format == 'format1') {
        $content .= $this->t('FILE FORMAT 1: This is the file format for an NSF Award search CSV.');
      } else {
        $content .= $this->t('FILE FORMAT 2: This format imports less information. Please see documentation for more information before importing.');
      }

      $content .= '<br><br>' . $summary . '<br><br>' .
        $this->t('Do you want to proceed with the import?');

      $response->addCommand(new OpenModalDialogCommand(
        $this->t('Confirm Import'),
        $content,
        [
          'dialogClass' => 'confirm-import-dialog',
          'width' => '50%',
          'buttons' => [],
        ]
      ));

      return $response;
    } catch (\Exception $e) {
      watchdog_exception('pi_comp', $e);
      $response = new AjaxResponse();
      $response->addCommand(new AlertCommand('An error occurred: ' . $e->getMessage()));
      return $response;
    }
  }

  private function matchUsers($pis) {
    $matched_users = [];
    foreach ($pis as $index => $pi) {
      $user = $this->entityTypeManager->getStorage('user')
        ->loadByProperties(['name' => $this->formatUsername($pi['name'])]);
      if (!empty($user)) {
        $matched_users[$pi['name']] = [
          'user' => reset($user),
          'matched' => true,
          'is_pi' => ($index === 0),
          'email' => $pi['email']
        ];
      } else {
        $matched_users[$pi['name']] = [
          'user' => null,
          'matched' => false,
          'is_pi' => ($index === 0),
          'email' => $pi['email']
        ];
      }
    }
    return $matched_users;
  }

  private function extractPIs($csv_entry, $format) {
    $pis = [];
    if ($format == 'format1') {
      $pis[] = [
        'name' => $csv_entry['PrincipalInvestigator'],
        'email' => $csv_entry['PIEmailAddress']
      ];
      if (!empty($csv_entry['Co-PIName(s)'])) {
        $co_pi_names = explode(', ', $csv_entry['Co-PIName(s)']);
        $co_pi_emails = explode(' ', $csv_entry['PIEmailAddress']);
        array_shift($co_pi_emails); // Remove the lead PI's email
        foreach ($co_pi_names as $index => $name) {
          $pis[] = [
            'name' => $name,
            'email' => isset($co_pi_emails[$index]) ? $co_pi_emails[$index] : ''
          ];
        }
      }
    } else {
      $pi = $csv_entry['field_project_lead_pi'];
      $parts = explode(',', $pi);
      if (count($parts) >= 2) {
        $lastname = trim($parts[0]);
        $firstname = trim($parts[1]);
        $firstname = preg_replace('/\s+[A-Z]\.?$/', '', $firstname);
        $pis[] = [
          'name' => $firstname . ' ' . $lastname,
          'email' => ''
        ];
      } else {
        $pis[] = [
          'name' => $pi,
          'email' => ''
        ];
      }
    }
    return $pis;
  }

  private function generateRandomPassword($length = 12) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ!@#$%^&*()_+{}[]';
    $password = '';
    for ($i = 0; $i < $length; $i++) {
      $password .= $characters[random_int(0, strlen($characters) - 1)];
    }
    return $password;
  }

  private function createUser($pi_data) {
    $username = $this->formatUsername($pi_data['name']);
    $email = !empty($pi_data['email']) ? $pi_data['email'] : strtolower(str_replace(' ', '.', $username)) . '@example.com';
    $user = $this->entityTypeManager->getStorage('user')->create([
      'name' => $username,
      'mail' => $email,
      'pass' => $this->generateRandomPassword(),
      'status' => 1,
    ]);
    $user->save();
    $this->messenger()->addStatus($this->t('Created user account for: @user with email: @email', ['@user' => $username, '@email' => $email]));
    return $user;
  }

  private function formatUsername($name) {
    $parts = preg_split('/,\s*/', $name, 2);
    if (count($parts) === 2) {
      $lastname = ucfirst(strtolower(trim($parts[0])));
      $firstname = ucfirst(strtolower(trim($parts[1])));
      $firstname = preg_replace('/\s+[A-Z]\.?$/', '', $firstname);
      return $firstname . ' ' . $lastname;
    }
    return ucwords(strtolower($name));
  }

  private function generateImportSummary($csv_parse, $format) {
    $to_look = [];
    $to_create = [];
    $to_update = [];
    $update_details = [];
    $user_summary = [];

    foreach ($csv_parse as $csv_entry) {
      $award_number = $csv_entry[$format == 'format1' ? 'AwardNumber' : 'Award Number'];
      $to_look[] = $award_number;

      $pis = $this->extractPIs($csv_entry, $format);
      $matched_users = $this->matchUsers($pis);
      $unmatched_pis = array_filter($pis, function($pi) use ($matched_users) {
        return !$matched_users[$pi['name']]['matched'];
      });

      $user_summary[$award_number] = [
        'matched' => array_map(function($pi) { return $pi['name']; }, array_filter($pis, function($pi) use ($matched_users) {
          return $matched_users[$pi['name']]['matched'];
        })),
        'unmatched' => array_map(function($pi) { return $pi['name']; }, $unmatched_pis),
      ];

      $award_terms = $this->entityTypeManager->getStorage('taxonomy_term')->loadByProperties([
        'vid' => 'award_numbers',
        'name' => $award_number,
      ]);

      if (empty($award_terms)) {
        $to_create[] = $award_number;
      } else {
        $award_term = reset($award_terms);
        $nodes = $this->entityTypeManager->getStorage('node')->loadByProperties([
          'type' => 'project',
          'field_award_number' => $award_term->id(),
        ]);

        if (empty($nodes)) {
          $to_create[] = $award_number;
        } else {
          $node = reset($nodes);
          $changes = $this->projectNeedsUpdate($node, $csv_entry, $format);
          if (!empty($changes)) {
            $to_update[] = $award_number;
            $update_details[$award_number] = $changes;
          }
        }
      }
    }

    $summary = '<strong>' . $this->t('Projects in upload:') . '</strong><br>';
    $summary .= implode(', ', $to_look) . '<br><br>';

    if (!empty($to_create)) {
      $summary .= '<strong>' . $this->t('Projects to be created:') . '</strong><br>';
      $summary .= implode(', ', $to_create) . '<br><br>';
    }

    if (!empty($to_update)) {
      $summary .= '<strong>' . $this->t('Projects to be updated:') . '</strong><br>';
      $summary .= implode(', ', $to_update) . '<br><br>';

      $summary .= '<strong>' . $this->t('Update details:') . '</strong><br>';
      foreach ($update_details as $award_number => $changes) {
        $summary .= $this->t('Project @award_number:', ['@award_number' => $award_number]) . '<br>';
        foreach ($changes as $change) {
          $summary .= $this->t('- Field @field: "@old" -> "@new"', [
              '@field' => $change['field'],
              '@old' => $change['old'],
              '@new' => $change['new'],
            ]) . '<br>';
        }
        $summary .= '<br>';
      }
    }

    if (empty($to_create) && empty($to_update)) {
      $summary .= $this->t('No changes detected. All projects are up to date.') . '<br>' . '<br>';
    }


    $summary .= '<strong>' . $this->t('User matching summary:') . '</strong><br>';
    foreach ($csv_parse as $csv_entry) {
      $award_number = $csv_entry[$format == 'format1' ? 'AwardNumber' : 'Award Number'];
      $pis = $this->extractPIs($csv_entry, $format);
      $matched_users = $this->matchUsers($pis);

      $summary .= $this->t('Award Number: @award_number', ['@award_number' => $award_number]) . '<br>';

      $existing_users = [];
      $matched_users_list = [];
      $unmatched_users = [];

      $nodes = $this->entityTypeManager->getStorage('node')->loadByProperties([
        'type' => 'project',
        'field_award_number.entity.name' => $award_number,
      ]);
      $node = reset($nodes);

      foreach ($pis as $index => $pi) {
        $formatted_name = $this->formatUsername($pi['name']);
        if ($node) {
          $is_assigned = false;
          if ($index === 0) {
            $lead_pi_user = $node->get('field_project_lead_pi_user')->entity;
            if ($lead_pi_user && $lead_pi_user->getAccountName() == $formatted_name) {
              $existing_users[] = '<strong>PI:</strong> ' . $formatted_name;
              $is_assigned = true;
            }
          } else {
            foreach ($node->get('field_project_co_pis_user') as $co_pi_user) {
              if ($co_pi_user->entity && $co_pi_user->entity->getAccountName() == $formatted_name) {
                $existing_users[] = '<strong>Co-PI:</strong> ' . $formatted_name;
                $is_assigned = true;
                break;
              }
            }
          }
          if (!$is_assigned) {
            if ($matched_users[$pi['name']]['matched']) {
              $matched_users_list[] = ($index === 0 ? '<strong>PI:</strong> ' : '<strong>Co-PI:</strong> ') . $formatted_name;
            } else {
              $unmatched_users[] = ($index === 0 ? '<strong>PI:</strong> ' : '<strong>Co-PI:</strong> ') . $formatted_name;
            }
          }
        } else {
          if ($matched_users[$pi['name']]['matched']) {
            $matched_users_list[] = ($index === 0 ? '<strong>PI:</strong> ' : '<strong>Co-PI:</strong> ') . $formatted_name;
          } else {
            $unmatched_users[] = ($index === 0 ? '<strong>PI:</strong> ' : '<strong>Co-PI:</strong> ') . $formatted_name;
          }
        }
      }

      if (!empty($existing_users)) {
        $summary .= $this->t('- Already assigned users:') . '<br>';
        $summary .= '  ' . implode('<br>  ', $existing_users) . '<br>';
      }
      if (!empty($matched_users_list)) {
        $summary .= $this->t('- Matched users:') . '<br>';
        $summary .= '  ' . implode('<br>  ', $matched_users_list) . '<br>';
      }
      if (!empty($unmatched_users)) {
        $summary .= $this->t('- Users to be created:') . '<br>';
        $summary .= '  ' . implode('<br>  ', $unmatched_users) . '<br>';
      }
      $summary .= '<br>';
    }

    return $summary;
  }

  private function projectNeedsUpdate($node, $csv_entry, $format) {
    $mapping = $this->getColumnMapping($format);
    $changes = [];
    foreach ($mapping as $csv_column => $drupal_field) {
      if (isset($csv_entry[$csv_column])) {
        $csv_value = trim($csv_entry[$csv_column]);
        $node_value = $this->getNodeFieldValue($node, $drupal_field);

        // Skip comparison if both values are empty
        if (empty($csv_value) && empty($node_value)) {
          continue;
        }

        if ($drupal_field == 'body') {
          $csv_value = mb_convert_encoding($csv_value, 'UTF-8', 'auto');
        } elseif ($drupal_field == 'field_award_number') {
          $node_value = $node->get($drupal_field)->entity ? $node->get($drupal_field)->entity->getName() : '';
        } elseif ($drupal_field == 'field_project_performance_period') {
          if ($format == 'format1') {
            if ($csv_column == 'StartDate') {
              $node_date = $node->get($drupal_field)->value;
            } elseif ($csv_column == 'EndDate') {
              $node_date = $node->get($drupal_field)->end_value;
            }
            $node_value = $node_date ? date('n/j/Y', strtotime($node_date)) : '';
          } else { // format2
            $start = $node->get($drupal_field)->value;
            $end = $node->get($drupal_field)->end_value;
            $node_value = $this->formatDateRange($start, $end);
          }
          // Compare non-empty values only
          if (!empty($csv_value) && !empty($node_value) && $csv_value !== $node_value) {
            $changes[] = [
              'field' => $drupal_field,
              'old' => $node_value,
              'new' => $csv_value,
            ];
          }
        } elseif ($drupal_field == 'field_awarded_amount_to_date') {
          $csv_value = preg_replace('/[^0-9.]/', '', $csv_value);
        }

        if ($csv_value !== $node_value) {
          $changes[] = [
            'field' => $drupal_field,
            'old' => $node_value,
            'new' => $csv_value,
          ];
        }
      }
    }
    return $changes;
  }

  private function formatDateRange($start_date, $end_date) {
    if (!$start_date && !$end_date) {
      return '';
    }
    $start = $start_date ? new \DateTime($start_date) : null;
    $end = $end_date ? new \DateTime($end_date) : null;
    return ($start ? $start->format('F Y') : 'Unknown start date') .
      ' – ' .
      ($end ? $end->format('F Y') : 'Unknown end date');
  }

  private function getColumnMapping($format) {
    $column_mappings = [
      'format1' => [
        'AwardNumber' => 'field_award_number',
        'title' => 'title',
        'Organization' => 'field_project_institution',
        'StartDate' => 'field_project_performance_period',
        'EndDate' => 'field_project_performance_period',
        'PrincipalInvestigator' => 'field_project_lead_pi',
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

    return $column_mappings[$format];
  }
}
