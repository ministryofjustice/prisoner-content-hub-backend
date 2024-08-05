<?php

namespace Drupal\Tests\prisoner_hub_content_suggestions\ExistingSite;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\Tests\jsonapi\Functional\JsonApiRequestTestTrait;
use Drupal\Tests\prisoner_hub_test_traits\Traits\JsonApiTrait;
use Drupal\user\Entity\Role;
use Drupal\user\RoleInterface;
use weitzman\DrupalTestTraits\Entity\NodeCreationTrait;
use weitzman\DrupalTestTraits\Entity\TaxonomyCreationTrait;
use weitzman\DrupalTestTraits\ExistingSiteBase;

/**
 * Test the content suggestions JSON:API resource works correctly.
 *
 * @group prisoner_hub_content_suggestions
 */
class PrisonerHubContentSuggestionsTest extends ExistingSiteBase {

  use JsonApiRequestTestTrait;
  use JsonApiTrait;
  use NodeCreationTrait;
  use TaxonomyCreationTrait;

  /**
   * A generated topics term.
   *
   * @var \Drupal\taxonomy\Entity\Term
   */
  protected $topicsTerm;

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
   * The uuid of a generated node that references $this->topicsTerm.
   *
   * @var string
   */
  protected $nodeWithTopic;

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

    $vocab_topics = Vocabulary::load('topics');
    $this->topicsTerm = $this->createTerm($vocab_topics);

    $vocab_categories = Vocabulary::load('moj_categories');
    $this->categoryTerm = $this->createTerm($vocab_categories);

    $vocab_series = Vocabulary::load('series');
    $this->seriesTerm = $this->createTerm($vocab_series, ['field_category' => ['target_id' => $this->categoryTerm->id()]]);
    $this->anotherSeriesTerm = $this->createTerm($vocab_series, ['field_category' => ['target_id' => $this->categoryTerm->id()]]);

    // Create some content for each.
    $this->nodeWithTopic = $this->createNode([
      'field_topics' => $this->topicsTerm->id(),
    ])->uuid();

    $this->nodeWithSeries = $this->createNode([
      'field_moj_series' => $this->seriesTerm->id(),
    ])->uuid();

    $this->nodeWithCategory = $this->createNode([
      'field_moj_top_level_categories' => $this->categoryTerm->id(),
      'field_not_in_series' => TRUE,
    ])->uuid();

    // Allow anonymous user to access entities without prison context.
    // As we're not testing the prison context part, this is unnecessary.
    // @todo Remove this when tests are refactored, and a single way of
    // creating entities (that includes adding relevant prisons) is used across
    // our tests.
    $role = Role::load(RoleInterface::ANONYMOUS_ID);
    $this->grantPermissions($role, ['view entity without prison context']);
  }

  /**
   * Test that content with no topic or category returns an empty result.
   */
  public function testContentWithNoTagOrCategory() {
    $node = $this->createNode();
    $this->assertJsonApiSuggestionsResponse([], $node);
  }

  /**
   * Test content with topic but no category returns content with that topic.
   */
  public function testContentWithTopicButNoCategory() {
    $node = $this->createNode([
      'field_topics' => [
        ['target_id' => $this->topicsTerm->id()],
      ],
    ]);
    $this->assertJsonApiSuggestionsResponse([$this->nodeWithTopic], $node);
  }

  /**
   * Test content with series but no topic returns content from that category.
   *
   * But *not* the same series.
   */
  public function testContentWithSeriesButNoTag() {
    $node = $this->createNode([
      'field_moj_series' => [
        ['target_id' => $this->anotherSeriesTerm->id()],
      ],
    ]);
    $this->assertJsonApiSuggestionsResponse([$this->nodeWithSeries], $node);
  }

  /**
   * Test content with category but no topic returns content from that category.
   */
  public function testContentWithCategoryButNoTag() {
    $node = $this->createNode([
      'field_moj_top_level_categories' => [
        ['target_id' => $this->categoryTerm->id()],
      ],
      'field_not_in_series' => TRUE,
    ]);
    $this->assertJsonApiSuggestionsResponse([$this->nodeWithCategory], $node);
  }

  /**
   * Test content with topic and series returns appropriate content.
   *
   * This content should have the same topic and the same category
   * as the series (but *not* content in the same series).
   */
  public function testContentWithTagAndSeries() {
    $node = $this->createNode([
      'field_topics' => [
        ['target_id' => $this->topicsTerm->id()],
      ],
      'field_moj_series' => [
        ['target_id' => $this->anotherSeriesTerm->id()],
      ],
    ]);
    $this->assertJsonApiSuggestionsResponse([
      $this->nodeWithSeries,
      $this->nodeWithTopic,
    ], $node);
  }

  /**
   * Test content with topic and category returns content with the same.
   */
  public function testContentWithTagAndCategory() {
    $node = $this->createNode([
      'field_topics' => [
        ['target_id' => $this->topicsTerm->id()],
      ],
      'field_moj_top_level_categories' => [
        ['target_id' => $this->categoryTerm->id()],
      ],
      'field_not_in_series' => TRUE,
    ]);
    $this->assertJsonApiSuggestionsResponse([
      $this->nodeWithCategory,
      $this->nodeWithTopic,
    ], $node);
  }

  /**
   * Helper function to assert a jsonapi response returns the expected entities.
   *
   * @param array $entities_to_check
   *   A list of entity uuids to check for in the JSON response.
   * @param \Drupal\node\NodeInterface $node
   *   The node to check suggestions for.
   */
  protected function assertJsonApiSuggestionsResponse(array $entities_to_check, NodeInterface $node) {
    $url = Url::fromUri('internal:/jsonapi/node/' . $node->getType() . '/' . $node->uuid() . '/suggestions', ['query' => ['page[limit]' => 4]]);
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
