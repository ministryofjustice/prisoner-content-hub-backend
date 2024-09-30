<?php

namespace Drupal\Tests\page_cache_persist\ExistingSite;

use Drupal\Core\Url;
use Drupal\Tests\prisoner_hub_test_traits\Traits\NodeCreationTrait;
use Drupal\user\Entity\Role;
use Drupal\user\RoleInterface;
use Drush\TestTraits\DrushTestTrait;
use weitzman\DrupalTestTraits\Entity\TaxonomyCreationTrait;
use weitzman\DrupalTestTraits\ExistingSiteBase;

/**
 * Test the page_cache_persist module functionality.
 *
 * @group page_cache_persist
 */
class PageCachePersistTest extends ExistingSiteBase {

  use DrushTestTrait;
  use TaxonomyCreationTrait;
  use NodeCreationTrait;

  /**
   * The url of the node.
   *
   * @var string
   */
  protected string $nodeUrlString;

  /**
   * Set up content and generate cache.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function setUp(): void {
    parent::setUp();
    // Allow anonymous user to access entities without prison context.
    // As we're not testing the prison context part, this is unnecessary.
    // @todo Remove this when tests are refactored, and a single way of
    // creating entities (that includes adding relevant prisons) is used across
    // our tests.
    $role = Role::load(RoleInterface::ANONYMOUS_ID);
    $this->grantPermissions($role, ['view entity without prison context']);

    $node = $this->createCategorisedNode();
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
   * Test the page cache isn't cleared when drupal_flush_all_caches() called.
   */
  public function testCacheClearAll() {
    drupal_flush_all_caches();
    $cache = $this->getCacheItem();
    $this->assertNotFalse($cache);
    $this->assertNotEmpty($cache->data);
  }

  /**
   * Test we can still clear the page cache using drush cache:force-clear-page.
   */
  public function testDrushForceCacheClearPage() {
    $this->drush('cache:force-clear-page');
    $cache = $this->getCacheItem();
    $this->assertFalse($cache);
  }

  /**
   * Test we can still clear the page cache using drush cache:force-clear-all.
   */
  public function testDrushForceCacheClearAll() {
    $this->drush('cache:force-clear-all');
    $cache = $this->getCacheItem();
    $this->assertFalse($cache);
  }

  /**
   * Helper function to get the cache item for a node jsonapi page.
   *
   * @return false|object
   *   Either the cache object if found, otherwise FALSE.
   */
  private function getCacheItem() {
    $cid_parts = [$this->nodeUrlString, ''];
    $cid = implode(':', $cid_parts);
    return \Drupal::cache('page')->get($cid);
  }

}
