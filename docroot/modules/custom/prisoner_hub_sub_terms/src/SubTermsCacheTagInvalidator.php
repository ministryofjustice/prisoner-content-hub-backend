<?php

namespace Drupal\prisoner_hub_sub_terms;

use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;
use Drupal\taxonomy\TermInterface;

/**
 * Cache tag invalidator for sub-terms.
 */
class SubTermsCacheTagInvalidator {

  /**
   * SubTermsCacheTagInvalidator constructor.
   *
   * @param \Drupal\Core\Cache\CacheTagsInvalidatorInterface $cacheTagsInvalidator
   *   The cache tag invalidator service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager service.
   */
  public function __construct(
    protected CacheTagsInvalidatorInterface $cacheTagsInvalidator,
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {
  }

  /**
   * Invalidate appropriate cachetag(s) based on $entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
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
      // If the entity is not being published, then check whether it is changing
      // category or series.  If not, then do not invalidate any cache tags.
      if (!$this->checkEntityIsChangingCategoryOrSeries($entity)) {
        return;
      }
    }

    $category_entity = $this->getCategoryFromEntity($entity);
    if ($category_entity) {
      $this->invalidateCategoryParent($category_entity);
    }

    $series_entity = $this->getSeriesFromEntity($entity);
    if ($series_entity) {
      $this->invalidateSeriesCategory($series_entity);
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
   * Check whether content is being updated to have a different series/category.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The content entity to check.
   *
   * @return bool
   *   TRUE if the content is changing category or series, otherwise FALSE.
   */
  protected function checkEntityIsChangingCategoryOrSeries(ContentEntityInterface $entity) {
    if (!isset($entity->original) || !$entity->isPublished()) {
      return FALSE;
    }
    $referenced_entity = $this->getCategoryFromEntity($entity) ?: $this->getSeriesFromEntity($entity);
    $previous_referenced_entity = $this->getCategoryFromEntity($entity->original) ?: $this->getSeriesFromEntity($entity->original);

    $previous_entity_id = $previous_referenced_entity ? $previous_referenced_entity->id() : NULL;
    $current_entity_id = $referenced_entity ? $referenced_entity->id() : NULL;
    return $previous_entity_id != $current_entity_id;
  }

  /**
   * Get the category taxonomy term from $entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   Entity for which we are getting the taxonomy term.
   *
   * @return \Drupal\taxonomy\TermInterface|false
   *   Either the category taxonomy term entity, or FALSE if not found.
   */
  protected function getCategoryFromEntity(ContentEntityInterface $entity) {
    if ($entity->hasField('field_moj_top_level_categories') && !$entity->get('field_moj_top_level_categories')->isEmpty()) {
      $entities = $entity->get('field_moj_top_level_categories')->referencedEntities();
      if (!empty($entities)) {
        return $entities[0];
      }
    }
    return FALSE;
  }

  /**
   * Get the series taxonomy term from $entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   Entity for which we are getting the taxonomy term.
   *
   * @return \Drupal\taxonomy\TermInterface|false
   *   Either the series taxonomy term entity, or FALSE if not found.
   */
  protected function getSeriesFromEntity(ContentEntityInterface $entity) {
    if ($entity->hasField('field_moj_series') && !$entity->get('field_moj_series')->isEmpty()) {
      $entities = $entity->get('field_moj_series')->referencedEntities();
      if (!empty($entities)) {
        return $entities[0];
      }
    }
    return FALSE;
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
