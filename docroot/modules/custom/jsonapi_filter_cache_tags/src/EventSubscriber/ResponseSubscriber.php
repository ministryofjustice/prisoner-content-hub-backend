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
    if ($response instanceof CacheableResourceResponse && $request->query->has('filter')) {
      $resource_type = $request->attributes->get('resource_type');
      if ($resource_type instanceof ResourceType) {
        $cache_tags = [];
        $filter = Filter::createFromQueryParameter($request->query->get('filter'), $resource_type, $this->fieldResolver);
        foreach ($filter->root()->members() as $member) {
          // We currently don't support group conditions, or any operator other
          // than =.
          if ($member instanceof EntityCondition && $member->operator() == '=') {
            $parts = explode('.', $member->field());
            // We are looking for an entity reference field, in the format of:
            // field_reference_name.entity:entity_type.uuid
            if (isset($parts[2]) && $parts[2] == 'uuid') {
              $cache_tags[] = $this->cacheTagsBuilder->buildCacheTag($resource_type->getEntityTypeId(), $parts[0], $member->value());
              $this->cacheTagsBuilder->storeFilterField($resource_type->getEntityTypeId(), $parts[0]);
            }
          }
        }
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
  }
}
