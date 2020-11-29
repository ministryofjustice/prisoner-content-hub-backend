<?php

namespace Drupal\moj_resources;

use Drupal\node\NodeInterface;
use Drupal\Core\Entity\Query\QueryFactory;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\moj_resources\Utilities;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

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
   * TermStorage object
   *
   * @var EntityManagerInterface
  */
  protected $termStorage;
  /**
   * Entitity Query object
   *
   * @var Drupal\Core\Entity\Query\QueryFactory
   *
   * Instance of querfactory
   */
  protected $entityQuery;
  protected $prisonId;
  protected $prisonCategories;

  /**
   * Class Constructor
   *
   * @param EntityTypeManagerInterface $entityTypeManager
   * @param QueryFactory $entityQuery
   */
  public function __construct(
    EntityTypeManagerInterface $entityTypeManager,
    QueryFactory $entityQuery
  ) {
    $this->nodeStorage = $entityTypeManager->getStorage('node');
    $this->termStorage = $entityTypeManager->getStorage('taxonomy_term');
    $this->entityQuery = $entityQuery;
  }
  /**
   * API resource function
   *
   * @param [string] $prisonId
   * @return array
   */
  public function FeaturedContentApiEndpoint($prisonId)
  {
    $this->prisonId = $prisonId;
    $results = $this->getFeaturedContent();

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
    $filteredResults = array();

    foreach ($results as $content) {
      $contentPrisons = Utilities::getPrisonsFor($content);

      if (empty($contentPrisons)) {
        $contentPrisonCategories = Utilities::getPrisonCategoriesFor($content);
        $matchingPrisonCategories = array_intersect($this->prisonCategories, $contentPrisonCategories);

        if (empty($matchingPrisonCategories)) {
          throw new BadRequestHttpException(
            'The content does not have a matching prison category for this prison',
            null,
            400
          );
        }

        array_push($filteredResults, $content);
      } else {
        $matchingPrisons = in_array($this->prisonId, $contentPrisons);

        if (!$matchingPrisons) {
          throw new BadRequestHttpException(
            'The content is not available for this prison',
            null,
            400
          );
        }

        array_push($filteredResults, $content);
      }
    }

    return array_values(array_map(array($this, 'decorateTile'), $filteredResults));
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

  private function getFeaturedContent()
  {
    $query = $this->entityQuery->get('node')
      ->condition('type', 'featured_articles')
      ->condition('status', 1)
      ->accessCheck(false);

    $prison = Utilities::getTermFor($this->prisonId, $this->termStorage);
    $this->prisonCategories = Utilities::getPrisonCategoriesFor($prison);

    $query->condition(Utilities::filterByPrisonCategories(
      $this->prisonId,
      $this->prisonCategories,
      $query
    ));

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

}
