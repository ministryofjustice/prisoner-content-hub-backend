<?php

namespace Drupal\Tests\prisoner_hub_breadcrumbs\ExistingSite;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\Tests\jsonapi\Functional\JsonApiRequestTestTrait;
use GuzzleHttp\RequestOptions;
use weitzman\DrupalTestTraits\Entity\NodeCreationTrait;
use weitzman\DrupalTestTraits\Entity\TaxonomyCreationTrait;
use weitzman\DrupalTestTraits\ExistingSiteBase;

/**
 * Test the the breadcrumb suggestions JSON:API resource works correctly
 *
 * @group prisoner_hub_breadcrumbs
 */
class PrisonerHubBreadcrumbsTest extends ExistingSiteBase {

  use JsonApiRequestTestTrait;
  use NodeCreationTrait;
  use TaxonomyCreationTrait;

  /**
   * An array of category taxonomy term entities.
   *
   * @var array
   */
  protected $categoryTerms;

  /**
   * A generated series term, that is linked to $this->$categoryTerm.
   *
   * @var \Drupal\taxonomy\Entity\Term
   */
  protected $seriesTerm;

  /**
   * Set up content and taxonomy terms to test with.
   */
  protected function setUp(): void {
    parent::setUp();

    $vocab_secondary_tags = Vocabulary::load('tags');
    $this->secondaryTagTerm = $this->createTerm($vocab_secondary_tags);

    $vocab_categories = Vocabulary::load('moj_categories');
    $parentTerm = $this->createTerm($vocab_categories);
    $subCategoryTerm = $this->createTerm($vocab_categories, [
      'parent' => [
        ['target_id' => $parentTerm->id()],
      ],
    ]);
    $subSubCategoryTerm = $this->createTerm($vocab_categories, [
      'parent' => [
        ['target_id' => $subCategoryTerm->id(),],
      ],
    ]);
    $this->createNode([
      'field_moj_top_level_categories' => [
        ['target_id' => $subSubCategoryTerm->id()]
      ],
      'field_not_in_series' => 1,
    ]);

    $this->categoryTerms = [$parentTerm, $subCategoryTerm, $subSubCategoryTerm];

    $vocab_series = Vocabulary::load('series');
    $this->seriesTerm = $this->createTerm($vocab_series, [
      'field_category' =>
        ['target_id' => $subSubCategoryTerm->id()],
    ]);
    $this->createNode([
      'field_moj_series' => [
        ['target_id' => $this->seriesTerm ->id()]
      ],
      'field_not_in_series' => 0,
    ]);
  }

  /**
   * Test that a sub category has the correct breadrumbs.
   */
  public function testSubCategoryBreadcrumb() {
    // Use array_pop to remove the last category from the list (as this won't
    // appear in the breadcrumbs).
    $category = array_pop($this->categoryTerms);
    $this->assertJsonApiBreadcrumbResponse($category, $this->categoryTerms);
  }

  /**
   * Test that a series has the correct breadcrumbs.
   */
  public function testSeriesBreadcrumb() {
    // The series has been assigned to the sub-sub-category.
    // So all categories should appear in the breadcrumbs.
    $this->assertJsonApiBreadcrumbResponse($this->seriesTerm, $this->categoryTerms);
  }

  /**
   * Test content assigned to a category has the correct breadcrumbs.
   */
  public function testContentWithCategoryBreadcumb() {
    $categories = $this->categoryTerms;
    $category = end($categories);
    $node = $this->createNode([
      'field_moj_top_level_categories' => [
        ['target_id' => $category->id()],
      ],
      'field_not_in_series' => 1,
    ]);
    $this->assertJsonApiBreadcrumbResponse($node, $this->categoryTerms);
  }

  /**
   * Test content assigned to a series has the corret breadcrumbs.
   */
  public function testContentWithSeriesBreadcumb() {
    $node = $this->createNode([
      'field_moj_series' => [
        ['target_id' => $this->seriesTerm->id()],
      ],
      'field_not_in_series' => 0,
    ]);
    $breadcrumbs = $this->categoryTerms;
    $breadcrumbs[] = $this->seriesTerm;
    $this->assertJsonApiBreadcrumbResponse($node, $breadcrumbs);
  }

  /**
   * Helper function to assert that a jsonapi response returns the expected
   * entities.
   *
   * @param array $entities_to_check
   *   A list of entity uuids to check for in the JSON response.
   * @param NodeInterface $node
   *   The node to check suggestions for.
   */
  protected function assertJsonApiBreadcrumbResponse(ContentEntityInterface $entity, array $breadcrumb_entities) {
    $url = Url::fromUri('internal:/jsonapi/' . $entity->getEntityTypeId() . '/' . $entity->bundle() . '/' . $entity->uuid());
    $response = $this->getJsonApiResponse($url);
    $this->assertSame(200, $response->getStatusCode(), $url->toString() . ' returns a 200 response.');
    $response_document = Json::decode((string) $response->getBody());
    $message = 'JSON response returns the correct results on url: ' . $url->toString();

    if (empty($breadcrumb_entities)) {
      $this->assertEmpty($response_document['data']['attributes']['breadcrumbs'], $message);
    }
    else {
      $breadcrumbs = [
        [
          'uri' => Url::fromRoute('<front>')->toString(),
          'title' => 'Home',
          'options' => [],
        ],
      ];
      /** @var ContentEntityInterface $breadcrumb_entity */
      foreach ($breadcrumb_entities as $breadcrumb_entity) {
        $breadcrumbs[] = [
          'uri' => $breadcrumb_entity->toUrl()->toString(),
          'title' => $breadcrumb_entity->label(),
          'options' => [],
        ];
      }
      $this->assertEqualsCanonicalizing($breadcrumbs, $response_document['data']['attributes']['breadcrumbs'], $message);
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