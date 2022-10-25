<?php

namespace Drupal\Tests\prisoner_hub_taxonomy_sorting\ExistingSite;

use DateTime;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Url;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\Tests\jsonapi\Functional\JsonApiRequestTestTrait;
use Drupal\Tests\node\Traits\NodeCreationTrait;
use GuzzleHttp\RequestOptions;
use weitzman\DrupalTestTraits\Entity\TaxonomyCreationTrait;
use weitzman\DrupalTestTraits\ExistingSiteBase;

/**
 * Base class that tests jsonapi responses for the correct results.
 *
 * @group prisoner_hub_taxonomy_sorting
 */
class SeriesSortByTest extends ExistingSiteBase {

  use JsonApiRequestTestTrait;
  use NodeCreationTrait;
  use TaxonomyCreationTrait;

  /**
   * @var \Drupal\taxonomy\Entity\Term
   */
  protected $seriesTerm;

  public function setUp() : void {
    parent::setUp();
    // Create a series
    $vocab = Vocabulary::load('series');
    $this->seriesTerm = $this->createTerm($vocab);

    $jsonapi_query = [
      'filter[field_moj_series.meta.drupal_internal__tid]' => $this->seriesTerm->id(),
      'sort' => 'series_sort_value',
    ];
    $this->jsonApiUrl = Url::fromUri('internal:/jsonapi/node/page', ['query' => $jsonapi_query]);

    // Create content.
    $node_values = [
      [
        'field_release_date' => '2018-06-10',
        'field_moj_season' => 1,
        'field_moj_episode' => 2,
        'field_moj_series' => [['target_id' => $this->seriesTerm->id()]]
      ],
      [
        'field_release_date' => '2018-09-10',
        'field_moj_season' => 1,
        'field_moj_episode' => 5,
        'field_moj_series' => [['target_id' => $this->seriesTerm->id()]]
      ],
      [
        'field_release_date' => '2019-01-02',
        'field_moj_season' => 4,
        'field_moj_episode' => 12,
        'field_moj_series' => [['target_id' => $this->seriesTerm->id()]]
      ],
      [
        'field_release_date' => '2020-02-12',
        'field_moj_season' => 11,
        'field_moj_episode' => 22,
        'field_moj_series' => [['target_id' => $this->seriesTerm->id()]]
      ],
      [
        'field_release_date' => '2029-06-10',
        'field_moj_season' => 40,
        'field_moj_episode' => 202,
        'field_moj_series' => [['target_id' => $this->seriesTerm->id()]]
      ],
    ];
    foreach ($node_values as $node_value) {
      $this->createNode($node_value);
    }
  }

  /**
   * Test that correct sorting is applied for series sorted by "Episode number (oldest first)".
   */
  public function testSortBySeasonAndEpisodeAsc() {
    $this->seriesTerm->set('field_sort_by', 'season_and_episode_asc');
    $this->seriesTerm->save();
    $request_options = [];
    $request_options[RequestOptions::HEADERS]['Accept'] = 'application/vnd.api+json';
    $response = $this->request('GET', $this->jsonApiUrl, $request_options);
    $this->assertSame(200, $response->getStatusCode());
    $response_document = Json::decode((string) $response->getBody());

    $season_number = 0;
    $episode_number = 0;
    foreach ($response_document['data'] as $item) {
      $this->assertGreaterThanOrEqual($season_number, $item['attributes']['field_moj_season']);
      $this->assertGreaterThanOrEqual($episode_number, $item['attributes']['field_moj_episode']);
      $season_number = $item['attributes']['field_moj_season'];
      $episode_number = $item['attributes']['field_moj_episode'];
    }
  }

  /**
   * Test that correct sorting is applied for series sorted by "Episode number (newest first)".
   */
  public function testSortBySeasonAndEpisodeDesc() {
    $this->seriesTerm->set('field_sort_by', 'season_and_episode_desc');
    $this->seriesTerm->save();
    $request_options = [];
    $request_options[RequestOptions::HEADERS]['Accept'] = 'application/vnd.api+json';
    $response = $this->request('GET', $this->jsonApiUrl, $request_options);
    $this->assertSame(200, $response->getStatusCode());
    $response_document = Json::decode((string) $response->getBody());

    $season_number = 999;
    $episode_number = 999;
    foreach ($response_document['data'] as $item) {
      $this->assertLessThanOrEqual($season_number, $item['attributes']['field_moj_season']);
      $this->assertLessThanOrEqual($episode_number, $item['attributes']['field_moj_episode']);
      $season_number = $item['attributes']['field_moj_season'];
      $episode_number = $item['attributes']['field_moj_episode'];
    }
  }

  /**
   * Test that correct sorting is applied for series sorted by "Release date (oldest first)".
   */
  public function testSortByReleaseDateAsc() {
    $this->seriesTerm->set('field_sort_by', 'release_date_asc');
    $this->seriesTerm->save();
    $request_options = [];
    $request_options[RequestOptions::HEADERS]['Accept'] = 'application/vnd.api+json';
    $response = $this->request('GET', $this->jsonApiUrl, $request_options);
    $this->assertSame(200, $response->getStatusCode());
    $response_document = Json::decode((string) $response->getBody());

    foreach ($response_document['data'] as $item) {
      $current_release_date = DateTime::createFromFormat('Y-m-d', $item['attributes']['field_release_date']);
      if (isset($previous_release_date)) {
        $this->assertGreaterThanOrEqual($previous_release_date, $current_release_date);
      }
      $previous_release_date = $current_release_date;
    }
  }

  /**
   * Test that correct sorting is applied for series sorted by "Release date (newest first)".
   */
  public function testSortByReleaseDateDesc() {
    $this->seriesTerm->set('field_sort_by', 'release_date_desc');
    $this->seriesTerm->save();
    $request_options = [];
    $request_options[RequestOptions::HEADERS]['Accept'] = 'application/vnd.api+json';
    $response = $this->request('GET', $this->jsonApiUrl, $request_options);
    $this->assertSame(200, $response->getStatusCode());
    $response_document = Json::decode((string) $response->getBody());

    foreach ($response_document['data'] as $item) {
      $current_release_date = DateTime::createFromFormat('Y-m-d', $item['attributes']['field_release_date']);
      if (isset($previous_release_date)) {
        $this->assertLessThanOrEqual($previous_release_date, $current_release_date);
      }
      $previous_release_date = $current_release_date;
    }
  }
}
