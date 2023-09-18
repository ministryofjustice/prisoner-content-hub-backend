<?php

namespace Drupal\Tests\prisoner_hub_prison_access\ExistingSite;

use Drupal\Core\Url;
use Drupal\elasticsearch_connector\Entity\Cluster;
use Drupal\search_api\Entity\Index;

/**
 * Tests jsonapi responses for nodes tagged with prisons and prison categories.
 *
 * @group prisoner_hub_prison_access
 */
class PrisonerHubQueryAccessSearchApiTest extends PrisonerHubQueryAccessTestBase {

  /**
   * The search index.
   *
   * @var \Drupal\search_api\IndexInterface
   */
  protected $index;

  /**
   * The JSON:API url.
   *
   * @var \Drupal\Core\Url
   */
  protected $jsonApiUrl;

  /**
   * The elasticsearch client.
   *
   * @var \nodespark\DESConnector\ClientInterface
   */
  protected $client;

  /**
   * The entity type id.
   *
   * @var string
   */
  protected $entityTypeId;

  /**
   * An array of bundles to check for.
   *
   * @var array
   */
  protected $bundles;

  /**
   * Set correct bundles to test for, and the elasticsearch client and index.
   */
  protected function setUp(): void {
    parent::setUp();
    $this->entityTypeId = 'node';
    $this->index = Index::load('content_for_search');
    $this->bundles = [];
    // Get all the bundles for this index.  Note there should only really
    // ever be one datasource, but apparently there can be multiple.
    foreach ($this->index->getDatasources() as $datasource) {
      $this->bundles = array_merge($this->bundles, array_keys($datasource->getBundles()));
    }
    $this->jsonApiUrl = Url::fromUri('internal:/jsonapi/prison/' . $this->prisonTerm->get('machine_name')->getValue()[0]['value'] . '/index/content_for_search');
    $cluster = Cluster::load('elasticsearch');
    $this->client = $this->container->get('elasticsearch_connector.client_manager')->getClientForCluster($cluster);
  }

  /**
   * Test empty response when nothing is tagged with a prison or category.
   */
  public function testEntitiesTaggedWithoutPrisonOrCategory() {
    foreach ($this->bundles as $bundle) {
      $this->setupEntitiesTaggedWithoutPrisonOrCategory($this->entityTypeId, $bundle);
    }
    $this->assertJsonApiListResponse([], $this->jsonApiUrl);
  }

  /**
   * Test entities are returned when tagged with a prison but no category.
   */
  public function testEntitiesTaggedWithPrisonButNoCategory() {
    $entities_to_check = [];
    foreach ($this->bundles as $bundle) {
      $entities_to_check = array_merge($entities_to_check, $this->setupEntitiesTaggedWithPrisonButNoCategory($this->entityTypeId, $bundle));
    }
    $this->assertJsonApiListResponse($entities_to_check, $this->jsonApiUrl);
  }

  /**
   * Test that entities are returned when tagged with a category but no prison.
   */
  public function testEntitesTaggedWithCategoryButNoPrison() {
    $entities_to_check = [];
    foreach ($this->bundles as $bundle) {
      $entities_to_check = array_merge($entities_to_check, $this->setupEntitiesTaggedWithCategoryButNoPrison($this->entityTypeId, $bundle));
    }
    $this->assertJsonApiListResponse($entities_to_check, $this->jsonApiUrl);
  }

  /**
   * Test entities are returned when tagged with a category and a prison.
   */
  public function testContentTaggedWithPrisonAndCategory() {
    $entities_to_check = [];
    foreach ($this->bundles as $bundle) {
      $entities_to_check = array_merge($entities_to_check, $this->setupContentTaggedWithPrisonAndCategory($this->entityTypeId, $bundle));
    }
    $this->assertJsonApiListResponse($entities_to_check, $this->jsonApiUrl);
  }

  /**
   * Test correct entities are returned when tagged with an excluded prison.
   */
  public function testContentTaggedWithPrisonAndExcluded() {
    $entities_to_check = [];
    foreach ($this->bundles as $bundle) {
      $entities_to_check = array_merge($entities_to_check, $this->setupEntitiesTaggedWithPrisonAndExcluded($this->entityTypeId, $bundle));
    }
    $this->assertJsonApiListResponse($entities_to_check, $this->jsonApiUrl);
  }

  /**
   * Test response entities when tagged with a prison category and excluded.
   */
  public function testContentTaggedWithPrisonCategoryAndExcluded() {
    $entities_to_check = [];
    foreach ($this->bundles as $bundle) {
      $entities_to_check = array_merge($entities_to_check, $this->setupEntitiesTaggedWithPrisonCategoryAndExcluded($this->entityTypeId, $bundle));
    }
    $this->assertJsonApiListResponse($entities_to_check, $this->jsonApiUrl);
  }

  /**
   * Refresh the index before we check search results via JSON:API.
   */
  protected function assertJsonApiListResponse(array $entities_to_check, Url $url) {
    $this->index->indexItems();
    // Need to tell elastic to refresh the index immediately.
    $this->client->indices()->refresh();
    parent::assertJsonApiListResponse($entities_to_check, $url);
  }

}
