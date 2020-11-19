<?php

namespace Drupal\moj_resources;

use Drupal\node\NodeInterface;
use Drupal\Core\Entity\Query\QueryFactory;
use Drupal\Core\Entity\EntityTypeManagerInterface;

require_once('Utils.php');

/**
 * CategoryFeaturedContentApiClass
 */

class CategoryFeaturedContentApiClass
{
  /**
   * Node IDs
   *
   * @var array
   */
  protected $nodeIds = array();
  /**
   * Nodes
   *
   * @var array
   */
  protected $nodes = array();
  /**
   * Language Tag
   *
   * @var string
   */
  protected $lang;
  /**
   * Node_storage object
   *
   * @var Drupal\Core\Entity\EntityManagerInterface
   */
  protected $nodeStorage;
  /**
   * Entitity Query object
   *
   * @var Drupal\Core\Entity\Query\QueryFactory
   *
   * Instance of querfactory
   */
  protected $entityQuery;

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
   * @param [string] $lang
   * @return array
   */
  public function CategoryFeaturedContentApiEndpoint($lang, $category, $number, $prison)
  {
    return self::getFeaturedContentNodeIds($category, $number, $prison);
  }
  /**
   * TranslateNode function
   *
   * @param NodeInterface $node
   *
   * @return $node
   */
  protected function translateNode(NodeInterface $node)
  {
    return $node->hasTranslation($this->lang) ? $node->getTranslation($this->lang) : $node;
  }
  /**
   * Get node ids
   *
   * @return void
   */
  protected function getFeaturedContentNodeIds($category, $number, $prison = 0)
  {
    $series = $this->promotedSeries($category, $prison);
    $nodes = $this->promotedNodes($category, $number, $prison);
    $query = array_merge($series, $nodes);

    //sort them out
    usort($query, function ($a, $b) {
      if ($a->changed && $b->changed) {
        return $b->changed->value - $a->changed->value;
      }

      return 0;
    });

    return array_slice($query, 0, $number);
  }

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

  private function extractSeriesIdsFrom($nodes)
  {
    $seriesIds = [];
    foreach ($nodes as $key => $n) {
      $seriesIds[] = $n->field_moj_series->target_id;
    }

    return $seriesIds;
  }

  private function promotedSeries($category, $prison)
  {
    $nodeIds = $this->allContentFor($category);
    $nodes = $this->loadNodesDetails($nodeIds);
    $series = $this->extractSeriesIdsFrom($nodes);

    return $this->promotedTerms(array_unique($series), $prison);
  }

  private function promotedNodes($category, $number, $prison)
  {
    $query = $this->entityQuery->get('node')
      ->condition('status', 1)
      ->condition('field_moj_category_featured_item', 1)
      ->accessCheck(false);

    $query = getPrisonResults($prison, $query);

    if ($category !== 0) {
      $query->condition('field_moj_top_level_categories', $category);
    };

    $query->range(0, $number);
    $nodes = $query->execute();

    $promotedContent = $this->loadNodesDetails($nodes);

    return array_map(array($this, 'decorateContent'), $promotedContent);
  }

  private function allContentFor($category)
  {
    $query = $this->entityQuery->get('node')
      ->condition('status', 1)
      ->accessCheck(false);

    if ($category !== 0) {
      $query->condition('field_moj_top_level_categories', $category);
    };

    return $query->execute();
  }

  private function promotedTerms($termIds, $prison)
  {
    $loadedTerms = $this->termStorage->loadMultiple($termIds);
    $promotedTerms = array_filter($loadedTerms, function ($term) use ($prison) {
      if ($term->field_moj_category_featured_item->value == true && $prison == $term->field_promoted_to_prison->target_id) {
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

  /**
   * Sanitise node
   *
   * @param [type] $term
   * @return void
   */
  protected function serialize($term)
  {
    $serializer = \Drupal::service($term->getType() . '.serializer.default'); // TODO: Inject dependency
    return $serializer->serialize($term, 'json', ['plugin_id' => 'entity']);
  }
}
