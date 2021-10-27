<?php

namespace Drupal\Tests\prisoner_hub_series_access\ExistingSite;

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
 * @group prisoner_hub_series_access
 */
class PrisonerHubSeriesAccessTest extends ExistingSiteBase {

  use JsonApiRequestTestTrait;
  use NodeCreationTrait;
  use TaxonomyCreationTrait;

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
    $vocab_series = Vocabulary::load('series');
    $this->seriesTerm = $this->createTerm($vocab_series);
  }

  /**
   * Test a series with no available content is a 403.
   */
  public function testSeriesWithNoAvailableContent() {
    $this->createNode([
      'field_moj_series' => [
        ['target_id' => $this->seriesTerm->id()]
      ],
      'status' => 0,
    ]);
    $url = Url::fromUri('internal:/jsonapi/taxonomy_term/' . $this->seriesTerm->bundle() . '/' . $this->seriesTerm->uuid());
    $response = $this->getJsonApiResponse($url);
    $this->assertSame(403, $response->getStatusCode(), $url->toString() . ' returns a 403 response.');
  }

  /**
   * Test a series with at least 1 available content is a 200.
   */
  public function testSeriesWithAvailableContent() {
    $this->createNode([
      'field_moj_series' => [
        ['target_id' => $this->seriesTerm->id()]
      ],
    ]);
    $url = Url::fromUri('internal:/jsonapi/taxonomy_term/' . $this->seriesTerm->bundle() . '/' . $this->seriesTerm->uuid());
    $response = $this->getJsonApiResponse($url);
    $this->assertSame(200, $response->getStatusCode(), $url->toString() . ' returns a 200 response.');
  }
}
