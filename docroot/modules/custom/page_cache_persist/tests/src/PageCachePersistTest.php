<?php

namespace Drupal\Tests\page_cache_persist\ExistingSite;

use Drupal\Core\Url;
use Drush\TestTraits\DrushTestTrait;
use weitzman\DrupalTestTraits\ExistingSiteBase;

/**
 * Test the page_cache_persist module functionality.
 *
 * @group page_cache_persist
 */
class PageCachePersistTest extends ExistingSiteBase {

  use DrushTestTrait;

  /**
   * The url of the node.
   *
   * @var string
   */
  protected $nodeUrlString;

  /**
   * Set up content and generate cache.
   */
  protected function setUp(): void {
    parent::setUp();
    $node = $this->createNode();
    $url = Url::fromRoute('jsonapi.node.individual', ['entity' => $node->uuid()]);
    $url->setAbsolute(TRUE);
    $this->nodeUrlString = $url->toString();
    // Visit the page to create the cache entry.
    $this->visit($this->nodeUrlString);
  }

  /**
   * Test that the page cache is working correctly.
   */
  public function testPageCacheCreated() {
    $cid_parts = [$this->nodeUrlString, ''];
    $cache = $this->getCacheItem();
    $this->assertNotFalse($cache);
    $this->assertNotEmpty($cache->data);
  }

  /**
   * Test that the page cache isn't cleared when drush cache:rebuild is run.
   */
  public function testDrushCacheRebuild() {
    $this->drush('cache:rebuild');
    $cache = $this->getCacheItem();
    $this->assertNotFalse($cache);
    $this->assertNotEmpty($cache->data);
  }

  /**
   * Test that the page cache isn't cleared when drupal_flush_all_caches() is
   * called.
   */
  public function testCacheClearAll() {
    drupal_flush_all_caches();
    $cache = $this->getCacheItem();
    $this->assertNotFalse($cache);
    $this->assertNotEmpty($cache->data);
  }

  /**
   * Test that we can still clear the page cache using drush cache:force-clear-page.
   */
  public function testDrushForceCacheClear() {
    $this->drush('cache:force-clear-page');
    $cache = $this->getCacheItem();
    $this->assertFalse($cache);
  }

  /**
   * Helper function to get the cache item for the node jsonapi page we are
   * testing with.
   *
   * @return FALSE|object
   *   Either the cache object if found, otherwise FALSE.
   */
  private function getCacheItem() {
    $cid_parts = [$this->nodeUrlString, ''];
    $cid = implode(':', $cid_parts);
    return \Drupal::cache('page')->get($cid);
  }

}
