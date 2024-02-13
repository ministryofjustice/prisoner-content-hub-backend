<?php

namespace Drupal\page_cache_persist\Commands;

use Drupal\Core\Cache\CacheBackendInterface;
use Drush\Commands\DrushCommands;

/**
 * Page cache persist commands.
 */
class PageCachePersistCommands extends DrushCommands {

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Cache\CacheBackendInterface $pageCache
   *   Page cache service, which will be overridden by this module.
   */
  public function __construct(protected CacheBackendInterface $pageCache) {
    parent::__construct();
  }

  /**
   * Force delete of page cache.
   *
   * @command cache:force-clear-page
   *
   * @usage cache:force-clear-page
   *   Force clearing page cache.
   */
  public function forceCacheClearPage() {
    $this->pageCache->forceDeleteAll();
  }

  /**
   * Force delete of all cache's including the page cache.
   *
   * @command cache:force-clear-all
   *
   * @usage cache:force-clear-all
   *   Force clearing all cache's including the page cache.
   *   Note this does not accept any arguments.  If you want to clear an
   *   individual cache use drush cache:clear
   */
  public function forceCacheClearAll() {
    drupal_flush_all_caches();
    $this->pageCache->forceDeleteAll();
  }

}
