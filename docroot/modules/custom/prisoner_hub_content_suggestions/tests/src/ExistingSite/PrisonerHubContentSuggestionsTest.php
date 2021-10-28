<?php

namespace Drupal\Tests\prisoner_hub_content_suggestions\ExistingSite;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
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
class PrisonerHubContentSuggestionsTest extends ExistingSiteBase {

  use JsonApiRequestTestTrait;
  use NodeCreationTrait;
  use TaxonomyCreationTrait;

  /**
   * A generated secondary tag term.
   *
   * @var \Drupal\taxonomy\Entity\Term
   */
  protected $secondaryTagTerm;

  /**
   * A generated category term.
   *
   * @var \Drupal\taxonomy\Entity\Term
   */
  protected $categoryTerm;

  /**
   * A generated series term, that is linked to $this->$categoryTerm.
   *
   * @var \Drupal\taxonomy\Entity\Term
   */
  protected $seriesTerm;

  /**
   * Another generated series term.
   *
   * @var \Drupal\taxonomy\Entity\Term
   */
  protected $anotherSeriesTerm;

  /**
   * The uuid of a generated node that references $this->secondaryTagTerm.
   *
   * @var string
   */
  protected $nodeWithTag;

  /**
   * The uuid of a generated node that references $this->seriesTerm.
   *
   * @var string
   */
  protected $nodeWithSeries;

  /**
   * The uuid of a generated node that references $this->categoryTerm.
   *
   * @var string
   */
  protected $nodeWithCategory;

  /**
   * Set up content and taxonomy terms to test with.
   */
  protected function setUp(): void {
    parent::setUp();

    $vocab_secondary_tags = Vocabulary::load('tags');
    $this->secondaryTagTerm = $this->createTerm($vocab_secondary_tags);

    $vocab_categories = Vocabulary::load('moj_categories');
    $this->categoryTerm = $this->createTerm($vocab_categories);

    $vocab_series = Vocabulary::load('series');
    $this->seriesTerm = $this->createTerm($vocab_series, ['field_category' => ['target_id' => $this->categoryTerm->id()]]);
    $this->anotherSeriesTerm = $this->createTerm($vocab_series, ['field_category' => ['target_id' => $this->categoryTerm->id()]]);

    // Create some content for each.
    $this->nodeWithTag = $this->createNode([
      'field_moj_secondary_tags' => $this->secondaryTagTerm->id(),
    ])->uuid();

    $this->nodeWithSeries = $this->createNode([
      'field_moj_series' => $this->seriesTerm->id(),
    ])->uuid();

    $this->nodeWithCategory = $this->createNode([
      'field_moj_top_level_categories' => $this->categoryTerm->id(),
      'field_not_in_series' => TRUE,
    ])->uuid();
  }

  /**
   * Test that content with no tag or category returns an empty result.
   */
  public function testContentWithNoTagOrCategory() {
    $node = $this->createNode([]);
    $this->assertJsonApiSuggestionsResponse([], $node);
  }

  /**
   * Test that content with tag but no category returns content with the same tag.
   */
  public function testContentWithTagButNoCategory() {
    $node = $this->createNode([
      'field_moj_secondary_tags' => [
        ['target_id' => $this->secondaryTagTerm->id()]
      ]
    ]);
    $this->assertJsonApiSuggestionsResponse([$this->nodeWithTag], $node);
  }

  /**
   * Test that content with series but no tag returns content from the same
   * category (but *not* the same series).
   */
  public function testContentWithSeriesButNoTag() {
    $node = $this->createNode([
      'field_moj_series' => [
        ['target_id' => $this->anotherSeriesTerm->id()]
      ]
    ]);
    $this->assertJsonApiSuggestionsResponse([$this->nodeWithSeries], $node);
  }

  /**
   * Test that content with a category but no tag returns content from the same
   * category.
   */
  public function testContentWithCategoryButNoTag() {
    $node = $this->createNode([
      'field_moj_top_level_categories' => [
        ['target_id' => $this->categoryTerm->id()]
      ],
      'field_not_in_series' => TRUE,
    ]);
    $this->assertJsonApiSuggestionsResponse([$this->nodeWithCategory], $node);
  }

  /**
   * Test that content with tag and series returns content with the same tag
   * and the same category as the series (but *not* content in the same series).
   */
  public function testContentWithTagAndSeries() {
    $node = $this->createNode([
      'field_moj_secondary_tags' => [
        ['target_id' => $this->secondaryTagTerm->id()]
      ],
      'field_moj_series' => [
        ['target_id' => $this->anotherSeriesTerm->id()]
      ],
    ]);
    $this->assertJsonApiSuggestionsResponse([$this->nodeWithSeries, $this->nodeWithTag], $node);
  }


  /**
   * Test that content with tag and a category returns content with the same
   * tag and category.
   */
  public function testContentWithTagAndCategory() {
    $node = $this->createNode([
      'field_moj_secondary_tags' => [
        ['target_id' => $this->secondaryTagTerm->id()]
      ],
      'field_moj_top_level_categories' => [
        ['target_id' => $this->categoryTerm->id()]
      ],
      'field_not_in_series' => TRUE,
    ]);
    $this->assertJsonApiSuggestionsResponse([$this->nodeWithCategory, $this->nodeWithTag], $node);
  }

  /**
   * Test that content with tag and a category returns content with the same
   * tag and category.
   */
  public function testContentWithTagAndCategory() {
    $node = $this->createNode([
      'field_moj_secondary_tags' => [
        ['target_id' => $this->secondaryTagTerm->id()]
      ],
      'field_moj_top_level_categories' => [
        ['target_id' => $this->categoryTerm->id()]
      ],
    ]);
    $this->assertJsonApiSuggestionsResponse([$this->nodeWithCategory, $this->nodeWithTag], $node);
  }


  /**
   * Helper function to assert that a jsonapi response returns the expected entities.
   *
   * @param array $entities_to_check
   *   A list of entity uuids to check for in the JSON response.
   * @param NodeInterface $node
   *   The node to check suggestions for.
   */
  protected function assertJsonApiSuggestionsResponse(array $entities_to_check, NodeInterface $node) {
    $url = Url::fromUri('internal:/jsonapi/node/' . $node->getType() . '/' . $node->uuid(). '/suggestions', ['query' => ['page[limit]' => 4]]);
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
