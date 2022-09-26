<?php

namespace Drupal\jsonapi_filter_cache_tags;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\ContentEntityInterface;

/**
 * The CacheTagsBuilder service.
 *
 * Provide common functionality between the creating and storing the cache tags
 * (via the ResponseSubscriber) and invalidating them on entity save.
 */
class CacheTagsBuilder {

  /**
   * String prefix for storing cache tags.
   */
  const CACHE_TAG_PREFIX = 'jsonapi_filter';

  /**
   * String prefix for storing data in the state API.
   */
  const STATE_KEY_PREFIX = 'jsonapi_filter_cache_tags.field_list';

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
    return self::CACHE_TAG_PREFIX . ':' . $entity_type . ':' . $field_name . ':' . $field_value;
  }

  /**
   * Get the state key to be used based on $entity_type.
   *
   * @param string $entity_type
   *   The entity type name, e.g. "node"
   *
   * @return string
   */
  public function getStateKey(string $entity_type) {
    return self::STATE_KEY_PREFIX . '.' . $entity_type;
  }

  /**
   * Store the field used as filters in Drupal's state system.
   *
   * We do this so that we can retrieve them later and know which fields to
   * use as cache tags for invalidation.  We'd otherwise need to invalidate
   * every field, which would be wasteful.
   *
   * @param string $entity_type
   *   The entity type name, e.g. "node".
   * @param string $field_name
   *   A name of the field, e.g. "field_category"
   *
   * @return void
   */
  public function storeFilterField(string $entity_type, string $field_name) {
    $state_key = $this->getStateKey($entity_type);
    $existing_filter_fields = \Drupal::state()->get($state_key, []);
    if (array_search($field_name, $existing_filter_fields) === FALSE) {
      $existing_filter_fields[] = $field_name;
      \Drupal::state()->set($state_key, $existing_filter_fields);
    }
  }

  /**
   * Invalidate cache tags for $entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity that has been created/updated.
   *
   * @return void
   */
  public function invalidateForEntity(ContentEntityInterface $entity) {
    $existing_filter_fields = \Drupal::state()->get($this->getStateKey($entity->getEntityTypeId()), []);
    foreach ($existing_filter_fields as $field_name) {
      if ($entity->hasField($field_name) && $entity->{$field_name}->entity) {
        Cache::invalidateTags([$this->buildCacheTag($entity->getEntityTypeId(), $field_name, $entity->{$field_name}->entity->uuid())]);
      }
    }
  }
}
