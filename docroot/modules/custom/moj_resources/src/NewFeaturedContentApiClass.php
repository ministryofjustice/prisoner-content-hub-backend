<?php

namespace Drupal\moj_resources;

use Drupal\node\NodeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

require_once('Utils.php');

/**
 * NewFeaturedContentApiClass
 */

class NewFeaturedContentApiClass
{
  /**
   * Node_storage object
   *
   * @var Drupal\Core\Entity\EntityManagerInterface
   */
  protected $nodeStorage;

  /**
   * Class Constructor
   *
   * @param EntityTypeManagerInterface $entityTypeManager
   */
  public function __construct(
    EntityTypeManagerInterface $entityTypeManager
  ) {
    $this->nodeStorage = $entityTypeManager->getStorage('node');
  }
  /**
   * API resource function
   *
   * @param [string] $prisonId
   * @return array
   */
  public function FeaturedContentApiEndpoint($prisonId = 0)
  {
    $results = $this->getFeaturedContent($prisonId);

    return array_slice($results, 0, 1);
  }

  private function decorateContent($featuredContent)
  {
    $response = [];
    $response['id'] = $featuredContent->nid->value;
    $response['title'] = $featuredContent->title->value;
    $response['content_type'] = $featuredContent->type->target_id;
    $response['large_tiles'] = $this->getTiles($featuredContent->field_moj_featured_tile_large);
    $response['small_tiles'] = $this->getTiles($featuredContent->field_moj_featured_tile_small);

    return $response;
  }

  private function getTiles($tiles) {
    $tileIds = [];
    $numberOfTiles = count($tiles);

    for ($i = 0; $i < $numberOfTiles; $i++) {
      array_push($tileIds, $tiles[$i]->target_id);
    }
    $results = $this->loadNodesDetails($tileIds);
    return array_values(array_map(array($this, 'decorateTile'), $results));
  }

  private function decorateTile($tile)
  {
    $response = [];
    $response['id'] = $tile->nid->value;
    $response['title'] = $tile->title->value;
    $response['content_type'] = $tile->type->target_id;
    $response['summary'] = $tile->field_moj_description->summary;
    $response['image'] = $tile->field_moj_thumbnail_image ? $tile->field_moj_thumbnail_image[0] : $tile->field_image[0];
    $response['series'] = $tile->field_moj_series;

    return $response;
  }

  private function getFeaturedContent($prisonId)
  {
    $query = $this->nodeStorage->getQuery()
      ->condition('type', 'featured_articles')
      ->condition('status', 1)
      ->accessCheck(false);

    $query = getPrisonResults($prisonId, $query);

    $results = $query->execute();

    $featuredContent = $this->loadNodesDetails($results);

    return array_map(array($this, 'decorateContent'), $featuredContent);
  }

  /**
   * Load full node details
   *
   * @param array $nodeIds
   * @return array
   */
  protected function loadNodesDetails(array $nodeIds)
  {
    return $this->nodeStorage->loadMultiple($nodeIds);
  }

  /**
   * Sanitise node
   *
   * @param [type] $item
   * @return void
   */
  protected function serialize($item)
  {
    $serializer = \Drupal::service($item->getType() . '.serializer.default');
    return $serializer->serialize($item, 'json', ['plugin_id' => 'entity']);
  }
}
