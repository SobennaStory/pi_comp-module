<?php

namespace Drupal\pi_comp\Services;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\node\NodeInterface;
use Drupal\Core\Database\Transaction;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;

/**
 * Service for managing PIMM projects.
 */
class PimmProjectManager implements PimmProjectManagerInterface {

  use StringTranslationTrait;

  /**
   * Valid project statuses.
   */
  const VALID_STATUSES = [
    'active',
    'pending',
    'inactive',
    'archived',
  ];

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The cache tags invalidator.
   *
   * @var \Drupal\Core\Cache\CacheTagsInvalidatorInterface
   */
  protected $cacheTagsInvalidator;

  /**
   * Table name for tracked projects.
   */
  const TABLE_NAME = 'pimm_tracked_projects';

  /**
   * Constructs a new PimmProjectManager.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    Connection $database,
    AccountProxyInterface $current_user,
    CacheTagsInvalidatorInterface $cache_tags_invalidator
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->database = $database;
    $this->currentUser = $current_user;
    $this->cacheTagsInvalidator = $cache_tags_invalidator;
  }

  /**
   * {@inheritdoc}
   */
  /**
   * {@inheritdoc}
   */
  public function addProject(NodeInterface $node, $notes = '') {
    try {
      // Validate project type
      if ($node->getType() !== 'project') {
        throw new \InvalidArgumentException('Node must be of type project');
      }

      // Check if already tracked
      if ($this->isProjectTracked($node->id())) {
        \Drupal::logger('pi_comp')->notice('Project already tracked: @nid', ['@nid' => $node->id()]);
        return FALSE;
      }

      // Determine initial status based on performance period
      $status = 'active';
      if ($node->hasField('field_project_performance_period') && !$node->get('field_project_performance_period')->isEmpty()) {
        $end_date = $node->get('field_project_performance_period')->end_value;
        if ($end_date && strtotime($end_date) < \Drupal::time()->getCurrentTime()) {
          $status = 'inactive';
          $notes = trim($notes . ' Project automatically set to inactive - performance period ended.');
        }
      }

      // Start transaction
      $transaction = $this->database->startTransaction();

      try {
        $fields = [
          'nid' => $node->id(),
          'status' => $status,
          'added_date' => time(),
          'added_by' => $this->currentUser->id(),
          'notes' => $notes,
        ];

        // Insert the record
        $result = $this->database->insert('pimm_tracked_projects')
          ->fields($fields)
          ->execute();

        if (!$result) {
          throw new \Exception('Failed to insert project record');
        }

        // Clear caches
        $this->invalidateProjectCaches($node->id());

        \Drupal::logger('pi_comp')->notice('Successfully added project: @nid with status @status', [
          '@nid' => $node->id(),
          '@status' => $status,
          '@title' => $node->getTitle(),
        ]);

        return TRUE;
      }
      catch (\Exception $e) {
        if (isset($transaction)) {
          $transaction->rollBack();
        }
        throw $e;
      }
    }
    catch (\Exception $e) {
      \Drupal::logger('pi_comp')->error('Failed to add project @nid: @error', [
        '@nid' => $node->id(),
        '@error' => $e->getMessage(),
      ]);
      return FALSE;
    }
  }

  /**
   * Invalidates project-related caches.
   */
  protected function invalidateProjectCaches($nid = NULL) {
    try {
      $tags = ['pimm_project_list'];
      if ($nid) {
        $tags[] = 'pimm_project:' . $nid;
      }
      $this->cacheTagsInvalidator->invalidateTags($tags);
    }
    catch (\Exception $e) {
      \Drupal::logger('pi_comp')->error('Cache invalidation error: @error', ['@error' => $e->getMessage()]);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function isProjectTracked($nid) {
    if (!$nid) {
      return FALSE;
    }

    try {
      return (bool) $this->database->select(self::TABLE_NAME, 'p')
        ->fields('p', ['id'])
        ->condition('nid', $nid)
        ->countQuery()
        ->execute()
        ->fetchField();
    }
    catch (\Exception $e) {
      \Drupal::logger('pi_comp')->error('Error checking project tracking status: @error', [
        '@error' => $e->getMessage(),
      ]);
      return FALSE;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function updateProjectStatus($nid, $status, $notes = NULL) {
    if (!in_array($status, self::VALID_STATUSES)) {
      \Drupal::logger('pi_comp')->error('Invalid status provided: @status', ['@status' => $status]);
      return FALSE;
    }

    try {
      $fields = [
        'status' => $status,
        'updated' => time(),
      ];

      if ($notes !== NULL) {
        $fields['notes'] = $notes;
      }

      $result = $this->database->update(self::TABLE_NAME)
        ->fields($fields)
        ->condition('nid', $nid)
        ->execute();

      if ($result) {
        $this->invalidateProjectCaches($nid);
      }

      return (bool) $result;
    }
    catch (\Exception $e) {
      \Drupal::logger('pi_comp')->error('Error updating project status: @error', [
        '@error' => $e->getMessage(),
      ]);
      return FALSE;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function removeProject($nid) {
    try {
      $transaction = $this->database->startTransaction();

      try {
        $result = $this->database->delete(self::TABLE_NAME)
          ->condition('nid', $nid)
          ->execute();

        if ($result) {
          $this->invalidateProjectCaches($nid);
          \Drupal::logger('pi_comp')->notice('Successfully removed project: @nid', ['@nid' => $nid]);
        }

        return (bool) $result;
      }
      catch (\Exception $e) {
        if (isset($transaction)) {
          $transaction->rollBack();
        }
        throw $e;
      }
    }
    catch (\Exception $e) {
      \Drupal::logger('pi_comp')->error('Error removing project: @error', ['@error' => $e->getMessage()]);
      return FALSE;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getProjectCount($status = NULL) {
    try {
      $query = $this->database->select(self::TABLE_NAME, 'p')
        ->fields('p', ['nid']);

      if ($status) {
        $query->condition('status', $status);
      }

      return (int) $query->countQuery()->execute()->fetchField();
    }
    catch (\Exception $e) {
      \Drupal::logger('pi_comp')->error('Error getting project count: @error', ['@error' => $e->getMessage()]);
      return 0;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getProjects($status = NULL, $sort = 'added_date', $direction = 'DESC') {
    try {
      // Start with base query
      $query = $this->database->select(self::TABLE_NAME, 'p')
        ->fields('p');

      // Add status filter if provided
      if ($status && in_array($status, self::VALID_STATUSES)) {
        $query->condition('p.status', $status);
      }

      // Validate and apply sorting
      $valid_sorts = [
        'added_date' => 'p.added_date',
        'status' => 'p.status',
        'updated' => 'p.updated',
        'notes' => 'p.notes',
      ];

      $sort_field = isset($valid_sorts[$sort]) ? $valid_sorts[$sort] : 'p.added_date';
      $direction = strtoupper($direction) === 'ASC' ? 'ASC' : 'DESC';
      $query->orderBy($sort_field, $direction);

      // Execute query
      $tracked_projects = $query->execute()->fetchAllAssoc('nid');

      if (empty($tracked_projects)) {
        return [];
      }

      // Load all project nodes in a single operation
      try {
        $nodes = $this->entityTypeManager->getStorage('node')
          ->loadMultiple(array_keys($tracked_projects));
      }
      catch (\Exception $e) {
        \Drupal::logger('pi_comp')->error('Failed to load project nodes: @error', [
          '@error' => $e->getMessage(),
        ]);
        return [];
      }

      $current_time = \Drupal::time()->getCurrentTime();

      // Build the projects array
      $projects = [];
      foreach ($tracked_projects as $nid => $tracking_data) {
        if (!isset($nodes[$nid])) {
          \Drupal::logger('pi_comp')->warning('Tracked project node not found: @nid', ['@nid' => $nid]);
          continue;
        }

        $node = $nodes[$nid];

        // Verify node is still a project type
        if ($node->getType() !== 'project') {
          \Drupal::logger('pi_comp')->warning('Tracked node is not a project: @nid', ['@nid' => $nid]);
          continue;
        }

        // Check performance period and update status if needed
        if ($node->hasField('field_project_performance_period') && !$node->get('field_project_performance_period')->isEmpty()) {
          $end_date = $node->get('field_project_performance_period')->end_value;
          if ($end_date && strtotime($end_date) < $current_time) {
            // Update status to inactive if performance period has ended
            $tracking_data->status = 'inactive';
            $this->updateProjectStatus($nid, 'inactive', 'Automatically set to inactive - performance period ended');
          }
        }

        $projects[$nid] = [
          'node' => $node,
          'tracking' => [
            'id' => $tracking_data->id,
            'status' => $tracking_data->status,
            'added_date' => $tracking_data->added_date,
            'added_by' => $tracking_data->added_by,
            'notes' => $tracking_data->notes,
            'updated' => $tracking_data->updated,
          ],
        ];
      }

      return $projects;
    }
    catch (\Exception $e) {
      \Drupal::logger('pi_comp')->error('Error fetching projects: @error', [
        '@error' => $e->getMessage(),
      ]);
      return [];
    }
  }
}
