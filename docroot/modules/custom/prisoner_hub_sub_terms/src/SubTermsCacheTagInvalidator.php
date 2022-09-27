<?php

namespace Drupal\prisoner_hub_sub_terms;

use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\TermInterface;

class SubTermsCacheTagInvalidator {

  /**
   * The cache tags invalidator.
   *
   * @var \Drupal\Core\Cache\CacheTagsInvalidatorInterface
   */
  protected $cacheTagsInvalidator;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * SubTermsCacheTagInvalidator constructor.
   *
   * @param \Drupal\Core\Cache\CacheTagsInvalidatorInterface $cache_tag_invalidator
   *   The cache tag invalidator service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   */
  public function __construct(CacheTagsInvalidatorInterface $cache_tag_invalidator, EntityTypeManagerInterface $entity_type_manager) {
    $this->cacheTagsInvalidator = $cache_tag_invalidator;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Invalidate appropriate cachetag(s) based on $entity.
   *
   * @param $entity
   *   The $entity being inserted or updated.
   */
  public function invalidate($entity) {
    if ($entity instanceof NodeInterface) {
      $this->invalidateNode($entity);
    }
  }

  /**
   * Invalidate cache tags based on node updates/inserts.
   *
   * @param \Drupal\node\NodeInterface $entity
   *   The node $entity.
   */
  protected function invalidateNode(NodeInterface $entity) {
    if (!$this->checkEntityIsBeingPublished($entity)) {
      return;
    }
    $term = NULL;
    if ($entity->hasField('field_moj_top_level_categories') && !$entity->get('field_moj_top_level_categories')->isEmpty()) {
      $entities = $entity->get('field_moj_top_level_categories')->referencedEntities();
      if (!empty($entities)) {
        $this->invalidateCategoryParent($entities[0]);
      }
    }
    if ($entity->hasField('field_moj_series') && !$entity->get('field_moj_series')->isEmpty()) {
      $entities = $entity->get('field_moj_series')->referencedEntities();
      if (!empty($entities)) {
        $this->invalidateSeriesCategory($entities[0]);
      }
    }
    if ($term) {
      $this->invalidateTermParent($term);
    }
  }

  /**
   * Check whether the $entity is currently being published.
   *
   * If the $entity is being updated, then we check that the previous version
   * was unpublished and the new version is published.
   *
   * @param \Drupal\Core\Entity\EntityPublishedInterface $entity
   *   The $entity to check.
   *
   * @return bool
   *   TRUE if $entity is being published, otherwise FALSE.
   */
  protected function checkEntityIsBeingPublished(EntityPublishedInterface $entity) {
    // If no original then this is a new entity.
    if (!isset($entity->original)) {
      return $entity->isPublished();
    }
    return !$entity->original->isPublished() && $entity->isPublished();
  }

  /**
   * Invalidate cache tags for the immediate parent category.
   *
   * Note we only invalidate the immediate parent, and not the full hierarchy.
   * This is to prevent excessive cache rebuilds, which can have a big impact on
   * performance.  Instead, we allow the others caches to reach their max-age.
   * Note we did use to expire grandfather categories, so if this is required
   * again please look in the git history.
   *
   * @param \Drupal\taxonomy\TermInterface $term
   *   The taxonomy term, either a series or a category.
   */
  protected function invalidateCategoryParent(TermInterface $term) {
    $cache_tags = [];
    foreach ($term->get('parent') as $parent) {
      if ($parent->target_id != 0) {
        $cache_tags = ['prisoner_hub_sub_terms:' . $parent->target_id];
      }
    }
    $this->cacheTagsInvalidator->invalidateTags($cache_tags);
  }

  /**
   * Invalidate the category associated with a series.
   *
   * Same as invalidateCategoryParent(), but for series.  Again we don't
   * go through the entire hierarchy, we just invalidate the immediate parent.
   *
   * @param \Drupal\taxonomy\TermInterface $term
   *   The series taxonomy term.
   */
  protected function invalidateSeriesCategory(TermInterface $term) {
    $cache_tags = ['prisoner_hub_sub_terms:' . $term->get('field_category')->target_id];
    $this->cacheTagsInvalidator->invalidateTags($cache_tags);
  }
}
