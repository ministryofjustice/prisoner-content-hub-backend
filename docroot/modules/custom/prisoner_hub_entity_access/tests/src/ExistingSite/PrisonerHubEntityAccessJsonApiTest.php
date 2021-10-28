<?php

namespace Drupal\Tests\prisoner_hub_entity_access\ExistingSite;

use Drupal\Core\Url;

/**
 * Test that the correct access is applied when accessing entities directly
 * through JSON:API.  We only care about the http status code, since the
 * contents of the response is up to the jsonapi module.
 *
 * @group prisoner_hub_entity_access
 */
class PrisonerHubEntityAccessJsonApiTest extends PrisonerHubQueryAccessTestBase {

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
   * Get the JSON:API url to make a request on.
   *
   * @param string $prison_name
   *   The prison taxonomy term machine_name.
   * @param string $entity_type_id
   *   The entity type id, e.g. "node".
   * @param string $bundle
   *  The bundle, e.g. "page".
   * @param string $uuid
   *  The uuid to check for.
   *
   * @return \Drupal\Core\Url
   *   A url object that be used to make the request.
   */
  protected function getJsonApiUri(string $prison_name, string $entity_type_id, string $bundle, string $uuid) {
    return Url::fromUri('internal:/jsonapi/prison/' . $prison_name . '/' . $entity_type_id . '/' . $bundle . '/' . $uuid);
  }


  /**
   * Test that we cannot access the entities, when not tagged with a prison
   * or a prison category.
   */
  public function testEntitiesTaggedWithoutPrisonOrCategory() {
    foreach ($this->bundlesByEntityType as $entity_type_id => $bundles) {
      foreach ($bundles as $bundle) {
        $uuid_to_check = $this->createEntity($entity_type_id, $bundle, []);
        $url = $this->getJsonApiUri($this->prisonTermMachineName, $entity_type_id, $bundle, $uuid_to_check);
        $this->assertJsonApiResponseByStatusCode($url, 403);
      }
    }
  }

  /**
   * Test that we can access the correct entities, when tagged with a prison
   * (but no category).
   */
  public function testEntitiesTaggedWithPrisonButNoCategory() {
    foreach ($this->bundlesByEntityType as $entity_type_id => $bundles) {
      foreach ($bundles as $bundle) {
        $uuid_to_check_is_200 = $this->createEntityTaggedWithPrisonButNoCategory($entity_type_id, $bundle, $this->prisonTerm->id());
        $url = $this->getJsonApiUri($this->prisonTermMachineName, $entity_type_id, $bundle, $uuid_to_check_is_200);
        $this->assertJsonApiResponseByStatusCode($url, 200);

        $uuid_to_check_is_403 = $this->createEntityTaggedWithPrisonButNoCategory($entity_type_id, $bundle, $this->anotherPrisonTerm->id());
        $url = $this->getJsonApiUri($this->prisonTermMachineName, $entity_type_id, $bundle, $uuid_to_check_is_403);
        $this->assertJsonApiResponseByStatusCode($url, 403);
      }
    }
  }

  /**
   * Test that we can access the correct entities, when tagged with a category
   * (but no prison).
   */
  public function testEntitesTaggedWithCategoryButNoPrison() {
    foreach ($this->bundlesByEntityType as $entity_type_id => $bundles) {
      foreach ($bundles as $bundle) {
        $uuid_to_check_is_200 = $this->createEntityTaggedWithCategoryButNoPrison($entity_type_id, $bundle, $this->prisonCategoryTerm->id());
        $url = $this->getJsonApiUri($this->prisonTermMachineName, $entity_type_id, $bundle, $uuid_to_check_is_200);
        $this->assertJsonApiResponseByStatusCode($url, 200);

        $uuid_to_check_is_403 = $this->createEntityTaggedWithCategoryButNoPrison($entity_type_id, $bundle, $this->anotherPrisonCategoryTerm->id());
        $url = $this->getJsonApiUri($this->prisonTermMachineName, $entity_type_id, $bundle, $uuid_to_check_is_403);
        $this->assertJsonApiResponseByStatusCode($url, 403);
      }
    }
  }

  /**
   * Test that we can access the correct entities, when tagged with both a
   * prison and a category.
   */
  public function testContentTaggedWithPrisonAndCategory() {
    foreach ($this->bundlesByEntityType as $entity_type_id => $bundles) {
      foreach ($bundles as $bundle) {
        $uuid_to_check_is_200 = $this->createEntityTaggedWithPrisonAndCategory($entity_type_id, $bundle, $this->prisonTerm->id(), $this->prisonCategoryTerm->id());
        $url = $this->getJsonApiUri($this->prisonTermMachineName, $entity_type_id, $bundle, $uuid_to_check_is_200);
        $this->assertJsonApiResponseByStatusCode($url, 200);

        $uuid_to_check_is_403 = $this->createEntityTaggedWithPrisonAndCategory($entity_type_id, $bundle, $this->anotherPrisonTerm->id(), $this->anotherPrisonCategoryTerm->id());
        $url = $this->getJsonApiUri($this->prisonTermMachineName, $entity_type_id, $bundle, $uuid_to_check_is_403);
        $this->assertJsonApiResponseByStatusCode($url, 403);
      }
    }
  }


  /**
   * @param \Drupal\Core\Url $url
   *   The url object to use for the JSON:API request.
   * @param int $status_code
   *   The status code to check for, e.g. 200.
   */
  function assertJsonApiResponseByStatusCode(Url $url, int $status_code) {
    $response = $this->getJsonApiResponse($url);
    $this->assertSame($status_code, $response->getStatusCode(), $url->toString() . ' returns a ' . $status_code . ' response.');
  }
}
