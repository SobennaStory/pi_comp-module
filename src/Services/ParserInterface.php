<?php

namespace Drupal\pi_comp\Services;

/**
 * Csv parser manager interface.
 */
interface ParserInterface {

  /**
   * Get CSV by id.
   *
   * @param int $id
   *   CSV id.
   * @param string $delimiter
   *   CSV delimiter.
   *
   * @return array|null
   *   Parsed CSV.
   */
  public function getCsvById(int $id, string $delimiter);

  /**
   * Get CSV column (first row).
   *
   * @param int $id
   *   CSV id.
   *
   * @return array|null
   *   CSV field names.
   */
  public function getCsvFieldsById(int $id);

  /**
   * Load CSV.
   *
   * @param int $id
   *   CSV id.
   *
   * @return \Drupal\file\Entity\File|null
   *   Entity object.
   */
  public function getCsvEntity(int $id);

}

