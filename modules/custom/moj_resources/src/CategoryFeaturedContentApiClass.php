<?php

namespace Drupal\moj_resources;

use Drupal\node\NodeInterface;
use Drupal\Core\Entity\Query\QueryFactory;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\moj_resources\Utilities;

/**
 * CategoryFeaturedContentApiClass
 */

class CategoryFeaturedContentApiClass
{
   /**
   * Nodes
   *
   * @var array
   */
  protected $nodes = array();
  /**
   * Node_storage object
   *
   * @var EntityManagerInterface
   */
  protected $nodeStorage;
  /**
   * Entity Query object
   *
   * @var QueryFactory
   *
   * Instance of QueryFactory
   */
  protected $entityQuery;
  /**
   * Node_storage object
   *
   * @var EntityManagerInterface
   */
  protected $termStorage;
  protected $categoryId;
  protected $prisonId;
  protected $numberOfResults;
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
   * @param int $categoryId
   * @param int $numberOfResults
   * @param int $prisonId
   *
   * @return array
   */
  public function CategoryFeaturedContentApiEndpoint($categoryId, $numberOfResults, $prisonId)
  {
    $this->categoryId = $categoryId;
    $this->prisonId = $prisonId;
    $this->numberOfResults = $numberOfResults;

    return self::getFeaturedContentNodeIds();
  }
  /**
   * Get content ids
   *
   * @return array
   */
  protected function getFeaturedContentNodeIds()
  {
    $prison = Utilities::getTermFor($this->prisonId, $this->termStorage);
    $this->prisonCategories = Utilities::getPrisonCategoriesFor($prison, false);

    $series = $this->promotedSeries();
    $nodes = $this->promotedNodes();
    $results = array_merge($series, $nodes);

    //sort them out
    usort($results, function ($a, $b) {
      if ($a->changed && $b->changed) {
        return $b->changed->value - $a->changed->value;
      }

      return 0;
    });

    return array_slice($results, 0, $this->numberOfResults);
  }
  /**
   * Creates the object to return
   *
   * @param NodeInterface[] $node
   *
   * @return array
  */
  private function decorateContent($node)
  {
    $content = [];
    $content['id'] = $node->nid->value;
    $content['title'] = $node->title->value;
    $content['content_type'] = $node->type->target_id;
    $content['summary'] = $node->field_moj_description->summary;
    $content['image'] = $node->field_moj_thumbnail_image[0] ? $node->field_moj_thumbnail_image[0] : $node->field_image[0];
    $content['duration'] = $node->field_moj_duration->value;

    return $content;
  }
  /**
   * Creates the object to return
   *
   * @param NodeInterface[] $term
   *
   * @return array
  */
  private function decorateTerm($term)
  {
    $content = [];
    $content['id'] = $term->tid->value;
    $content['title'] = $term->name->value;
    $content['content_type'] = $term->vid->target_id;
    $content['summary'] = $term->field_content_summary->value;
    $content['image'] = $term->field_featured_image[0];
    $content['audio'] = $term->field_featured_audio[0];
    $content['video'] = $term->field_featured_video[0];

    return $content;
  }
  /**
   * Creates the object to return
   *
   * @param NodeInterface[] $nodes
   *
   * @return array
  */
  private function extractSeriesIdsFrom($nodes)
  {
    $seriesIds = [];
    foreach ($nodes as $key => $n) {
      $seriesIds[] = $n->field_moj_series->target_id;
    }

    return array_unique($seriesIds);
  }
  /**
   * Creates the object to return
   *
   * @return array
  */
  private function promotedSeries()
  {
    $nodeIds = $this->allContentFor();
    $nodes = $this->loadNodesDetails($nodeIds);
    $series = $this->extractSeriesIdsFrom($nodes);

    return $this->promotedTerms($series);
  }
  /**
   * Creates the object to return
   *
   * @return array
  */
  private function promotedNodes()
  {
    $query = $this->entityQuery->get('node')
      ->condition('status', 1)
      ->condition('field_moj_category_featured_item', 1)
      ->condition('field_moj_top_level_categories', $this->categoryId)
      ->accessCheck(false);

    $query->condition(Utilities::filterByPrisonCategories(
      $this->prisonId,
      $this->prisonCategories,
      $query
    ));

    $nodes = $query->execute();

    $promotedContent = $this->loadNodesDetails($nodes);

    return array_map(array($this, 'decorateContent'), $promotedContent);
  }
  /**
   * Creates the object to return
   *
   * @return NodeInterface
  */
  private function allContentFor()
  {
    $query = $this->entityQuery->get('node')
      ->condition('status', 1)
      ->condition('field_moj_top_level_categories', $this->categoryId)
      ->accessCheck(false);

    $query->condition(Utilities::filterByPrisonCategories(
      $this->prisonId,
      $this->prisonCategories,
      $query
    ));

    return $query->execute();
  }
  /**
   * Creates the object to return
   *
   * @param int[] $termIds
   *
   * @return array
  */
  private function promotedTerms($termIds)
  {
    $loadedTerms = $this->termStorage->loadMultiple($termIds);
    $prisonId = $this->prisonId;

    $promotedTerms = array_filter($loadedTerms, function ($term) use ($prisonId) {
      $promotedContent = $term->field_moj_category_featured_item->value;
      $promotedToPrison = $term->field_promoted_to_prison->target_id;

      if ($promotedContent) {
        if ($term->hasField('field_promoted_to_prison') && !$term->get('field_promoted_to_prison')->isEmpty()) {
          $selectedPrisonDoesNotMatchRequest = intval($term->get('field_promoted_to_prison')->target_id) !== intval($prisonId);

          if ($selectedPrisonDoesNotMatchRequest) {
            return false;
          }
        }

        if ($term->hasField('field_prison_categories')
          && !$term->get('field_prison_categories')->isEmpty()) {
          $termPrisonCategories = [];

          foreach($term->get('field_prison_categories') as $termPrisonCategory) {
            array_push($termPrisonCategories, intval($termPrisonCategory->target_id));
          }

          $matchingPrisonCategories = array_intersect($this->prisonCategories, $termPrisonCategories);
          $hasNoMatchingPrisonCategories = empty($matchingPrisonCategories);

          if ($hasNoMatchingPrisonCategories) {
            return false;
          }
        }

        return true;
      }

      return false;
    });

    return array_map(array($this, 'decorateTerm'), $promotedTerms);
  }

  /**
   * Load full node details
   *
   * @param array $nodeIds
   *
   * @return array
   */
  protected function loadNodesDetails(array $nodeIds)
  {
    return array_filter(
      $this->nodeStorage->loadMultiple($nodeIds),
      function ($term) {
        return $term->access();
      }
    );
  }
}
