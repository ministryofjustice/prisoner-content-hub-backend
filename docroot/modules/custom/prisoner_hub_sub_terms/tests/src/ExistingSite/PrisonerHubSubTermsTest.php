<?php

namespace Drupal\Tests\prisoner_hub_sub_terms\ExistingSite;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Url;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\Tests\jsonapi\Functional\JsonApiRequestTestTrait;
use GuzzleHttp\RequestOptions;
use weitzman\DrupalTestTraits\Entity\NodeCreationTrait;
use weitzman\DrupalTestTraits\Entity\TaxonomyCreationTrait;
use weitzman\DrupalTestTraits\ExistingSiteBase;

/**
 * Test the the content suggestions JSON:API resource works correctly
 *
 * @group prisoner_hub_content_suggestions
 */
class PrisonerHubSubTermsTest extends ExistingSiteBase {

  use JsonApiRequestTestTrait;
  use NodeCreationTrait;
  use TaxonomyCreationTrait;

  /**
   * A generated category term.
   *
   * @var \Drupal\taxonomy\Entity\Term
   */
  protected $categoryTerm;

  /**
   * A generated category term.
   *
   * @var \Drupal\taxonomy\Entity\Term
   */
  protected $subCategoryTerm;

  /**
   * A generated series term, that is linked to $this->$categoryTerm.
   *
   * @var \Drupal\taxonomy\Entity\Term
   */
  protected $seriesTerm;

  /**
   * The JSON:API url.
   *
   * @var \Drupal\Core\Url
   */
  protected $jsonApiUrl;

  /**
   * Set up taxonomy terms to test with.
   */
  protected function setUp(): void {
    parent::setUp();

    $vocab_categories = Vocabulary::load('moj_categories');
    $this->categoryTerm = $this->createTerm($vocab_categories);
    $this->jsonApiUrl = Url::fromUri('internal:/jsonapi/taxonomy_term/moj_categories/' . $this->categoryTerm->uuid() . '/sub_terms');
    $this->subCategoryTerm = $this->createTerm($vocab_categories, [
      'parent' => [
        'target_id' => $this->categoryTerm->id(),
      ],
    ]);

    $vocab_series = Vocabulary::load('series');
    $this->seriesTerm = $this->createTerm($vocab_series, ['field_category' => ['target_id' => $this->categoryTerm->id()]]);
  }

  /**
   * Test the resource returns the correct taxonomy terms, in the correct order.
   *
   * @Todo split this out into several more discrete tests.
   */
  public function testSubTermsHaveCorrectSorting() {
    $vocab_categories = Vocabulary::load('moj_categories');
    $vocab_series = Vocabulary::load('series');

    // Create a subcategory with content inside it, and check that the
    // subcategory is shown.
    $first_term = $this->createTerm($vocab_categories, [
      'parent' => [
        'target_id' => $this->categoryTerm->id(),
      ],
    ]);
    $this->createNode([
      'field_moj_top_level_categories' => [['target_id' => $first_term->id()]],
      'field_not_in_series' => 1,
      'changed' => time(),
    ]);

    // Create another subcategory, and create content that is _very old_,
    // ensure this is displayed last.
    $last_term = $this->createTerm($vocab_categories, [
      'parent' => [
        'target_id' => $this->categoryTerm->id(),
      ],
    ]);
    $this->createNode([
      'field_moj_top_level_categories' => [['target_id' => $last_term->id()]],
      'field_not_in_series' => 1,
      'changed' => strtotime('-2 years'),
    ]);

    // Create some unpublished content to ensure this doesn't effect
    // sorting.
    $this->createNode([
      'field_moj_top_level_categories' => [['target_id' => $last_term->id()]],
      'field_not_in_series' => 1,
      'changed' => time(),
      'status' => 0,
    ]);

    // Create another sub-category with a series inside it.
    // And check for the sub-category appearing (not the series).
    $second_term = $this->createTerm($vocab_categories, [
      'parent' => [
        'target_id' => $this->categoryTerm->id(),
      ],
    ]);
    $second_series = $this->createTerm($vocab_series, [
      'field_category' => [
        'target_id' => $second_term->id(),
      ]
    ]);
    $this->createNode([
      'field_moj_series' => [['target_id' => $second_series->id()]],
      'changed' => strtotime('-10 minutes'),
    ]);

    // Create a subcategory with some content, and ensure it's displayed
    // in the correct position.
    $third_term = $this->createTerm($vocab_categories, [
      'parent' => [
        'target_id' => $this->categoryTerm->id(),
      ],
    ]);
    $this->createNode([
      'field_moj_top_level_categories' => [['target_id' => $third_term->id()]],
      'field_not_in_series' => 1,
      'changed' => strtotime('-1 week'),
    ]);

    // Create a series inside the current category, with some content in it.
    // Ensure it is displayed in the correct position.
    $fourth_term = $this->createTerm($vocab_series, [
      'field_category' => [
        'target_id' => $this->categoryTerm->id()
      ]
    ]);
    $this->createNode([
      'field_moj_series' => [['target_id' => $fourth_term->id()]],
      'changed' => strtotime('-6 months'),
    ]);

    // Create a three new sub-categories (going three levels down), and ensure
    // the highest level sub-category is shown.
    $fifth_term = $this->createTerm($vocab_categories, [
      'parent' => [
        'target_id' => $this->categoryTerm->id(),
      ],
    ]);
    $fifth_subcategory = $this->createTerm($vocab_categories, [
      'parent' => [
        'target_id' => $fifth_term->id()
      ]
    ]);
    $fifth_subsubcategory = $this->createTerm($vocab_categories, [
      'parent' => [
        'target_id' => $fifth_subcategory->id()
      ]
    ]);
    $this->createNode([
      'field_moj_top_level_categories' => [['target_id' => $fifth_subsubcategory->id()]],
      'field_not_in_series' => 1,
      'changed' => strtotime('-7 months'),
    ]);

    $correct_order_sub_terms = [
      $first_term->uuid(),
      $second_term->uuid(),
      $third_term->uuid(),
      $fourth_term->uuid(),
      $fifth_term->uuid(),
      $last_term->uuid(),
    ];

    // Create some other categories and series that should not appear in the
    // results.
    $another_category = $this->createTerm($vocab_categories);
    $this->createNode([
      'field_moj_top_level_categories' => [['target_id' => $another_category->id()]],
      'field_not_in_series' => 1,
    ]);

    $another_sub_category = $this->createTerm($vocab_categories, [
      'parent' => [
        'target_id' => $another_category->id(),
      ],
    ]);
    $this->createNode([
      'field_moj_top_level_categories' => [['target_id' => $another_sub_category->id()]],
      'field_not_in_series' => 1,
    ]);

    $another_series = $this->createTerm($vocab_series, [
      'field_category' => [
        'target_id' => $another_category->id(),
      ],
    ]);
    $this->createNode([
      'field_moj_series' => [['target_id' => $another_series->id()]],
    ]);

    // Also create content on the main category itself, to ensure that
    // this also isn't returned (we should only receive sub-terms).
    $this->createNode([
      'field_moj_top_level_categories' => [['target_id' => $this->categoryTerm->id()]],
      'field_not_in_series' => 1,
    ]);

    $response = $this->getJsonApiResponse($this->jsonApiUrl);
    $this->assertSame(200, $response->getStatusCode());
    $response_document = Json::decode((string) $response->getBody());
    foreach ($response_document['data'] as $item) {
      $this->assertEquals($correct_order_sub_terms, array_map(static function (array $data) {
        return $data['id'];
      }, $response_document['data']));
    }
  }

  /**
   * Test that the correct cache tags are invalidated.
   */
  public function testCacheTagInvalidation() {
    // Create some content in the new subCategory, and ensure we get a cache HIT.
    $this->createNode([
      'field_moj_top_level_categories' => [['target_id' => $this->subCategoryTerm->id()]],
      'field_not_in_series' => 1,
    ]);
    // Run the request twice, so the first one generates a cache.
    $this->getJsonApiResponse($this->jsonApiUrl);
    $response = $this->getJsonApiResponse($this->jsonApiUrl);
    $this->assertSame($response->getHeader('X-Drupal-Cache')[0], 'HIT');

    // We should have one cachetag invalidation, as we created one peice of content.
    $cachetag = 'prisoner_hub_sub_terms:' . $this->categoryTerm->id();
    $invalidation_count = \Drupal::service('cache_tags.invalidator.checksum')->getCurrentChecksum([$cachetag]);
    $this->assertSame(1, $invalidation_count, 'Cachetag has been cleared exactly one time.');

    // Create a new node, and check for a MISS.
    $this->createNode([
      'field_moj_top_level_categories' => [['target_id' => $this->subCategoryTerm->id()]],
      'field_not_in_series' => 1,
    ]);
    $response = $this->getJsonApiResponse($this->jsonApiUrl);
    $this->assertSame($response->getHeader('X-Drupal-Cache')[0], 'MISS');
    $invalidation_count = \Drupal::service('cache_tags.invalidator.checksum')->getCurrentChecksum([$cachetag]);
    $this->assertSame(2, $invalidation_count, 'Cachetag has been cleared exactly two times.');
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
