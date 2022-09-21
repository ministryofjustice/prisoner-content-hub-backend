<?php

namespace Drupal\jsonapi_filter_cache_tags\EventSubscriber;

use Drupal\jsonapi\CacheableResourceResponse;
use Drupal\jsonapi\Context\FieldResolver;
use Drupal\jsonapi\Query\EntityCondition;
use Drupal\jsonapi\Query\Filter;
use Drupal\jsonapi\ResourceType\ResourceType;
use Drupal\jsonapi_filter_cache_tags\CacheTagsBuilder;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Class ResponseSubscriber.
 */
class ResponseSubscriber implements EventSubscriberInterface {

  /**
   * @var \Drupal\jsonapi_filter_cache_tags\CacheTagsBuilder
   *
   * The cache tags builder service.
   */
  protected $cacheTagsBuilder;

  /**
   * @var \Drupal\jsonapi\Context\FieldResolver
   *
   * The field resolver service.
   */
  protected $fieldResolver;

  /**
   * Constructs a new ResponseSubscriber object.
   */
  public function __construct(CacheTagsBuilder $cache_tags_builder, FieldResolver $field_resolver) {
    $this->cacheTagsBuilder = $cache_tags_builder;
    $this->fieldResolver = $field_resolver;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[KernelEvents::RESPONSE] = ['onResponse', 999];

    return $events;
  }

  /**
   * This method is called when the kernel.response is dispatched.
   *
   * @param \Symfony\Component\EventDispatcher\Event $event
   *   The dispatched event.
   */
  public function onResponse(ResponseEvent $event) {
    $response = $event->getResponse();
    $request = $event->getRequest();
    if ($response instanceof CacheableResourceResponse
      && $request->query->has('filter')
      && $resource_type = $request->attributes->get('resource_type'))
    {
      assert($resource_type instanceof ResourceType);
      $filter = Filter::createFromQueryParameter($request->query->get('filter'), $resource_type, $this->fieldResolver);
      $cache_tags = $this->createCacheTagsFromFilter($filter, $resource_type->getEntityTypeId());
      $this->addCacheTagsToResponse($response, $cache_tags, $resource_type);
    }
  }

  /**
   * Create cache tag(s) from a JSON:API filter.
   *
   * @param \Drupal\jsonapi\Query\Filter $filter
   *   The filter object, originating from the url query.
   * @param string $entity_type_id
   *   The entity type id, e.g. "node".
   *
   * @return array
   *   An array of cache tags, can be empty if none are created.
   */
  protected function createCacheTagsFromFilter(Filter $filter, string $entity_type_id) {
    $cache_tags = [];
    foreach ($filter->root()->members() as $member) {
      // We currently don't support group conditions, or any operator other
      // than "=".  I.e. we don't support "!=", "IN", "NOT IN", etc.
      if ($member instanceof EntityCondition && $member->operator() == '=') {
        $parts = explode('.', $member->field());
        // We are looking for an entity reference field, in the format of:
        // field_reference_name.entity:entity_type.uuid
        if (isset($parts[2]) && $parts[2] == 'uuid') {
          $cache_tags[] = $this->cacheTagsBuilder->buildCacheTag($entity_type_id, $parts[0], $member->value());
          $this->cacheTagsBuilder->storeFilterField($entity_type_id, $parts[0]);
        }
      }
    }
    return $cache_tags;
  }

  /**
   * Add the $cache_tags as cacheable metadata to the $response.
   *
   * We also look for list cache tags (e.g. "node_list") and remove them.
   *
   * @param \Drupal\jsonapi\CacheableResourceResponse $response
   *   The response object.
   * @param array $cache_tags
   *   An array of cache tags, if empty then we do not alter the $response.
   * @param \Drupal\jsonapi\ResourceType\ResourceType $resource_type
   *   The JSON:API resource type.
   *
   * @return void
   */
  protected function addCacheTagsToResponse(CacheableResourceResponse $response, array $cache_tags, ResourceType $resource_type) {
    // Only override cache tags if there is something to be set.
    if (!empty($cache_tags)) {
      // Add on existing cache tags.
      $cache_tags += $response->getCacheableMetadata()->getCacheTags();

      // Remove any existing list cache tags, e.g. "node_list".
      $cache_tags_to_remove = [$resource_type->getEntityTypeId() . '_list'];
      // Drupal also supports bundle specific entity list cache tags. Remove
      // these as well if present.
      if ($resource_type->getBundle()) {
        $cache_tags_to_remove[] = $resource_type->getEntityTypeId() . '_list:' . $resource_type->getBundle();
      }
      $response->getCacheableMetadata()->setCacheTags(array_diff($cache_tags, $cache_tags_to_remove));
    }
  }
}
