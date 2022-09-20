<?php

namespace Drupal\jsonapi_filter_cache_tags;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\ContentEntityInterface;

/**
 * The CacheTagsBuilder service.
 *
 * Provide common functionality between the creating and storing the cache tags
 * (via the ResponseSubcriber) and invalidating them on entity save.
 */
class CacheTagsBuilder {

  /**
   * Build the cache tag string.
   *
   * @param string $entity_type
   *   The entity type name, e.g. "node".
   * @param string $field_name
   *   The field name, e.g. "field_category".
   * @param string $field_value
   *   The value of the field.
   *
   * @return string
   */
  public function buildCacheTag(string $entity_type, string $field_name, string $field_value) {
    return 'jsonapi_filter:' . $entity_type . ':' . $field_name . ':' . $field_value;
  }

  /**
   * Store the cache tags in Drupal's state system.
   *
   * We do this so that we can retrieve them later and know which ones to
   * invalidate.  We'd otherwise need to invalidate every theoretical cache tag
   * (for every field on the entity), which would be wasteful.
   *
   * @param string $entity_type
   *   The entity type name, e.g. "node".
   * @param array $cache_tags
   *   A list of cache tags to be stored.
   *
   * @return void
   */
  public function storeCacheTags(string $entity_type, array $cache_tags) {
    $state_key = 'jsonapi_filter_cache_tags:' . $entity_type;
    $existing_cache_tags = \Drupal::state()->get($state_key, []);
    \Drupal::state()->set($state_key, array_merge($cache_tags, $existing_cache_tags));
  }

  /**
   * Invalidate cache tags based for $entity, based on what is already stored.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity that has been created/updated.
   *
   * @return void
   */
  public function invalidateForEntity(ContentEntityInterface $entity) {
    $existing_cache_tags = \Drupal::state()->get('jsonapi_filter_cache_tags:' . $entity->getEntityTypeId(), []);
    foreach($existing_cache_tags as $cache_tag) {
      $parts = explode(':', $cache_tag);
      if ($entity->hasField($parts[2]) && $entity->{$parts[2]}->entity && $entity->{$parts[2]}->entity->uuid() == $parts[3]) {
        Cache::invalidateTags([$cache_tag]);
      }
    }
  }
}
