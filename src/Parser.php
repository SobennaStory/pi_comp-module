<?php

namespace Drupal\pi_comp;

use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Get data from CSV files.
 */
class Parser implements ParserInterface {

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs Parser object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function getCsvById(int $id, string $delimiter) {
    /** @var \Drupal\file\Entity\File $entity */
    $entity = $this->getCsvEntity($id);
    $csv_data = [];

    if (($handle = fopen($entity->getFileUri(), 'r')) !== FALSE) {
      $keys = fgetcsv($handle, 0, $delimiter); // Get column names (keys)
      while (($data = fgetcsv($handle, 0, $delimiter)) !== FALSE) {
        $csv_data[] = array_combine($keys, $data); // Combine keys with data
      }
      fclose($handle);
    }

    return $csv_data;
  }

  /**
   * {@inheritdoc}
   */
  public function getCsvFieldsById(int $id) {
    $csv = $this->getCsvById($id);

    if ($csv && is_array($csv) && count($csv) > 0) {
      return array_keys($csv[0]); // Return keys of the first row
    }

    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getCsvEntity(int $id) {
    if ($id) {
      return $this->entityTypeManager->getStorage('file')->load($id);
    }

    return NULL;
  }

}
