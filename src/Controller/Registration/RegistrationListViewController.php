<?php

namespace Drupal\pi_comp\Controller\Registration;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class RegistrationListViewController extends ControllerBase {
  protected $formBuilder;
  protected $entityTypeManager;

  public function __construct(FormBuilderInterface $form_builder, EntityTypeManagerInterface $entity_type_manager) {
    $this->formBuilder = $form_builder;
    $this->entityTypeManager = $entity_type_manager;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('form_builder'),
      $container->get('entity_type.manager')
    );
  }

  public function view($registration_list_id = NULL, $webform_id = NULL, $sort_field = NULL) {
    if ($registration_list_id === NULL) {
      return $this->listRegistrationLists();
    }

    $registration_list = $this->entityTypeManager->getStorage('node')->load($registration_list_id);
    $webforms = $registration_list->field_regwebforms->referencedEntities();
    $users = $registration_list->field_regusers->referencedEntities();
    $form = $this->formBuilder->getForm('Drupal\pi_comp\Form\Registration\RegistrationSortForm', $registration_list_id, $webforms);

    $tables = [];
    foreach ($webforms as $webform) {
      $tables[] = $this->buildWebformTable($webform, $users, $registration_list_id, $sort_field);
    }

   $build = [
      '#theme' => 'registration_list_view',
      '#registration_list' => $registration_list,
      '#sort_form' => $form,
      '#webform_tables' => $tables,
      '#webforms_count' => count($webforms),
      '#users_count' => count($users),
      '#debug_webforms' => $registration_list->field_regwebforms->getValue(),
      '#debug_users' => $registration_list->field_regusers->getValue(),
    ];

    $this->attachLibrary($build);

    return $build;
  }

  private function attachLibrary(&$build) {
    $build['#attached']['library'][] = 'pi_comp/pi_comp_styles';
  }

  private function buildWebformTable($webform, $users, $registration_list_id, $sort_field = NULL) {
    $webform_elements = $webform->getElementsDecodedAndFlattened();
    $header = ['User ID', 'Name'];
    foreach ($webform_elements as $key => $element) {
      if (isset($element['#title'])) {
        $header[] = $element['#title'];
      }
    }

    $rows = [];
    foreach ($users as $user) {
      $submission = $this->getLatestSubmission($webform->id(), $user->id());
      if ($submission) {
        $row = [$user->id(), $user->getDisplayName()];
        $submission_data = $submission->getData();
        foreach ($webform_elements as $key => $element) {
          $row[] = $submission_data[$key] ?? '';
        }
        $rows[] = $row;
      }
    }

    if ($sort_field) {
      usort($rows, function ($a, $b) use ($sort_field, $header) {
        $index = array_search($sort_field, $header);
        return $a[$index] <=> $b[$index];
      });
    }

    return [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#empty' => $this->t('No submissions found for @webform', ['@webform' => $webform->label()]),
      '#caption' => $this->t('Submissions for @webform', ['@webform' => $webform->label()]),
    ];
  }

  private function getLatestSubmission($webform_id, $user_id) {
    $query = $this->entityTypeManager->getStorage('webform_submission')->getQuery()
      ->condition('webform_id', $webform_id)
      ->condition('uid', $user_id)
      ->sort('created', 'DESC')
      ->range(0, 1)
      ->accessCheck(FALSE);

    $result = $query->execute();

    if (!empty($result)) {
      $sid = reset($result);
      return $this->entityTypeManager->getStorage('webform_submission')->load($sid);
    }

    return NULL;
  }

  public function viewUserSubmissions($registration_list_id, $user_id) {
    $registration_list = $this->entityTypeManager->getStorage('node')->load($registration_list_id);

    if (!$registration_list || $registration_list->bundle() !== 'registration_list') {
      return [
        '#markup' => $this->t('Invitee list not found.'),
      ];
    }

    $user = $this->entityTypeManager->getStorage('user')->load($user_id);
    if (!$user) {
      return [
        '#markup' => $this->t('User not found.'),
      ];
    }

    $webforms = $registration_list->field_regwebforms->referencedEntities();

    $output = [
      '#markup' => '<h2>' . $this->t('Submissions for @name', ['@name' => $user->getDisplayName()]) . '</h2>',
    ];

    foreach ($webforms as $webform) {
      $submission = $this->getLatestSubmission($webform->id(), $user_id);
      if ($submission) {
        $output[] = [
          '#type' => 'details',
          '#title' => $this->t('Submission for @webform', ['@webform' => $webform->label()]),
          '#open' => TRUE,
          'content' => [
            '#type' => 'webform_submission_information',
            '#webform_submission' => $submission,
          ],
        ];
      }
    }

    return $output;
  }

  public function getWebformFields($webform_id) {
    $webform = $this->entityTypeManager->getStorage('webform')->load($webform_id);
    $elements = $webform->getElementsDecodedAndFlattened();

    $fields = [];
    foreach ($elements as $key => $element) {
      if (isset($element['#title'])) {
        $fields[$key] = $element['#title'];
      }
    }

    return new JsonResponse($fields);
  }

  private function listRegistrationLists() {
    // Clear the entity cache for nodes
    $this->entityTypeManager->getStorage('node')->resetCache();

    $query = $this->entityTypeManager->getStorage('node')->getQuery()
      ->condition('type', 'registration_list')
      ->sort('created', 'DESC')
      ->accessCheck(FALSE);
    $nids = $query->execute();

    $registration_lists = $this->entityTypeManager->getStorage('node')->loadMultiple($nids);

    $options = [];
    foreach ($registration_lists as $registration_list) {
      $options[$registration_list->id()] = $registration_list->label();
    }

    $build = [
      'title' => [
        '#type' => 'markup',
        '#markup' => '<h2>' . $this->t('Registration Lists') . '</h2>',
      ],
      'create_button' => [
        '#type' => 'link',
        '#title' => $this->t('Create New Registration List'),
        '#url' => Url::fromRoute('pi_comp.registration_list_create'),
        '#attributes' => [
          'class' => ['button', 'button--primary'],
        ],
      ],
      'select_list' => [
        '#type' => 'select',
        '#title' => $this->t('Select a Registration List'),
        '#options' => $options,
        '#empty_option' => $this->t('- Select -'),
        '#attributes' => [
          'data-drupal-selector' => 'edit-select-list',
        ],
      ],
      'view_button' => [
        '#type' => 'button',
        '#value' => $this->t('View Selected List'),
        '#attributes' => [
          'class' => ['use-ajax'],
          'data-dialog-type' => 'modal',
          'data-drupal-selector' => 'edit-view-button',
        ],
      ],
      '#attached' => [
        'library' => [
          'core/drupal.dialog.ajax',
          'pi_comp/registration-list-view',
        ],
        'drupalSettings' => [
          'pi_comp' => [
            'viewUrl' => Url::fromRoute('pi_comp.registration_list_view')->toString(),
          ],
        ],
      ],
      '#cache' => [
        'tags' => ['node_list:registration_list'],
      ],
    ];

    return $build;
  }
  public function exportEmails($registration_list_id) {
    $registration_list = $this->entityTypeManager->getStorage('node')->load($registration_list_id);

    if (!$registration_list || $registration_list->bundle() !== 'registration_list') {
      $this->messenger()->addError($this->t('Invitee list not found or invalid bundle: @id', ['@id' => $registration_list_id]));
      return $this->redirect('pi_comp.registration_list_view', ['registration_list_id' => $registration_list_id]);
    }

    $users = $registration_list->field_regusers->referencedEntities();

    $csv_data = "Name,Email\n";
    foreach ($users as $user) {
      $csv_data .= '"' . $user->getDisplayName() . '","' . $user->getEmail() . "\"\n";
    }

    $response = new Response($csv_data);
    $response->headers->set('Content-Type', 'text/csv');
    $response->headers->set('Content-Disposition', 'attachment; filename="registration_emails.csv"');

    return $response;
  }

}
