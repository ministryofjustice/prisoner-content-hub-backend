<?php

namespace Drupal\Tests\prisoner_hub_entity_access\ExistingSite;

use Drupal\Core\Url;

/**
 * Test that the JSON:API responses for taxonomy terms tagged with prisons and
 * prison categories return the correct response.
 *
 * @group prisoner_hub_entity_access
 */
class PrisonerHubQueryAccessJsonApiByBundleTest extends PrisonerHubQueryAccessTestBase {

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
    // Get the list of content types with the prison field enabled.
    /** @var \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager */
    $entityFieldManager = $this->container->get('entity_field.manager');
    $entityFieldManager->getFieldMap();
    $this->bundlesByEntityType['node'] = $entityFieldManager->getFieldMap()['node'][$this->prisonFieldName]['bundles'];
    $this->bundlesByEntityType['taxonomy_term'] = $entityFieldManager->getFieldMap()['taxonomy_term'][$this->prisonFieldName]['bundles'];
  }

  /**
   * Get the JSON:API url to test with.
   *
   * @return \Drupal\Core\Url
   *   The URL object to use for the JSON:API request.
   */
  protected function getJsonApiUri(string $prison_name, string $entity_type_id, string $bundle) {
    return Url::fromUri('internal:/jsonapi/prison/' . $prison_name . '/' . $entity_type_id . '/' . $bundle);
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
   * Test that  entities are returned in the JSON response, when tagged with a
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
}
