<?php

namespace Drupal\Tests\prisoner_hub_prison_access\ExistingSite;

use Drupal\Core\Url;
use Drupal\node\NodeInterface;

/**
 * Test the access control when accessing entities directly through JSON:API.
 *
 * We only care about the http status code, since the contents of the response
 * is up to the jsonapi module.
 *
 * @group prisoner_hub_prison_access
 */
class PrisonerHubPrisonAccessJsonApiTest extends PrisonerHubQueryAccessTestBase {

  /**
   * Entity types to test.
   *
   * @var string[]
   */
  private static array $entityTypes = ['node', 'taxonomy_term'];

  /**
   * An array of bundles to check for, keyed by entity type.
   *
   * @var array
   */
  protected array $bundlesByEntityType;

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
   * Get the JSON:API url to make a request on.
   *
   * @param string $prison_name
   *   The prison taxonomy term machine_name.
   * @param string $entity_type_id
   *   The entity type id, e.g. "node".
   * @param string $bundle
   *   The bundle, e.g. "page".
   * @param string $uuid
   *   The uuid to check for.
   *
   * @return \Drupal\Core\Url
   *   A url object that be used to make the request.
   */
  protected function getJsonApiUri(string $prison_name, string $entity_type_id, string $bundle, string $uuid) {
    return Url::fromUri('internal:/jsonapi/prison/' . $prison_name . '/' . $entity_type_id . '/' . $bundle . '/' . $uuid);
  }

  /**
   * Test access denied for entities not tagged with a prison or category.
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
   * Test access for entities tagged with a prison (but no category).
   */
  public function testEntitiesTaggedWithPrisonButNoCategory() {
    foreach ($this->bundlesByEntityType as $entity_type_id => $bundles) {
      foreach ($bundles as $bundle) {
        $uuid_to_check_is_200 = $this->createEntityTaggedWithPrisons($entity_type_id, $bundle, [$this->prisonTerm->id()]);
        $url = $this->getJsonApiUri($this->prisonTermMachineName, $entity_type_id, $bundle, $uuid_to_check_is_200);
        $this->assertJsonApiResponseByStatusCode($url, 200);

        $uuid_to_check_is_403 = $this->createEntityTaggedWithPrisons($entity_type_id, $bundle, [$this->anotherPrisonTerm->id()]);
        $url = $this->getJsonApiUri($this->prisonTermMachineName, $entity_type_id, $bundle, $uuid_to_check_is_403);
        $this->assertJsonApiResponseByStatusCode($url, 403);
      }
    }
  }

  /**
   * Test access for entities tagged with a category (but no prison).
   */
  public function testEntitiesTaggedWithCategoryButNoPrison() {
    foreach ($this->bundlesByEntityType as $entity_type_id => $bundles) {
      foreach ($bundles as $bundle) {
        $uuid_to_check_is_200 = $this->createEntityTaggedWithPrisons($entity_type_id, $bundle, [$this->prisonCategoryTerm->id()]);
        $url = $this->getJsonApiUri($this->prisonTermMachineName, $entity_type_id, $bundle, $uuid_to_check_is_200);
        $this->assertJsonApiResponseByStatusCode($url, 200);

        $uuid_to_check_is_403 = $this->createEntityTaggedWithPrisons($entity_type_id, $bundle, [$this->anotherPrisonCategoryTerm->id()]);
        $url = $this->getJsonApiUri($this->prisonTermMachineName, $entity_type_id, $bundle, $uuid_to_check_is_403);
        $this->assertJsonApiResponseByStatusCode($url, 403);
      }
    }
  }

  /**
   * Test access for entities tagged with both a prison and a category.
   */
  public function testContentTaggedWithPrisonAndCategory() {
    foreach ($this->bundlesByEntityType as $entity_type_id => $bundles) {
      foreach ($bundles as $bundle) {
        $uuid_to_check_is_200 = $this->createEntityTaggedWithPrisons($entity_type_id, $bundle, [
          $this->prisonTerm->id(),
          $this->prisonCategoryTerm->id(),
        ]);
        $url = $this->getJsonApiUri($this->prisonTermMachineName, $entity_type_id, $bundle, $uuid_to_check_is_200);
        $this->assertJsonApiResponseByStatusCode($url, 200);

        $uuid_to_check_is_403 = $this->createEntityTaggedWithPrisons($entity_type_id, $bundle, [
          $this->anotherPrisonTerm->id(),
          $this->anotherPrisonCategoryTerm->id(),
        ]);
        $url = $this->getJsonApiUri($this->prisonTermMachineName, $entity_type_id, $bundle, $uuid_to_check_is_403);
        $this->assertJsonApiResponseByStatusCode($url, 403);
      }
    }
  }

  /**
   * Test access for unpublished entities tagged with a prison and a category.
   */
  public function testContentTaggedWithPrisonAndCategoryUnpublished() {
    foreach ($this->bundlesByEntityType as $entity_type_id => $bundles) {
      $entity_type = $this->entityTypeManager->getDefinition($entity_type_id);
      foreach ($bundles as $bundle) {
        $bundle_is_moderated = $this->moderationInformation->shouldModerateEntitiesOfBundle($entity_type, $bundle);
        $status = $bundle_is_moderated ? NULL : NodeInterface::NOT_PUBLISHED;
        $moderation_state = $bundle_is_moderated ? 'draft' : '';
        $uuid_to_check_is_403 = $this->createEntityTaggedWithPrisons($entity_type_id, $bundle, [
          $this->prisonTerm->id(),
          $this->prisonCategoryTerm->id(),
        ], $status, [], $moderation_state);
        $url = $this->getJsonApiUri($this->prisonTermMachineName, $entity_type_id, $bundle, $uuid_to_check_is_403);
        $this->assertJsonApiResponseByStatusCode($url, 403);
      }
    }
  }

  /**
   * Test access for entities tagged with and excluded from a prison.
   */
  public function testContentTaggedWithPrisonAndExcluded() {
    foreach ($this->bundlesByEntityType as $entity_type_id => $bundles) {
      foreach ($bundles as $bundle) {
        $uuid_to_check_is_403 = $this->createEntityTaggedWithPrisons($entity_type_id, $bundle, [$this->prisonTerm->id()], NodeInterface::PUBLISHED, [$this->prisonTerm->id()]);
        $url = $this->getJsonApiUri($this->prisonTermMachineName, $entity_type_id, $bundle, $uuid_to_check_is_403);
        $this->assertJsonApiResponseByStatusCode($url, 403);
      }
    }
  }

  /**
   * Test entity access when tagged with a prison category and excluded.
   */
  public function testContentTaggedWithPrisonCategoryAndExcluded() {
    foreach ($this->bundlesByEntityType as $entity_type_id => $bundles) {
      foreach ($bundles as $bundle) {
        $uuid_to_check_is_403 = $this->createEntityTaggedWithPrisons($entity_type_id, $bundle, [$this->prisonCategoryTerm->id()], NodeInterface::PUBLISHED, [$this->prisonTerm->id()]);
        $url = $this->getJsonApiUri($this->prisonTermMachineName, $entity_type_id, $bundle, $uuid_to_check_is_403);
        $this->assertJsonApiResponseByStatusCode($url, 403);
      }
    }
  }

  /**
   * Helper function to assert status codes for JSON:API requests.
   *
   * @param \Drupal\Core\Url $url
   *   The url object to use for the JSON:API request.
   * @param int $status_code
   *   The status code to check for, e.g. 200.
   */
  public function assertJsonApiResponseByStatusCode(Url $url, int $status_code) {
    $response = $this->getJsonApiResponse($url);
    $this->assertSame($status_code, $response->getStatusCode(), $url->toString() . ' returns a ' . $status_code . ' response.');
  }

}
