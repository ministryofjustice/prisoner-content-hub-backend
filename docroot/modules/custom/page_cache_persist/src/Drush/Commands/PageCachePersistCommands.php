<?php

namespace Drupal\page_cache_persist\Drush\Commands;

use Drupal\Core\Cache\CacheBackendInterface;
use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Page cache persist commands.
 */
final class PageCachePersistCommands extends DrushCommands {

  /**
   * Constructs a PageCachePersistCommands object.
   */
  public function __construct(
    private readonly CacheBackendInterface $pageCache,
  ) {
    parent::__construct();
  }

  /**
   * Instantiates a new instance of this class.
   *
   * This is a factory method that returns a new instance of this class. The
   * factory should pass any needed dependencies into the constructor of this
   * class, but not the container itself.
   */
  public static function create(ContainerInterface $container) {
    return new PageCachePersistCommands(
      $container->get('cache.page'),
    );
  }

  /**
   * Force delete of page cache.
   */
  #[CLI\Command(name: 'cache:force-clear-page', aliases: ['cfcp'])]
  #[CLI\Usage(name: 'cache:force-clear-page', description: 'Force clearing page cache')]
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
  #[CLI\Command(name: 'cache:force-clear-all', aliases: ['cfca'])]
  #[CLI\Usage(name: 'cache:force-clear-all', description: "Force clearing all cache's including the page cache.\nNote this does not accept any arguments. If you want to clear an individual cache use drush cache:clear")]
  public function forceCacheClearAll() {
    drupal_flush_all_caches();
    $this->pageCache->forceDeleteAll();
  }

}
