<?php

namespace Drupal\Tests\prisoner_hub_recently_added\ExistingSite;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Url;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\Tests\jsonapi\Functional\JsonApiRequestTestTrait;
use Drupal\Tests\prisoner_hub_test_traits\Traits\JsonApiTrait;
use Drupal\Tests\prisoner_hub_test_traits\Traits\NodeCreationTrait;
use GuzzleHttp\RequestOptions;
use weitzman\DrupalTestTraits\Entity\TaxonomyCreationTrait;
use weitzman\DrupalTestTraits\ExistingSiteBase;

/**
 * Test the recently added JSON:API resource works correctly.
 *
 * @group prisoner_hub_recently_added
 */
class PrisonerHubRecentlyAddedTest extends ExistingSiteBase {

  use JsonApiRequestTestTrait;
  use JsonApiTrait;
  use NodeCreationTrait;
  use TaxonomyCreationTrait;

  /**
   * The jsonapi url to run tests on.
   *
   * @var \Drupal\Core\Url
   */
  protected Url $jsonApiUrl;

  /**
   * An array of uuids in the correct oder.
   *
   * @var array
   */
  protected array $correctOrderUuids;

  /**
   * Set up content and taxonomy terms to test with.
   */
  protected function setUp(): void {
    parent::setUp();

    $this->jsonApiUrl = Url::fromUri('internal:/jsonapi/recently-added', ['query' => ['page[limit]' => 4]]);

    $vocab_series = Vocabulary::load('series');

    $current_time = time();
    $first_entity = $this->createCategorisedNode([
      'published_at' => $current_time,
    ]);

    $third_entity = $this->createTerm($vocab_series);
    $this->createNode([
      'field_moj_series' => [['target_id' => $third_entity->id()]],
      'published_at' => strtotime('-5 seconds', $current_time),
    ]);

    $second_entity = $this->createTerm($vocab_series);
    $this->createNode([
      'field_moj_series' => [['target_id' => $second_entity->id()]],
      'published_at' => strtotime('-1 second', $current_time),
    ]);

    $fourth_entity = $this->createCategorisedNode([
      'published_at' => strtotime('-7 seconds', $current_time),
    ]);

    $this->correctOrderUuids = [
      $first_entity->uuid(),
      $second_entity->uuid(),
      $third_entity->uuid(),
      $fourth_entity->uuid(),
    ];
  }

  /**
   * Test the resource returns the correct entities, in the correct order.
   */
  public function testRecentlyAddedCorrectSorting() {
    // Up to 50% of the response from the end point may come from prioritised
    // content, rather than new content. So all we can test is that two of
    // our test pieces of content are present and in the right order.
    $response = $this->getJsonApiResponse($this->jsonApiUrl);
    $this->assertSame(200, $response->getStatusCode());

    $response_document = Json::decode((string) $response->getBody());
    $this->assertEquals(4, count($response_document['data']));

    $response_ids = array_map(static function (array $data) {
      return $data['id'];
    }, $response_document['data']);

    $this->assertContains($this->correctOrderUuids[0], $response_ids);
    $this->assertContains($this->correctOrderUuids[1], $response_ids);
    $this->assertGreaterThan(array_search($this->correctOrderUuids[0], $response_ids), array_search($this->correctOrderUuids[1], $response_ids));
  }

  /**
   * Test that new content is immediately updated on the resource.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function testRecentlyAddedCacheClear() {
    $request_options = [];
    $request_options[RequestOptions::HEADERS]['Accept'] = 'application/vnd.api+json';
    $this->request('GET', $this->jsonApiUrl, $request_options);

    // Ensure we get a cache HIT on the second time we make a request.
    $response = $this->request('GET', $this->jsonApiUrl, $request_options);
    $this->assertSame($response->getHeader('X-Drupal-Cache')[0], 'HIT');

    $new_entity = $this->createCategorisedNode([
      'published_at' => strtotime('+1 second'),
    ]);

    $response = $this->getJsonApiResponse($this->jsonApiUrl);
    $response_document = Json::decode((string) $response->getBody());
    $response_ids = array_map(static function (array $data) {
      return $data['id'];
    }, $response_document['data']);
    $this->assertContains($new_entity->uuid(), $response_ids);
  }

}
