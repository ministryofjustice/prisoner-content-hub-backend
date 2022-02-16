<?php

namespace Drupal\Tests\prisoner_hub_taxonomy_access\ExistingSite;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\taxonomy\TermInterface;
use Drupal\Tests\jsonapi\Functional\JsonApiRequestTestTrait;
use GuzzleHttp\RequestOptions;
use weitzman\DrupalTestTraits\Entity\NodeCreationTrait;
use weitzman\DrupalTestTraits\Entity\TaxonomyCreationTrait;
use weitzman\DrupalTestTraits\ExistingSiteBase;

/**
 * Test that series are accessible/inaccessible.
 *
 * @group prisoner_hub_taxonomy_access
 */
class PrisonerHubTaxonomyAccessTest extends ExistingSiteBase {

  use JsonApiRequestTestTrait;
  use NodeCreationTrait;
  use TaxonomyCreationTrait;

  /**
   * Test a series with no available content is a 403.
   */
  public function testSeriesWithNoAvailableContent() {
    $vocab_series = Vocabulary::load('series');
    $series = $this->createTerm($vocab_series);
    $this->createNode([
      'field_moj_series' => [
        ['target_id' => $series->id()]
      ],
      'status' => NodeInterface::NOT_PUBLISHED,
    ]);
    $url = Url::fromUri('internal:/jsonapi/taxonomy_term/' . $series->bundle() . '/' . $series->uuid());
    $response = $this->getJsonApiResponse($url);
    $this->assertSame(403, $response->getStatusCode(), $url->toString() . ' returns a 403 response.');
  }

  /**
   * Test a series with at least 1 available content is a 200.
   */
  public function testSeriesWithAvailableContent() {
    $vocab_series = Vocabulary::load('series');
    $series = $this->createTerm($vocab_series);
    $this->createNode([
      'field_moj_series' => [
        ['target_id' => $series->id()]
      ],
      'status' => NodeInterface::PUBLISHED,
    ]);
    $url = Url::fromUri('internal:/jsonapi/taxonomy_term/' . $series->bundle() . '/' . $series->uuid());
    $response = $this->getJsonApiResponse($url);
    $this->assertSame(200, $response->getStatusCode(), $url->toString() . ' returns a 200 response.');
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
