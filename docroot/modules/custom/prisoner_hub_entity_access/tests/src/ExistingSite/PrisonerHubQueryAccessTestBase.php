<?php

namespace Drupal\Tests\prisoner_hub_entity_access\ExistingSite;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Url;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\Tests\jsonapi\Functional\JsonApiRequestTestTrait;
use GuzzleHttp\RequestOptions;
use weitzman\DrupalTestTraits\Entity\NodeCreationTrait;
use weitzman\DrupalTestTraits\Entity\TaxonomyCreationTrait;
use weitzman\DrupalTestTraits\ExistingSiteBase;

/**
 * Base class that tests jsonapi responses for the correct results.
 *
 * @group prisoner_hub_entity_access
 */
abstract class PrisonerHubQueryAccessTestBase extends ExistingSiteBase {

  use TaxonomyCreationTrait;
  use NodeCreationTrait;
  use JsonApiRequestTestTrait;

  /**
   * Return the entity type id.
   *
   * @return string
   */
  abstract protected function getEntityTypeId();

  /**
   * Create an entity.
   *
   * As each entity type has slightly different ways that they are created,
   * this method must be implemented by an extending class.
   *
   * @param string $bundle
   *   The bundle (i.e. content type, vocabulary etc) id.
   * @param $values
   *   An array of field values.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   */
  abstract protected function createEntity(string $bundle, array $values);

  /**
   * The "current" prison taxonomy term.
   *
   * @var \Drupal\taxonomy\Entity\Term
   */
  protected $prisonTerm;

  /**
   * Another prison taxonomy term, that is _not_ the "current".
   *
   * @var \Drupal\taxonomy\Entity\Term
   */
  protected $anotherPrisonTerm;

  /**
   * A prison category term, that is associated with the "current" prison.
   *
   * @var \Drupal\taxonomy\Entity\Term
   */
  protected $prisonCategoryTerm;

  /**
   * Another prison category term, that is _not_ associated with the "current" prison.
   *
   * @var \Drupal\taxonomy\Entity\Term
   */
  protected $anotherPrisonCategoryTerm;

  /**
   * The prison reference field name.
   *
   * @var String
   */
  protected $prisonFieldName;

  /**
   * The prison category reference field name.
   *
   * @var String
   */
  protected $prisonCategoryFieldName;

  /**
   * An array of bundles to check for, automatically generated in setUp().
   *
   * @var array
   */
  protected $bundles;

  /**
   * Sets up prison and prison category terms, to be used later when testing.
   */
  protected function setUp() {
    parent::setUp();

    $this->prisonFieldName = $this->container->getParameter('prisoner_hub_entity_access.prison_field_name');
    $this->prisonCategoryFieldName = $this->container->getParameter('prisoner_hub_entity_access.category_field_name');

    // Get the list of content types with the prison field enabled.
    /** @var \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager */
    $entityFieldManager = $this->container->get('entity_field.manager');
    $entityFieldManager->getFieldMap();
    $this->bundles = $entityFieldManager->getFieldMap()[$this->getEntityTypeId()][$this->prisonFieldName]['bundles'];


    $vocab_prison_categories = Vocabulary::load('prison_category');
    $this->prisonCategoryTerm = $this->createTerm($vocab_prison_categories);

    $vocab_prisons = Vocabulary::load('prisons');
    $values = [
      $this->prisonCategoryFieldName => [
        ['target_id' => $this->prisonCategoryTerm->id()],
      ],
    ];
    $this->prisonTerm = $this->createTerm($vocab_prisons, $values);
    $this->prisonTermMachineName = $this->prisonTerm->get('machine_name')->getValue()[0]['value'];

    // Create alternative prison and prison category taxonomy terms.
    // We will tag some content with this, to ensure it does not appear.
    $this->anotherPrisonCategoryTerm = $this->createTerm($vocab_prison_categories);
    $values = [
      $this->prisonCategoryFieldName => [
        ['target_id' => $this->anotherPrisonCategoryTerm->id()],
      ],
    ];
    $this->anotherPrisonTerm = $this->createTerm($vocab_prisons, $values);
  }

  /**
   * Test that no entities are returned in the JSON response, when nothing is
   * tagged with a prison or a prison category.
   */
  public function testNoEntitiesTaggedWithPrisonOrCategory() {
    foreach ($this->bundles as $bundle) {
      // Create some nodes that have no values.
      for ($i = 0; $i < 5; $i++) {
        $this->createEntity($bundle, []);
      }
      $this->assertJsonResponse([], $bundle);
    }
  }

  /**
   * Test that entities are returned in the JSON response, when tagged with a
   * prison (but no category).
   */
  public function testEntitiesTaggedWithPrisonButNoCategory() {
    foreach ($this->bundles as $bundle) {
      $entities_to_check = [];
      for ($i = 0; $i < 5; $i++) {
        $values = [
          $this->prisonFieldName => [
            ['target_id' => $this->prisonTerm->id()]
          ],
        ];
        $entities_to_check[] = $this->createEntity($bundle, $values)->uuid();
      }

      // Also create some content tagged with a different prison.
      for ($i = 0; $i < 5; $i++) {
        $values = [
          $this->prisonFieldName => [
            ['target_id' => $this->anotherPrisonTerm->id()],
          ],
        ];
        $this->createEntity($bundle, $values);
      }

      $this->assertJsonResponse($entities_to_check, $bundle);
    }
  }

  /**
   * Test that entities are returned in the JSON response, when tagged with a
   * category (but no prison).
   */
  public function testContentTaggedWithCategoryButNoPrison() {
    foreach ($this->bundles as $bundle) {
      $entities_to_check = [];
      for ($i = 0; $i < 5; $i++) {
        $values = [
          $this->prisonCategoryFieldName => [
            ['target_id' => $this->prisonCategoryTerm->id()],
          ],
        ];
        $entities_to_check[] = $this->createEntity($bundle, $values)->uuid();
      }

      // Also create some content tagged with a different prison category.
      for ($i = 0; $i < 5; $i++) {
        $values = [
          $this->prisonCategoryFieldName => [
            ['target_id' => $this->anotherPrisonCategoryTerm->id()],
          ],
        ];
        $this->createEntity($bundle, $values)->uuid();
      }

      $this->assertJsonResponse($entities_to_check, $bundle);
    }
  }

  /**
   * Test that  entities are returned in the JSON response, when tagged with a
   * category and a prison.
   */
  public function testContentTaggedWithPrisonAndCategory() {
    foreach ($this->bundles as $bundle) {
      $entities_to_check = [];
      for ($i = 0; $i < 5; $i++) {
        $values = [
          $this->prisonFieldName => [
            ['target_id' => $this->prisonTerm->id()],
          ],
          $this->prisonCategoryFieldName => [
            ['target_id' => $this->prisonTerm->id()],
          ],
        ];
        $entities_to_check[] = $this->createEntity($bundle, $values)->uuid();
      }

      // Also create some content tagged with a different category and prison.
      for ($i = 0; $i < 5; $i++) {
        $values = [
          $this->prisonFieldName => [
            ['target_id' => $this->anotherPrisonTerm->id()]
          ],
          $this->prisonCategoryFieldName => [
            ['target_id' => $this->anotherPrisonCategoryTerm->id()],
          ],
        ];
        $this->createEntity($bundle, $values)->uuid();
      }

      $this->assertJsonResponse($entities_to_check, $bundle);
    }
  }

  /**
   * Helper function to assert that a jsonapi response returns the expected entities.
   *
   * @param array $entities_to_check
   *   A list of entity uuids to check for in the JSON response.
   * @param string $bundle
   *   The bundle machine name to check for.
   */
  protected function assertJsonResponse(array $entities_to_check, string $bundle) {
    $request_options = [];
    $request_options[RequestOptions::HEADERS]['Accept'] = 'application/vnd.api+json';
    $url = Url::fromUri('internal:/jsonapi/prison/' . $this->prisonTermMachineName . '/' . $this->getEntityTypeId() . '/' . $bundle);
    $response = $this->request('GET', $url, $request_options);
    $this->assertSame(200, $response->getStatusCode(), $url->toString() . ' returns a 200 response.');
    $response_document = Json::decode((string) $response->getBody());
    $message = 'JSON response returns the correct results for entity type: ' . $this->getEntityTypeId() . ', bundle: ' . $bundle;
    if (empty($entities_to_check)) {
      $this->assertEmpty($response_document['data'], $message);
    }
    else {
      $this->assertSame($entities_to_check, array_map(static function (array $data) {
        return $data['id'];
      }, $response_document['data']), $message);
    }
  }
}
