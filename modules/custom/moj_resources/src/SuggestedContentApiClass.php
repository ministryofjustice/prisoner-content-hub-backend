<?php

namespace Drupal\moj_resources;

use Drupal\node\NodeInterface;
use Drupal\Core\Entity\Query\QueryFactory;
use Drupal\Core\Entity\EntityTypeManagerInterface;

require_once('Utils.php');

/**
 * SuggestedContentApiClass
 */

class SuggestedContentApiClass
{
  /**
   * Language Tag
   *
   * @var string
   */
  protected $language;
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

  protected $categoryId;
  protected $numberOfResults;
  protected $prisonId;

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
    $this->entityQuery = $entityQuery;
  }

  /**
   * API resource function
   *
   * @param [string] $language
   * @return array
   */
  public function SuggestedContentApiEndpoint($language, $categoryId, $numberOfResults, $prisonId)
  {
    $this->language = $language;
    $this->categoryId = $categoryId;
    $this->numberOfResults = $numberOfResults;
    $this->prisonId = $prisonId;
    $suggestions = $this->getSuggestions();
    $translatedSuggestions = array_map([$this, 'translateNode'], $suggestions);
    return array_map([$this, 'decorateContent'], array_values($translatedSuggestions));
  }

  /**
   * TranslateNode function
   *
   * @param NodeInterface $node
   *
   * @return $node
   */
  private function translateNode(NodeInterface $node)
  {
    return $node->hasTranslation($this->language) ? $node->getTranslation($this->language) : $node;
  }

  /**
   * Get the relevant matching items
   *
   * @return array
   */
  private function getSuggestions()
  {
    $category = $this->nodeStorage->load($this->categoryId);
    $secondaryTagIds = $this->getTagIds($category->field_moj_secondary_tags);
    $matchingIds = array_unique($this->getSecondaryTagItemsFor($secondaryTagIds));

    if (count($matchingIds) < $this->numberOfResults) {
      $matchingSecondaryTagIds = $this->getAllSecondaryTagItemsFor($secondaryTagIds);
      $matchingIds = array_unique(array_merge($matchingIds, $matchingSecondaryTagIds));
    }

    if (count($matchingIds) < $this->numberOfResults) {
      $primaryTagIds = $this->getTagIds($category->field_moj_top_level_categories);
      $matchingPrimaryTagIds = $this->getPrimaryTagItemsFor($primaryTagIds);
      $matchingIds = array_unique(array_merge($matchingIds, $matchingPrimaryTagIds));
    }

    $categoryIdIndex = array_search($this->categoryId, $matchingIds);

    if ($categoryIdIndex !== false) {
      unset($matchingIds[$categoryIdIndex]);
    }

    return $this->loadNodesDetails(array_slice($matchingIds, 0, $this->numberOfResults));
  }

  /**
   * Get tags ids out of nodes
   *
   * @param array[nodes] $tags
   *
   * @return array
   */
  private function getTagIds($tags) {
    $tagIds = [];
    $numberOfTags = count($tags);

    for ($i = 0; $i < $numberOfTags; $i++) {
      array_push($tagIds, $tags[$i]->target_id);
    }

    return $tagIds;
  }

  /**
   * Get matching primary items for a given id
   *
   * @param int $id
   *
   * @return array
   */
  private function getPrimaryTagItemsFor($ids)
  {
    return $this->getInitialQuery()
      ->condition('field_moj_top_level_categories', $ids, 'IN')
      ->sort('nid', 'DESC')
      ->range(0, $this->numberOfResults)
      ->execute();
  }

  /**
   * Get matching primary or secondary items for a given id, excluding the passed in ids
   *
   * @param int $id
   * @param boolean $primary
   *
   * @return array
   */
  private function getAllSecondaryTagItemsFor($ids)
  {
    $query = $this->getInitialQuery();
    $group = $query
      ->orConditionGroup()
      ->condition('field_moj_secondary_tags', $ids, 'IN')
      ->condition('field_moj_tags', $ids, 'IN');

    return $query
      ->condition($group)
      ->sort('nid', 'DESC')
      ->range(0, $this->numberOfResults)
      ->execute();
  }

  /**
   * Get matching primary or secondary items for a given id
   *
   * @param array[int] $ids
   *
   * @return array
   */
  private function getSecondaryTagItemsFor($ids)
  {
    $query = $this->getInitialQuery();

    for ($i = 0; $i < count($ids); $i++) {
        $query->condition("field_moj_secondary_tags.$i", $ids[$i]);
    }

    return $query
      ->sort('nid', 'DESC')
      ->range(0, $this->numberOfResults)
      ->execute();
  }

  /**
   * Setup a query
   *
   * @return array
   */
  private function getInitialQuery()
  {
    $types = array('page', 'moj_pdf_item', 'moj_radio_item', 'moj_video_item',);
    $query = $this->entityQuery->get('node')
      ->condition('status', 1)
      ->condition('type', $types, 'IN')
      ->accessCheck(false);

    return getPrisonResults($this->prisonId, $query);
  }

  /**
   * decorateContent
   *
   * @param Node $node
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
   * Load full node details
   *
   * @param array $nids
   * @return array
   */
  private function loadNodesDetails(array $nids)
  {
    return array_filter(
      $this->nodeStorage->loadMultiple($nids),
      function ($item) {
        return $item->access();
      }
    );
  }
}

