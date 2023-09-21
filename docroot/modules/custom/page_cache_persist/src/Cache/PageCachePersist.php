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
   * PageCacheOverride constructor.
   *
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *   The "inner" cache backend service.
   */
  public function __construct(protected CacheBackendInterface $cache) {
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
   * There is nowhere in Drupal core that calls this on the page cache.  So
   * for now we will leave it working as normal.
   */
  public function invalidateAll() {
    $this->cache->invalidateAll();
  }

  /**
   * {@inheritdoc}
   */
  public function get($cid, $allow_invalid = FALSE) {
    return $this->cache->get($cid, $allow_invalid);
  }

  /**
   * {@inheritdoc}
   */
  public function getMultiple(&$cids, $allow_invalid = FALSE) {
    return $this->cache->getMultiple($cids, $allow_invalid);
  }

  /**
   * {@inheritdoc}
   */
  public function set($cid, $data, $expire = Cache::PERMANENT, array $tags = []) {
    return $this->cache->set($cid, $data, $expire, $tags);
  }

  /**
   * {@inheritdoc}
   */
  public function setMultiple(array $items) {
    return $this->cache->setMultiple($items);
  }

  /**
   * {@inheritdoc}
   */
  public function delete($cid) {
    $this->cache->delete($cid);
  }

  /**
   * {@inheritdoc}
   */
  public function deleteMultiple(array $cids) {
    $this->cache->deleteMultiple($cids);
  }

  /**
   * {@inheritdoc}
   */
  public function invalidate($cid) {
    $this->cache->invalidate($cid);
  }

  /**
   * {@inheritdoc}
   */
  public function invalidateMultiple(array $cids) {
    $this->cache->invalidateMultiple($cids);
  }

  /**
   * {@inheritdoc}
   */
  public function garbageCollection() {
    return $this->cache->garbageCollection();
  }

  /**
   * {@inheritdoc}
   */
  public function removeBin() {
    return $this->cache->removeBin();
  }

}
