<?php

namespace Drupal\prisoner_hub_sub_terms;

use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;
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
   * @param bool $insert
   *  TRUE if $entity is being inserted, otherwise FALSE.
   */
  public function invalidate($entity, bool $insert) {
    if ($entity instanceof NodeInterface) {
      $this->invalidateNode($entity, $insert);
    }
    if ($entity instanceof TermInterface) {
      $this->invalidateTaxonomyTerm($entity, $insert);
    }
  }

  /**
   * Check whether the $entity is published.
   *
   * If the $entity is being updated, then we check both the previous and
   * current versions (if one of those is published then we return TRUE).
   *
   * @param \Drupal\Core\Entity\EntityPublishedInterface $entity
   *   The $entity to check.
   * @param bool $insert
   *   TRUE if $entity is being inserted, otherwise FALSE.
   *
   * @return bool
   *   TRUE if $entity (either current or previous version) is published,
   *   otherwise FALSE.
   */
  protected function checkEntityIsPublished(EntityPublishedInterface $entity, bool $insert) {
    // Ignore if we are inserting an unpublished entity.
    if ($insert && !$entity->isPublished()) {
      return FALSE;
    }
    // Ignore if we are updating an unpublished entity.
    if (isset($entity->original) && !$entity->original->isPublished() && !$entity->isPublished()) {
      return FALSE;
    }
    return TRUE;
  }

  /**
   * Invalidate cachetags based on node updates/inserts.
   *
   * @param \Drupal\node\NodeInterface $entity
   *   The node $entity.
   * @param bool $insert
   *   TRUE if $entity is being inserted, otherwise FALSE.
   */
  protected function invalidateNode(NodeInterface $entity, bool $insert) {
    if (!$this->checkEntityIsPublished($entity, $insert)) {
      return;
    }
    $category_id = NULL;
    if ($entity->hasField('field_moj_top_level_categories') && !$entity->get('field_moj_top_level_categories')->isEmpty()) {
      $category_id = $entity->get('field_moj_top_level_categories')->target_id;
    }
    if ($entity->hasField('field_moj_series') && !$entity->get('field_moj_series')->isEmpty()) {
      $series_entities = $entity->get('field_moj_series')->referencedEntities();
      if (!empty($series_entities)) {
        $category_id = $series_entities[0]->get('field_category')->target_id;
      }
    }
    if ($category_id) {
      $this->invalidateCategoryAndParents($category_id);
    }
  }

  /**
   * Invalidate cachetags based on taxonomy term updates/inserts.
   *
   * @param \Drupal\taxonomy\TermInterface $entity
   *   The taxonomy terms $entity.
   * @param bool $insert
   *   TRUE if $entity is being inserted, otherwise FALSE.
   */
  protected function invalidateTaxonomyTerm(TermInterface $entity, bool $insert) {
    if (!$this->checkEntityIsPublished($entity, $insert)) {
      return;
    }
    $category_id = NULL;
    if ($entity->bundle() == 'series') {
      $category_id = $entity->get('field_category')->target_id;
    }
    if ($entity->bundle() == 'moj_categories') {
      if ($insert) {
        // If this is a new category, then it wont have a cache entry yet.
        // So just clear it's parents.
        $category_id = $entity->get('parent')->target_id;
      }
      else {
        $category_id = $entity->id();
      }
    }
    if ($category_id) {
      $this->invalidateCategoryAndParents($category_id);
    }
  }

  /**
   * Invalidate cachetags for category id, and all parents of that category.
   *
   * @param int $category_id
   *   The taxonomy term id for the category.
   */
  protected function invalidateCategoryAndParents(int $category_id) {
    $tags = ['prisoner_hub_sub_terms:' . $category_id];

    $terms = $this->entityTypeManager->getStorage('taxonomy_term')->loadAllParents($category_id);
    foreach ($terms as $term) {
      // Exclude the current category id, which is also returned by
      // loadAllParents(), as this has already been added.
      if ($term->id() != $category_id) {
        $tags[] =  'prisoner_hub_sub_terms:' . $term->id();
      }
    }
    $this->cacheTagsInvalidator->invalidateTags($tags);
  }
}
