<?php

namespace Drupal\Tests\jsonapi_filter_cache_tags\ExistingSite;

use Drupal\Core\Url;
use Drupal\Tests\field\Traits\EntityReferenceTestTrait;
use Drupal\Tests\jsonapi\Functional\JsonApiRequestTestTrait;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;
use GuzzleHttp\RequestOptions;
use weitzman\DrupalTestTraits\Entity\NodeCreationTrait;
use weitzman\DrupalTestTraits\Entity\TaxonomyCreationTrait;
use weitzman\DrupalTestTraits\ExistingSiteBase;

/**
 * Test the jsonapi_filter_cache_tags module works as expected.
 */
class JsonApiFilterCacheTagsTest extends ExistingSiteBase {

  use JsonApiRequestTestTrait;
  use NodeCreationTrait;
  use ContentTypeCreationTrait;
  use TaxonomyCreationTrait;
  use EntityReferenceTestTrait;

  /**
   * @var \Drupal\taxonomy\Entity\Term
   */
  protected $termA;

  /**
   * @var \Drupal\taxonomy\Entity\Term
   */
  protected $termB;

  /**
   * @var \Drupal\node\NodeInterface
   */
  protected $nodeA;

  /**
   * @var \Drupal\node\NodeInterface
   */
  protected $nodeB;

  /**
   * @var \Drupal\Core\Url
   */
  protected $jsonapiUrlTermA;

  /**
   * @var \Drupal\Core\Url
   */
  protected $jsonapiUrlTermB;

  /**
   * @var string
   */
  protected $entityReferenceFieldName;

  /**
   * Set up content and taxonomy to test with, and warm caches.
   */
  protected function setUp(): void {
    parent::setUp();
    $contentType = $this->createContentType();
    $contentType->save();

    // Flush caches so routes are regenerated with newly created content type.
    drupal_flush_all_caches();
    $this->entityReferenceFieldName = 'field_reference_' . mb_strtolower($this->randomMachineName());
    $this->createEntityReferenceField('node', $contentType->id(), $this->entityReferenceFieldName, 'Field reference test', 'taxonomy_term');
    $vocab = $this->createVocabulary();
    $this->termA = $this->createTerm($vocab);
    $this->termB = $this->createTerm($vocab);
    $this->nodeA = $this->createNode([
      'type' => $contentType->id(),
      $this->entityReferenceFieldName => [
        ['target_id' => $this->termA->id()],
      ],
      'status' => 1,
    ]);
    $this->nodeB = $this->createNode([
      'type' => $contentType->id(),
      $this->entityReferenceFieldName => [
        ['target_id' => $this->termB->id()],
      ],
      'status' => 1,
    ]);
    $this->jsonapiUrlTermA = Url::fromUri('internal:/jsonapi/node/' . $contentType->id(), ['query' => ['filter[' . $this->entityReferenceFieldName . '.id]' => $this->termA->uuid()]]);
    // Run the request so that a cache entry is created.
    $this->getJsonApiResponse($this->jsonapiUrlTermA);

    $this->jsonapiUrlTermB = Url::fromUri('internal:/jsonapi/node/'  . $contentType->id(), ['query' => ['filter[' . $this->entityReferenceFieldName . '.id]' => $this->termB->uuid()]]);
    // Run the request so that a cache entry is created.
    $this->getJsonApiResponse($this->jsonapiUrlTermB);
  }

  /**
   * Test that the correct cache tags are invalidated when content is updated.
   */
  public function testCacheTagCheckSums() {
    // Now update one node.
    $this->nodeA->set('title', 'Changed');
    $this->nodeA->save();

    // We should have one cache tag invalidation, as we updated one piece of content.
    $cache_tag = \Drupal::service('jsonapi_filter_cache_tags.cache_tags_builder')->buildCacheTag('node', $this->entityReferenceFieldName, $this->termA->uuid());
    $invalidation_count = \Drupal::service('cache_tags.invalidator.checksum')->getCurrentChecksum([$cache_tag]);
    $this->assertSame(1, $invalidation_count, 'Cache tag has been cleared exactly one time.');

    // Assert the other cache tag has no invalidations.
    $cache_tag = \Drupal::service('jsonapi_filter_cache_tags.cache_tags_builder')->buildCacheTag('node', $this->entityReferenceFieldName, $this->termB->uuid());
    $invalidation_count = \Drupal::service('cache_tags.invalidator.checksum')->getCurrentChecksum([$cache_tag]);
    $this->assertSame(0, $invalidation_count, 'Cache tag has never been cleared.');

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
