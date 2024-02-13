<?php

namespace Drupal\page_cache_persist\EventSubscriber;

use Drupal\Core\Cache\Cache;
use Drupal\Core\EventSubscriber\CacheRouterRebuildSubscriber as CoreCacheRouterRebuildSubscriber;

/**
 * Class CacheRouterRebuildSubscriber.
 *
 * Decorate the core CacheRouterRebuildSubscriber to prevent http_response tag
 * being cleared.
 */
class CacheRouterRebuildSubscriber extends CoreCacheRouterRebuildSubscriber {

  /**
   * Override the onRouterFinished method.
   *
   * Prevent invalidation of the 'http_response' tag.
   */
  public function onRouterFinished() {
    Cache::invalidateTags(['4xx-response', 'route_match']);
  }

}
