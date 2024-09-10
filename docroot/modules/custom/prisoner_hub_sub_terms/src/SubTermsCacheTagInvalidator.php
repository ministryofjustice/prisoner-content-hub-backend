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

    $category_entity = $this->getCategoriesFromEntity($entity);
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
   * Check if content is updated to have a different series/ set of categories.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The content entity to check.
   *
   * @return bool
   *   TRUE if the content is changing category or series, otherwise FALSE.
   */
  protected function checkEntityIsChangingCategoryOrSeries(ContentEntityInterface $entity) {
    // We don't care about new content...
    if (!isset($entity->original)) {
      return FALSE;
    }
    // ...or content that isn't published.
    if (!$entity->isPublished()) {
      return FALSE;
    }
    if ($this->getSeriesFromEntity($entity) != $this->getSeriesFromEntity($entity->original)) {
      // Content has been put in a different series, or content has been
      // removed from a series, or content has been put in a series when it
      // wasn't previously in one.
      return TRUE;
    }

    // Compare all the categories before and after saving.
    // We don't care if they are re-ordered; just if they are different sets.
    $category_ids = $this->getCategoryIdsFromEntity($entity);
    $previous_category_ids = $this->getCategoryIdsFromEntity($entity->original);

    // If these were large sets, it would be more efficient to sort these sets
    // and step though. However, at the time of writing, categories max out at
    // 3, so let's keep it simple.
    if (array_diff($category_ids, $previous_category_ids)) {
      return TRUE;
    }
    if (array_diff($previous_category_ids, $category_ids)) {
      return TRUE;
    }

    // If we get to here, we haven't found any changes.
    return FALSE;
  }

  /**
   * Get the category taxonomy terms from $entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   Entity for which we are getting the taxonomy term.
   *
   * @return array
   *   Array of term IDs (if any).
   */
  protected function getCategoryIdsFromEntity(ContentEntityInterface $entity) {
    $ids = [];
    if ($entity->hasField('field_moj_top_level_categories') && !$entity->get('field_moj_top_level_categories')->isEmpty()) {
      foreach ($entity->get('field_moj_top_level_categories')->getValue() as $value) {
        $ids[] = $value['target_id'];
      }
    }
    return $ids;
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
