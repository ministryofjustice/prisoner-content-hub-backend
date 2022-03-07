<?php

namespace Drupal\Tests\prisoner_hub_prison_access\ExistingSite;

use Drupal\Core\Url;
use Drupal\node\NodeInterface;

/**
 * Test that the JSON:API responses for taxonomy terms tagged with prisons and
 * prison categories return the correct response.
 *
 * @group prisoner_hub_prison_access
 */
class PrisonerHubQueryAccessJsonApiByBundleTest extends PrisonerHubQueryAccessTestBase {

  /**
   * Entity types to test.
   *
   * @var string[]
   */
  static $entityTypes = ['node', 'taxonomy_term'];

  /**
   * An array of bundles to check for, keyed by entity type.
   *
   * @var array
   */
  protected $bundlesByEntityType;

  /**
   * Setup the correct bundles to test for.
   */
  protected function setUp(): void {
    parent::setUp();
    foreach (self::$entityTypes as $entityType) {
      $this->bundlesByEntityType[$entityType] = $this->getBundlesWithField($entityType, $this->prisonFieldName);
    }
  }

  /**
   * Get the JSON:API url to test with.
   *
   * @return \Drupal\Core\Url
   *   The URL object to use for the JSON:API request.
   */
  protected function getJsonApiUri(string $prison_name, string $entity_type_id, string $bundle, array $options = []) {
    return Url::fromUri('internal:/jsonapi/prison/' . $prison_name . '/' . $entity_type_id . '/' . $bundle, $options);
  }


  /**
   * Test that no entities are returned in the JSON response, when nothing is
   * tagged with a prison or a prison category.
   */
  public function testEntitiesTaggedWithoutPrisonOrCategory() {
    foreach ($this->bundlesByEntityType as $entity_type_id => $bundles) {
      foreach ($bundles as $bundle) {
        $this->setupEntitiesTaggedWithoutPrisonOrCategory($entity_type_id, $bundle);
        $this->assertJsonApiListResponse([], $this->getJsonApiUri($this->prisonTermMachineName, $entity_type_id, $bundle));
      }
    }
  }

  /**
   * Test that entities are returned in the JSON response, when tagged with a
   * prison (but no category).
   */
  public function testEntitiesTaggedWithPrisonButNoCategory() {
    foreach ($this->bundlesByEntityType as $entity_type_id => $bundles) {
      foreach ($bundles as $bundle) {
        $entities_to_check = $this->setupEntitiesTaggedWithPrisonButNoCategory($entity_type_id, $bundle);
        $this->assertJsonApiListResponse($entities_to_check, $this->getJsonApiUri($this->prisonTermMachineName, $entity_type_id, $bundle));
      }
    }
  }

  /**
   * Test that entities are returned in the JSON response, when tagged with a
   * category (but no prison).
   */
  public function testEntitesTaggedWithCategoryButNoPrison() {
    foreach ($this->bundlesByEntityType as $entity_type_id => $bundles) {
      foreach ($bundles as $bundle) {
        $entities_to_check = $this->setupEntitiesTaggedWithCategoryButNoPrison($entity_type_id, $bundle);
        $this->assertJsonApiListResponse($entities_to_check, $this->getJsonApiUri($this->prisonTermMachineName, $entity_type_id, $bundle));
      }
    }
  }

  /**
   * Test that entities are returned in the JSON response, when tagged with a
   * category and a prison.
   */
  public function testContentTaggedWithPrisonAndCategory() {
    foreach ($this->bundlesByEntityType as $entity_type_id => $bundles) {
      foreach ($bundles as $bundle) {
        $entities_to_check = $this->setupContentTaggedWithPrisonAndCategory($entity_type_id, $bundle);
        $this->assertJsonApiListResponse($entities_to_check, $this->getJsonApiUri($this->prisonTermMachineName, $entity_type_id, $bundle));
      }
    }
  }

  /**
   * Test that correct entities are returned in the JSON response, when tagged
   * with a prison and also excluded by that prison.
   */
  public function testContentTaggedWithPrisonButExcluded() {
    foreach ($this->bundlesByEntityType as $entity_type_id => $bundles) {
      foreach ($bundles as $bundle) {
        $entities_to_check = $this->setupEntitiesTaggedWithPrisonAndExcluded($entity_type_id, $bundle);
        $this->assertJsonApiListResponse($entities_to_check, $this->getJsonApiUri($this->prisonTermMachineName, $entity_type_id, $bundle));
      }
    }
  }

  /**
   * Test that correct entities are returned in the JSON response, when tagged
   * with a prison category and also excluded by that prison.
   */
  public function testContentTaggedWithPrisonCategoryButExcluded() {
    foreach ($this->bundlesByEntityType as $entity_type_id => $bundles) {
      foreach ($bundles as $bundle) {
        $entities_to_check = $this->setupEntitiesTaggedWithPrisonAndExcluded($entity_type_id, $bundle);
        $this->assertJsonApiListResponse($entities_to_check, $this->getJsonApiUri($this->prisonTermMachineName, $entity_type_id, $bundle));
      }
    }
  }

  /**
   * Test that a group OR filter on two different entity reference fields works as expected.
   *
   * @covers prisoner_hub_prison_access_jsonapi_entity_filter_access().
   * @see https://www.drupal.org/project/drupal/issues/3072384
   */
  public function testJsonApiGroupFilters() {
    foreach ($this->bundlesByEntityType as $entity_type_id => $bundles) {
      $bundle = reset($bundles);
      $entities_to_check = [];

      // In order to test this core bug, we need to filter on two different
      // entity reference fields.  For that we will use field_prisons and
      // field_exclude_from_prison (as these fields are the only dependencies
      // of this module).
      $entities_to_check[] = $this->createEntityTaggedWithPrisons($entity_type_id, $bundle, [$this->prisonCategoryTerm->id()], NodeInterface::PUBLISHED, [$this->anotherPrisonTerm->id()]);
      $entities_to_check[] = $this->createEntityTaggedWithPrisons($entity_type_id, $bundle, [$this->prisonTerm->id()]);

      // Create some additional entities that should not appear in the results.
      $this->createEntityTaggedWithPrisons($entity_type_id, $bundle, [$this->anotherPrisonCategoryTerm->id()]);
      $this->createEntityTaggedWithPrisons($entity_type_id, $bundle, [$this->anotherPrisonTerm->id()]);

      $filter = [
        'or_group' => ['group' => ['conjunction' => 'OR']],
        'filter_id_1' => [
          'condition' => [
            'path' => $this->excludeFromPrisonFieldName . '.id',
            'value' => $this->anotherPrisonTerm->uuid(),
            'memberOf' => 'or_group',
          ],
        ],
        'filter_id_2' => [
          'condition' => [
            'path' => $this->prisonFieldName . '.id',
            'value' => $this->prisonTerm->uuid(),
            'memberOf' => 'or_group',
          ],
        ],
      ];
      $options = [
        'query' => ['filter' => $filter],
      ];
      $uri = $this->getJsonApiUri($this->prisonTermMachineName, $entity_type_id, $bundle, $options);
      $this->assertJsonApiListResponse($entities_to_check, $uri);
    }
  }
}
