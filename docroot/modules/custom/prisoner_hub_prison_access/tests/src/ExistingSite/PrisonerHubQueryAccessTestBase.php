<?php

namespace Drupal\Tests\prisoner_hub_prison_access\ExistingSite;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\Tests\jsonapi\Functional\JsonApiRequestTestTrait;
use Drupal\Tests\node\Traits\NodeCreationTrait;
use GuzzleHttp\RequestOptions;
use weitzman\DrupalTestTraits\Entity\TaxonomyCreationTrait;
use weitzman\DrupalTestTraits\ExistingSiteBase;

/**
 * Base class that tests jsonapi responses for the correct results.
 *
 * @group prisoner_hub_prison_access
 */
abstract class PrisonerHubQueryAccessTestBase extends ExistingSiteBase {

  use JsonApiRequestTestTrait;
  use NodeCreationTrait;
  use TaxonomyCreationTrait;
  use PrisonerHubPrisonAccessTestTrait;

  /**
   * Sets up prison and prison category terms, to be used later when testing.
   */
  protected function setUp(): void {
    parent::setUp();
    $this->createPrisonTaxonomyTerms();
  }


  /**
   * Create an entity.
   *
   * @param string $entity_type_id
   *   The entity type id, e.g. "node".
   * @param string $bundle
   *   The bundle (i.e. content type, vocabulary etc) id.
   * @param $values
   *   An array of field values.
   *
   * @return int
   *   The uuid of the created entity.
   */
  protected function createEntity(string $entity_type_id, string $bundle, array $values) {
    switch ($entity_type_id) {
      case 'node':
        $values['type'] = $bundle;
        return $this->createNode($values)->uuid();

      case 'taxonomy_term':
        $vocabulary = Vocabulary::load($bundle);
        $term = $this->createTerm($vocabulary, $values);

        // Series require available content to be accessible
        // (via prisoner_hub_series_access module).
        // TODO: Refactor all tests across custom modules to use the same entity creation process.
        if ($bundle == 'series') {
          $this->createNode([
            'field_moj_series' => ['target_id' => $term->id()],
            $this->prisonFieldName => [
              ['target_id' => $this->prisonTerm->id()]
            ],
          ]);
        }
        return $term->uuid();
    }
  }

  /**
   * Setup entities that are _not_ tagged with eith a prison or a category.
   *
   * @return array
   *   An array of entities to check for.
   */
  protected function setupEntitiesTaggedWithoutPrisonOrCategory(string $entity_type_id, string $bundle, int $amount = 5) {
    // Create some entities that have no values.
    for ($i = 0; $i < $amount; $i++) {
      $this->createEntity($entity_type_id, $bundle, []);
    }
    return [];
  }

  /**
   * Create an entity tagged with a prison but no category.
   *
   * @return int
   *   The uuid of the created entity.
   */
  public function createEntityTaggedWithPrisons(string $entity_type_id, string $bundle, array $prison_ids, $status = NodeInterface::PUBLISHED, $excluded_prisons = []) {
    $values = [
      'status' => $status,
    ];
    foreach ($prison_ids as $prison_id) {
      $values[$this->prisonFieldName][] = ['target_id' => $prison_id];
    }
    foreach ($excluded_prisons as $excluded_prison_id) {
      $values[$this->excludeFromPrisonFieldName][] = ['target_id' => $excluded_prison_id];
    }

    return $this->createEntity($entity_type_id, $bundle, $values);
  }

  /**
   * Setup entities that are tagged with a prison but _no_ category.
   *
   * @return array
   *   An array of entities to check for.
   */
  protected function setupEntitiesTaggedWithPrisonButNoCategory(string $entity_type_id, string $bundle, int $amount = 5) {
    $entities_to_check = [];
    for ($i = 0; $i < $amount; $i++) {
      $entities_to_check[] = $this->createEntityTaggedWithPrisons($entity_type_id, $bundle, [$this->prisonTerm->id()]);
    }

    // Also create some content tagged with a different prison.
    for ($i = 0; $i < $amount; $i++) {
      $this->createEntityTaggedWithPrisons($entity_type_id, $bundle, [$this->anotherPrisonTerm->id()]);
    }

    // Also create some unpublished entities.
    for ($i = 0; $i < $amount; $i++) {
      $this->createEntityTaggedWithPrisons($entity_type_id, $bundle, [$this->prisonTerm->id()], NodeInterface::NOT_PUBLISHED);
    }

   return $entities_to_check;
  }

  /**
   * Setup entities that are tagged with a category but _no_ prison.
   *
   * @return array
   *   An array of entities to check for.
   */
  protected function setupEntitiesTaggedWithCategoryButNoPrison(string $entity_type_id, string $bundle, int $amount = 5) {
    $entities_to_check = [];
    for ($i = 0; $i < $amount; $i++) {
      $entities_to_check[] = $this->createEntityTaggedWithPrisons($entity_type_id, $bundle, [$this->prisonCategoryTerm->id()]);
    }

    // Also create some content tagged with a different prison category.
    for ($i = 0; $i < $amount; $i++) {
      $this->createEntityTaggedWithPrisons($entity_type_id, $bundle, [$this->anotherPrisonCategoryTerm->id()]);
    }

    return $entities_to_check;
  }

  /**
   * Setup entities that are tagged with a prison _and_ a category.
   *
   * @return array
   *   An array of entities to check for.
   */
  protected function setupContentTaggedWithPrisonAndCategory(string $entity_type_id, string $bundle, int $amount = 5) {
    $entities_to_check = [];
    for ($i = 0; $i < $amount; $i++) {
      $entities_to_check[] = $this->createEntityTaggedWithPrisons($entity_type_id, $bundle, [$this->prisonTerm->id(), $this->prisonCategoryTerm->id()]);
    }

    // Also create some content tagged with a different category and prison.
    for ($i = 0; $i < $amount; $i++) {
      $this->createEntityTaggedWithPrisons($entity_type_id, $bundle, [$this->anotherPrisonTerm->id(), $this->anotherPrisonCategoryTerm->id()]);
    }

    return $entities_to_check;
  }

  /**
   * Setup entities that are tagged with a prison and excluded.
   *
   * @return array
   *   An array of entities to check for.
   */
  protected function setupEntitiesTaggedWithPrisonAndExcluded(string $entity_type_id, string $bundle, int $amount = 5) {
    $entities_to_check = [];
    for ($i = 0; $i < $amount; $i++) {
      $entities_to_check[] = $this->createEntityTaggedWithPrisons($entity_type_id, $bundle, [$this->prisonTerm->id()]);
    }

    // Also create some content tagged with same prison but excluded.
    for ($i = 0; $i < $amount; $i++) {
      $this->createEntityTaggedWithPrisons($entity_type_id, $bundle, [$this->prisonTerm->id()], NodeInterface::PUBLISHED, [$this->prisonTerm->id()]);
    }

    return $entities_to_check;
  }

  /**
   * Setup entities that are tagged with a prison and excluded.
   *
   * @return array
   *   An array of entities to check for.
   */
  protected function setupEntitiesTaggedWithPrisonCategoryAndExcluded(string $entity_type_id, string $bundle, int $amount = 5) {
    $entities_to_check = [];
    for ($i = 0; $i < $amount; $i++) {
      $entities_to_check[] = $this->createEntityTaggedWithPrisons($entity_type_id, $bundle, [$this->prisonCategoryTerm->id()]);
    }

    // Also create some content tagged with same prison but excluded.
    for ($i = 0; $i < $amount; $i++) {
      $this->createEntityTaggedWithPrisons($entity_type_id, $bundle, [$this->prisonCategoryTerm->id()], NodeInterface::PUBLISHED, [$this->prisonTerm->id()]);
    }

    return $entities_to_check;
  }

  /**
   * Helper function to assert that a jsonapi response returns the expected entities.
   *
   * Note this only works with JSON:API responses that return multiple rows.
   * I.e. /jsonapi/node/page, and *not* /jsonapi/node/page/uuid.
   *
   * @param array $entities_to_check
   *   A list of entity uuids to check for in the JSON response.
   * @param string $bundle
   *   The bundle machine name to check for.
   */
  protected function assertJsonApiListResponse(array $entities_to_check, Url $url) {
    $response = $this->getJsonApiResponse($url);
    $this->assertSame(200, $response->getStatusCode(), $url->toString() . ' returns a 200 response.');
    $response_document = Json::decode((string) $response->getBody());
    $message = 'JSON response returns the correct results on url: ' . $url->toString();
    if (empty($entities_to_check)) {
      $this->assertEmpty($response_document['data'], $message);
    }
    else {
      $this->assertEqualsCanonicalizing($entities_to_check, array_map(static function (array $data) {
        return $data['id'];
      }, $response_document['data']), $message);
    }
  }

  /**
   * Get a response from a JSON:API url.
   *
   * @param \Drupal\Core\Url $url
   *   The url object to use for the JSON:API request.
   *
   * @return \Psr\Http\Message\ResponseInterface
   *   The response object.
   */
  function getJsonApiResponse(Url $url) {
    $request_options = [];
    $request_options[RequestOptions::HEADERS]['Accept'] = 'application/vnd.api+json';
    return $this->request('GET', $url, $request_options);
  }

}
