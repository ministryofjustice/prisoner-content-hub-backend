<?php

namespace Drupal\Tests\prisoner_hub_recently_added\ExistingSite;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Url;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\Tests\jsonapi\Functional\JsonApiRequestTestTrait;
use GuzzleHttp\RequestOptions;
use weitzman\DrupalTestTraits\Entity\NodeCreationTrait;
use weitzman\DrupalTestTraits\Entity\TaxonomyCreationTrait;
use weitzman\DrupalTestTraits\ExistingSiteBase;

/**
 * Test the the recently added JSON:API resource works correctly
 *
 * @group prisoner_hub_recently_added
 */
class PrisonerHubRecentlyAddedTest extends ExistingSiteBase {

  use JsonApiRequestTestTrait;
  use NodeCreationTrait;
  use TaxonomyCreationTrait;

  /**
   * The jsonapi url to run tests on.
   *
   * @var \Drupal\Core\Url
   */
  protected $jsonApiUrl;

  /**
   * An array of uuids in the correct oder.
   *
   * @var array
   */
  protected $correctOrderUuids;

  /**
   * Set up content and taxonomy terms to test with.
   */
  protected function setUp(): void {
    parent::setUp();

    $this->jsonApiUrl = Url::fromUri('internal:/jsonapi/recently-added', ['query' => ['page[limit]' => 4]]);

    $vocab_series = Vocabulary::load('series');

    $current_time = time();
    $first_entity = $this->createNode([
      'field_not_in_series' => 1,
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

    $fourth_entity = $this->createNode([
      'field_not_in_series' => 1,
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
    $this->assertJsonResponse($this->correctOrderUuids);
  }

  /**
   * Test that new content is immediately updated on the resource.
   */
  public function testRecentlyAddedCacheClear() {
    $request_options = [];
    $request_options[RequestOptions::HEADERS]['Accept'] = 'application/vnd.api+json';
    $this->request('GET', $this->jsonApiUrl, $request_options);

    // Ensure we get a cache HIT on the second time we make a request.
    $response = $this->request('GET', $this->jsonApiUrl, $request_options);
    $this->assertSame($response->getHeader('X-Drupal-Cache')[0], 'HIT');
    print_r($response->getHeaders());
    $this->assertStringContainsString('prisoner_hub_recently_added', $response->getHeader('X-Drupal-Cache-Tags')[0]);
    $new_entity = $this->createNode([
      'field_not_in_series' => 1,
      'published_at' => strtotime('+1 second'),
    ]);
    $this->correctOrderUuids = array_merge([$new_entity->uuid()], array_slice($this->correctOrderUuids, 0, 3));
    $this->assertJsonResponse($this->correctOrderUuids);
  }

  /**
   * Asserts the jsonapi response.
   *
   * @param array $correct_order_uuids
   *   An array of uuids, in the correct order, to check for.
   */
  protected function assertJsonResponse(array $correct_order_uuids) {
    $request_options = [];
    $request_options[RequestOptions::HEADERS]['Accept'] = 'application/vnd.api+json';
    $response = $this->request('GET', $this->jsonApiUrl, $request_options);
    $this->assertSame(200, $response->getStatusCode());
    $response_document = Json::decode((string) $response->getBody());
    foreach ($response_document['data'] as $item) {
      $this->assertEquals($correct_order_uuids, array_map(static function (array $data) {
        return $data['id'];
      }, $response_document['data']));
    }
  }
}
