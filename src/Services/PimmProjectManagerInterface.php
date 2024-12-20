<?php

namespace Drupal\pi_comp\Services;

use Drupal\node\NodeInterface;

/**
 * Interface for PIMM project manager service.
 */
interface PimmProjectManagerInterface {

  /**
   * Gets the total number of projects.
   *
   * @return int
   *   The total number of tracked projects.
   */
  public function getProjectCount();

  /**
   * Adds a project to PIMM.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node to add.
   * @param string $notes
   *   Optional notes about the project.
   *
   * @return bool
   *   TRUE if project was added successfully, FALSE otherwise.
   */
  public function addProject(NodeInterface $node, $notes = '');

  /**
   * Removes a project from PIMM.
   *
   * @param int $nid
   *   The node ID to remove.
   *
   * @return bool
   *   TRUE if project was removed successfully, FALSE otherwise.
   */
  public function removeProject($nid);

  /**
   * Gets all projects tracked by PIMM.
   *
   * @param string|null $status
   *   Optional status to filter by.
   *
   * @return array
   *   Array of tracked projects.
   */
  public function getProjects($status = NULL);

  /**
   * Checks if a project is tracked by PIMM.
   *
   * @param int $nid
   *   The node ID to check.
   *
   * @return bool
   *   TRUE if project is tracked, FALSE otherwise.
   */
  public function isProjectTracked($nid);

  /**
   * Updates a project's PIMM status.
   *
   * @param int $nid
   *   The node ID to update.
   * @param string $status
   *   The new status.
   * @param string|null $notes
   *   Optional notes about the status change.
   *
   * @return int
   *   The number of rows affected.
   */
  public function updateProjectStatus($nid, $status, $notes = NULL);

}
