<?php

namespace Drupal\page_cache_persist\Commands;

use Drupal\Core\Cache\CacheBackendInterface;
use Drush\Commands\DrushCommands;

/**
 * Page cache persist commands.
 */
class PageCachePersistCommands extends DrushCommands {

  /**
   * The page cache service, which will be ovverriden by this module.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $pageCache;

  public function __construct(CacheBackendInterface $page_cache) {
    $this->pageCache = $page_cache;
  }

  /**
   * Force delete of page cache.
   *
   * @command cache:force-clear-page
   *
   * @usage cache:force-clear-page
   *   Force clearing page cache.
   *
   * @aliases fcp
   */
  public function forcecCacheClearPage() {
    $this->pageCache->forceDeleteAll();
  }

}
