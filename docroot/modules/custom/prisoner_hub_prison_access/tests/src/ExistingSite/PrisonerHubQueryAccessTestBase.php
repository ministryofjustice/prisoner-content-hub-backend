<?php

namespace Drupal\Tests\prisoner_hub_prison_access\ExistingSite;

use Drupal\Component\Serialization\Json;
use Drupal\content_moderation\ModerationInformationInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\Tests\jsonapi\Functional\JsonApiRequestTestTrait;
use Drupal\Tests\prisoner_hub_test_traits\Traits\JsonApiTrait;
use Drupal\Tests\prisoner_hub_test_traits\Traits\NodeCreationTrait as PrisonerHubNodeCreationTrait;
use weitzman\DrupalTestTraits\Entity\NodeCreationTrait;
use weitzman\DrupalTestTraits\Entity\TaxonomyCreationTrait;
use weitzman\DrupalTestTraits\ExistingSiteBase;

/**
 * Base class that tests jsonapi responses for the correct results.
 *
 * @group prisoner_hub_prison_access
 */
abstract class PrisonerHubQueryAccessTestBase extends ExistingSiteBase {

  use JsonApiRequestTestTrait;
  use JsonApiTrait;
  use NodeCreationTrait;
  use TaxonomyCreationTrait;
  use PrisonerHubPrisonAccessTestTrait;
  use PrisonerHubNodeCreationTrait;

  /**
   * Moderation information service.
   */
  protected ModerationInformationInterface $moderationInformation;

  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * Sets up prison and prison category terms, to be used later when testing.
   */
  protected function setUp(): void {
    parent::setUp();
    $this->moderationInformation = $this->container->get('content_moderation.moderation_information');
    $this->entityTypeManager = $this->container->get('entity_type.manager');
    $this->createPrisonTaxonomyTerms();
  }

  /**
   * Create an entity.
   *
   * @param string $entity_type_id
   *   The entity type id, e.g. "node".
   * @param string $bundle
   *   The bundle (i.e. content type, vocabulary etc.) id.
   * @param array $values
   *   An array of field values.
   *
   * @return null|string
   *   The uuid of the created entity.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function createEntity(string $entity_type_id, string $bundle, array $values) {
    switch ($entity_type_id) {
      case 'node':
        $values['type'] = $bundle;
        // Create a node with a content category due to constraint that content
        // should always have a category or series.
        return $this->createCategorisedNode($values)->uuid();

      case 'taxonomy_term':
        $vocabulary = Vocabulary::load($bundle);
        $term = $this->createTerm($vocabulary, $values);

        // Series and categories require available content to be accessible
        // (via prisoner_hub_taxonomy_access module).
        // @todo Refactor all tests across custom modules to use the same entity creation process.
        if ($bundle == 'series') {
          $this->createNode([
            'field_moj_series' => ['target_id' => $term->id()],
            $this->prisonFieldName => [
              ['target_id' => $this->prisonTerm->id()],
            ],
          ]);
        }
        elseif ($bundle == 'moj_categories') {
          $this->createNode([
            'field_moj_top_level_categories' => ['target_id' => $term->id()],
            $this->prisonFieldName => [
              ['target_id' => $this->prisonTerm->id()],
            ],
          ]);
        }
        return $term->uuid();
    }
  }

  /**
   * Setup entities that are _not_ tagged with either a prison or a category.
   *
   * @param string $entity_type_id
   *   Entity type ID, eg 'node' or 'taxonomy_term'.
   * @param string $bundle
   *   Bundle to create, eg 'page'.
   * @param int $amount
   *   Number of entities to create.
   *
   * @return array
   *   An array of entities to check for.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function setupEntitiesTaggedWithoutPrisonOrCategory(string $entity_type_id, string $bundle, int $amount = 5) {
    // Create some entities that have no values.
    for ($i = 0; $i < $amount; $i++) {
      $this->createEntity($entity_type_id, $bundle, []);
    }
    return [];
  }

  /**
   * Create an entity tagged with a prison(s) but no category.
   *
   * @param string $entity_type_id
   *   Entity type ID, eg 'node' or 'taxonomy_term'.
   * @param string $bundle
   *   Bundle to create, eg 'page'.
   * @param array $prison_ids
   *   Set of term IDs for the prisons for which the entity should be tagged.
   * @param int $status
   *   Whether entity should be published. Should be either
   *   NodeInterface::PUBLISHED or NodeInterface::NOT_PUBLISHED.
   * @param array $excluded_prisons
   *   Optional set of term IDs for the prisons from which the entity should be
   *   excluded.
   *
   * @return string
   *   The uuid of the created entity.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function createEntityTaggedWithPrisons(string $entity_type_id, string $bundle, array $prison_ids, $status = NodeInterface::PUBLISHED, $excluded_prisons = [], string $moderation_state = '') {
    if ($moderation_state) {
      $values = [
        'moderation_state' => $moderation_state,
      ];
    }
    else {
      $values = [
        'status' => $status,
      ];
    }
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
   * @param string $entity_type_id
   *   Entity type ID, eg 'node' or 'taxonomy_term'.
   * @param string $bundle
   *   Bundle to create, eg 'page'.
   * @param int $amount
   *   Number of entities to create.
   *
   * @return array
   *   The entities to check.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
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
   * @param string $entity_type_id
   *   Entity type ID, eg 'node' or 'taxonomy_term'.
   * @param string $bundle
   *   Bundle to create, eg 'page'.
   * @param int $amount
   *   Number of entities to create.
   *
   * @return array
   *   An array of entities to check for.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
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
   * @param string $entity_type_id
   *   Entity type ID, eg 'node' or 'taxonomy_term'.
   * @param string $bundle
   *   Bundle to create, eg 'page'.
   * @param int $amount
   *   Number of entities to create.
   *
   * @return array
   *   An array of entities to check for.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function setupContentTaggedWithPrisonAndCategory(string $entity_type_id, string $bundle, int $amount = 5) {
    $entities_to_check = [];
    for ($i = 0; $i < $amount; $i++) {
      $entities_to_check[] = $this->createEntityTaggedWithPrisons($entity_type_id, $bundle, [
        $this->prisonTerm->id(),
        $this->prisonCategoryTerm->id(),
      ]);
    }

    // Also create some content tagged with a different category and prison.
    for ($i = 0; $i < $amount; $i++) {
      $this->createEntityTaggedWithPrisons($entity_type_id, $bundle, [
        $this->anotherPrisonTerm->id(),
        $this->anotherPrisonCategoryTerm->id(),
      ]);
    }

    return $entities_to_check;
  }

  /**
   * Setup entities that are tagged with a prison and excluded.
   *
   * @param string $entity_type_id
   *   Entity type ID, eg 'node' or 'taxonomy_term'.
   * @param string $bundle
   *   Bundle to create, eg 'page'.
   * @param int $amount
   *   Number of entities to create.
   *
   * @return array
   *   An array of entities to check for.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
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
   * @param string $entity_type_id
   *   Entity type ID, eg 'node' or 'taxonomy_term'.
   * @param string $bundle
   *   Bundle to create, eg 'page'.
   * @param int $amount
   *   Number of entities to create.
   *
   * @return array
   *   An array of entities to check for.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
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
   * Helper function to assert a jsonapi response returns the expected entities.
   *
   * Note this only works with JSON:API responses that return multiple rows.
   * I.e. /jsonapi/node/page, and *not* /jsonapi/node/page/uuid.
   *
   * @param array $entities_to_check
   *   A list of entity uuids to check for in the JSON response.
   * @param \Drupal\Core\Url $url
   *   The URL for the JSON:API request being tested.
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

}
