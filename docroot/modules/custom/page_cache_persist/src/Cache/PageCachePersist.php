<?php

namespace Drupal\page_cache_persist\Cache;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheBackendInterface;

/**
 * Class PageCachePersist.
 *
 * This decorates the default page.cache service.
 * Note we cannot extend another class, as we do not know which type of cache
 * backend is being used (e.g. database, redis, etc).  So we must implement each
 * of the interface methods and call the inner injected service.
 */
class PageCachePersist implements CacheBackendInterface {

  /**
   * The "inner" cache bin.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cache;

  /**
   * PageCacheOverride constructor.
   *
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *   The "inner" cache backend service.
   */
  public function __construct(CacheBackendInterface $cache) {
    $this->cache = $cache;
  }

  /**
   * Override deleteAll() with nothing.
   *
   * This prevents the page cache from being cleared.
   */
  public function deleteAll() {
    // Do nothing.
  }

  /**
   * Provide an alternative way to delete the entire page cache.
   */
  public function forceDeleteAll() {
    $this->cache->deleteAll();
  }

  /**
   * We do not currently need to override invalidateAll().
   *
   * There is no where in Drupal core that calls this on the page cache.  So
   * for now we will leave it working as normal.
   */
  public function invalidateAll() {
    $this->cache->invalidateAll();
  }

  /**
   * @inheritDoc
   */
  public function get($cid, $allow_invalid = FALSE) {
    return $this->cache->get($cid, $allow_invalid);
  }

  /**
   * @inheritDoc
   */
  public function getMultiple(&$cids, $allow_invalid = FALSE) {
    return $this->cache->getMultiple($cids, $allow_invalid);
  }

  /**
   * @inheritDoc
   */
  public function set($cid, $data, $expire = Cache::PERMANENT, array $tags = []) {
    return $this->cache->set($cid, $data, $expire, $tags);
  }

  /**
   * @inheritDoc
   */
  public function setMultiple(array $items) {
    return $this->cache->setMultiple($items);
  }

  /**
   * @inheritDoc
   */
  public function delete($cid) {
    $this->cache->delete($cid);
  }

  /**
   * @inheritDoc
   */
  public function deleteMultiple(array $cids) {
    $this->cache->deleteMultiple($cids);
  }

  /**
   * @inheritDoc
   */
  public function invalidate($cid) {
    $this->cache->invalidate($cid);
  }

  /**
   * @inheritDoc
   */
  public function invalidateMultiple(array $cids) {
    $this->cache->invalidateMultiple($cids);
  }

  /**
   * @inheritDoc
   */
  public function garbageCollection() {
    return $this->cache->garbageCollection();
  }

  /**
   * @inheritDoc
   */
  public function removeBin() {
    return $this->cache->removeBin();
  }
}
