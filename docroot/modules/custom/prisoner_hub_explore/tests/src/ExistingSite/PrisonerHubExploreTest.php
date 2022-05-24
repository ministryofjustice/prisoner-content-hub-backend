<?php

namespace Drupal\Tests\prisoner_hub_explore\ExistingSite;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Url;
use Drupal\Tests\jsonapi\Functional\JsonApiRequestTestTrait;
use GuzzleHttp\RequestOptions;
use weitzman\DrupalTestTraits\Entity\NodeCreationTrait;
use weitzman\DrupalTestTraits\ExistingSiteBase;

/**
 * Test the the explore JSON:API resource works correctly
 *
 * @group prisoner_hub_explore
 */
class PrisonerHubExploreTest extends ExistingSiteBase {

  use JsonApiRequestTestTrait;
  use NodeCreationTrait;

  /**
   * Test that the /jsonapi/explore resource returns some content.
   */
  public function testExploreContent() {
    $limit = 4;

    // Ensure there is some content to test with.
    // Note this test could be run on a site with existing content.
    // Because of that, and that the order is random, we only check for the
    // quantity of results, not that specific IDs are returned.
    for ($i = 1; $i <= $limit; $i++) {
      $this->createNode();
    }
    $url = Url::fromUri('internal:/jsonapi/node/explore', ['query' => ['page[limit]' => $limit]]);
    $response = $this->getJsonApiResponse($url);
    $this->assertSame(200, $response->getStatusCode(), $url->toString() . ' returns a 200 response.');
    $response_document = Json::decode((string) $response->getBody());
    $message = 'JSON response returns the correct results on url: ' . $url->toString();
    $this->assertSame(count($response_document['data']), $limit, $message);

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
