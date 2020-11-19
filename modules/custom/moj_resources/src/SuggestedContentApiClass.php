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
    $translatedSuggestions = array_map([$this, 'translateContent'], $suggestions);
    return array_map([$this, 'decorateContent'], array_values($translatedSuggestions));
  }

  /**
   * Get the relevant matching items
   *
   * @return array
   */
  private function getSuggestions()
  {
    $category = $this->nodeStorage->load($this->categoryId);
    $prison = $this->nodeStorage->load($this->prisonId);

    $prisonCategories = $this->getPrisonCategoriesFor($prison);

    $secondaryTagIds = $this->getTagIds($category->field_moj_secondary_tags, );
    $matchingIds = array_unique($this->getSecondaryTagItemsFor($secondaryTagIds, $prisonCategories));

    if (count($matchingIds) < $this->numberOfResults) {
      $matchingSecondaryTagIds = $this->getAllSecondaryTagItemsFor($secondaryTagIds, $prisonCategories);
      $matchingIds = array_unique(array_merge($matchingIds, $matchingSecondaryTagIds));
    }

    if (count($matchingIds) < $this->numberOfResults) {
      $primaryTagIds = $this->getTagIds($category->field_moj_top_level_categories);
      $matchingPrimaryTagIds = $this->getPrimaryTagItemsFor($primaryTagIds, $prisonCategories);
      $matchingIds = array_unique(array_merge($matchingIds, $matchingPrimaryTagIds));
    }

    $categoryIdIndex = array_search($this->categoryId, $matchingIds);

    if ($categoryIdIndex !== false) {
      unset($matchingIds[$categoryIdIndex]);
    }

    $suggestions = $this->loadNodesDetails(array_slice($matchingIds, 0, $this->numberOfResults));

    return $suggestions;
  }

  /**
   * Get Prison Categories for a Drupal term object
   *
   * @param EntityInterface $term
   * @return int[]
  */
  private function getPrisonCategoriesFor($term) {
    $prisonCategories = [];

    foreach ($term->field_prison_categories as $prisonCategory) {
      array_push($prisonCategories, $prisonCategory->target_id);
    }

    if (empty($prisonCategories)) {
      throw new BadRequestHttpException(
        'Term does not have any prison categories selected',
        null,
        400
      );
    }

    return $prisonCategories;
  }

  /**
   * Get tags ids out of nodes
   *
   * @param array[nodes] $tags
   *
   * @return array
   */
  private function getTagIds($tags) {
    return array_map(
      function($tag) {
        return $tag->target_id;
      },
      $tags
    );
  }

  /**
   * Get matching primary items for supplied tag ids
   *
   * @param array[int] $tagIds
   *
   * @return array
   */
  private function getPrimaryTagItemsFor($tagIds, $prisonCategories)
  {
    return $this->getInitialQuery()
      ->condition('field_moj_top_level_categories', $tagIds, 'IN')
      ->condition('field_prison_categories', $prisonCategories, 'IN')
      ->sort('nid', 'DESC')
      ->range(0, $this->numberOfResults)
      ->execute();
  }

  /**
   * Get matching secondary items for supplied tag ids
   *
   * @param array[int] $tagIds
   *
   * @return array
   */
  private function getAllSecondaryTagItemsFor($tagIds, $prisonCategories)
  {
    $query = $this->getInitialQuery();
    $group = $query
      ->orConditionGroup()
      ->condition('field_moj_secondary_tags', $tagIds, 'IN')
      ->condition('field_moj_tags', $tagIds, 'IN');

    return $query
      ->condition($group)
      ->condition('field_prison_categories', $prisonCategories, 'IN')
      ->sort('nid', 'DESC')
      ->range(0, $this->numberOfResults)
      ->execute();
  }

  /**
   * Get matching secondary items for supplied tag ids
   *
   * @param array[int] $tagIds
   *
   * @return array
   */
  private function getSecondaryTagItemsFor($tagIds, $prisonCategories)
  {
    $query = $this->getInitialQuery();

    for ($i = 0; $i < count($tagIds); $i++) {
        $query->condition("field_moj_secondary_tags.$i", $tagIds[$i]);
    }

    return $query
      ->condition('field_prison_categories', $prisonCategories, 'IN')
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
   * TranslateNode function
   *
   * @param NodeInterface $node
   *
   * @return $node
   */
  private function translateContent(NodeInterface $node)
  {
    return $node->hasTranslation($this->language) ? $node->getTranslation($this->language) : $node;
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
   * @param array $nodeIds
   * @return array
   */
  private function loadNodesDetails(array $nodeIds)
  {
    return array_filter(
      $this->nodeStorage->loadMultiple($nodeIds),
      function ($node) {
        return $node->access();
      }
    );
  }
}

