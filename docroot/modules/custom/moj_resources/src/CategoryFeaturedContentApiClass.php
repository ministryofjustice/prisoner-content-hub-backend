<?php

namespace Drupal\moj_resources;

use Drupal\node\NodeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityManagerInterface;

require_once('Utils.php');

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
   * Node_storage object
   *
   * @var EntityManagerInterface
   */
  protected $termStorage;

  /**
   * Class Constructor
   *
   * @param EntityTypeManagerInterface $entityTypeManager
   */
  public function __construct(
    EntityTypeManagerInterface $entityTypeManager
  ) {
    $this->nodeStorage = $entityTypeManager->getStorage('node');
    $this->termStorage = $entityTypeManager->getStorage('taxonomy_term');
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
    return self::getFeaturedContentNodeIds($categoryId, $numberOfResults, $prisonId);
  }
  /**
   * Get content ids
   *
   * @param int $categoryId
   * @param int $numberOfResults
   * @param int $prisonId
   *
   * @return array
   */
  protected function getFeaturedContentNodeIds($categoryId, $numberOfResults, $prisonId = 0)
  {
    $series = $this->promotedSeries($categoryId, $prisonId);
    $nodes = $this->promotedNodes($categoryId, $numberOfResults, $prisonId);
    $results = array_merge($series, $nodes);

  //sort them out
    usort($results, function ($a, $b) {
      if ($a->changed && $b->changed) {
        return $b->changed->value - $a->changed->value;
      }

      return 0;
    });

    return array_slice($results, 0, $numberOfResults);
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

    return $seriesIds;
  }
  /**
   * Creates the object to return
   *
   * @param int $categoryId
   * @param int $prisonId
   *
   * @return array
  */
  private function promotedSeries($categoryId, $prisonId)
  {
    $nodeIds = $this->allContentFor($categoryId);
    $nodes = $this->loadNodesDetails($nodeIds);
    $series = $this->extractSeriesIdsFrom($nodes);

    return $this->promotedTerms(array_unique($series), $prisonId);
  }
  /**
   * Creates the object to return
   *
   * @param int $categoryId
   * @param int $numberOfResults
   * @param int $prisonId
   *
   * @return array
  */
  private function promotedNodes($categoryId, $numberOfResults, $prisonId)
  {
    $query = $this->nodeStorage->getQuery()
      ->condition('status', 1)
      ->condition('field_moj_category_featured_item', 1)
      ->accessCheck(false);

    $query = getPrisonResults($prisonId, $query);
    $query->condition('field_moj_top_level_categories', $categoryId);
    $query->range(0, $numberOfResults);
    $nodes = $query->execute();

    $promotedContent = $this->loadNodesDetails($nodes);

    return array_map(array($this, 'decorateContent'), $promotedContent);
  }
  /**
   * Creates the object to return
   *
   * @param int $categoryId
   *
   * @return NodeInterface
  */
  private function allContentFor($categoryId)
  {
    $query = $this->nodeStorage->getQuery()
      ->condition('status', 1)
      ->accessCheck(false);
      $query->condition('field_moj_top_level_categories', $categoryId);

    return $query->execute();
  }
  /**
   * Creates the object to return
   *
   * @param int[] $termIds
   * @param int $prisonId
   *
   * @return array
  */
  private function promotedTerms($termIds, $prisonId)
  {
    $loadedTerms = $this->termStorage->loadMultiple($termIds);
    $promotedTerms = array_filter($loadedTerms, function ($term) use ($prisonId) {
      if ($term->field_moj_category_featured_item->value == true && $prisonId == $term->field_promoted_to_prison->target_id) {
        return true;
      } elseif ($term->field_moj_category_featured_item->value == true && !$term->field_promoted_to_prison->target_id) {
        return true;
      } else {
        return false;
      }
    });

    usort($promotedTerms, function ($a, $b) {
      return $b->changed->value - $a->changed->value;
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
