<?php

namespace Drupal\moj_resources;

use Drupal\node\NodeInterface;
use Drupal\Core\Entity\Query\QueryFactory;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\moj_resources\Utilities;

/**
 * RelatedContentApiClass
 */

class RelatedContentApiClass
{
  /**
   * Language Tag
   *
   * @var string
   */
  protected $languageId;
  /**
   * Node_storage object
   *
   * @var Drupal\Core\Entity\EntityManagerInterface
   */
  protected $nodeStorage;
  protected $termStorage;
  /**
   * Entitity Query object
   *
   * @var Drupal\Core\Entity\Query\QueryFactory
   *
   * Instance of querfactory
   */
  protected $entityQuery;
  protected $categoryId;
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
    $this->termStorage = $entityTypeManager->getStorage('taxonomy_term');
    $this->entityQuery = $entityQuery;
  }
  /**
   * API resource function
   *
   * @param [string] $languageId
   * @return array
   */
  public function RelatedContentApiEndpoint($languageId, $categoryId, $numberOfResults, $offsetIntoNumberOfResults, $prisonId, $sortOrder = 'ASC')
  {
    $this->languageId = $languageId;
    $this->categoryId = $categoryId;
    $this->prisonId = $prisonId;
    $relatedContentIds = $this->getRelatedContentIds($numberOfResults, $offsetIntoNumberOfResults, $sortOrder);
    $populatedContent = $this->loadRelatedContentDetail($relatedContentIds);
    $translatedContent = array_map([$this, 'translateNode'], $populatedContent);

    return array_map([$this, 'createReturnObject'], array_values($translatedContent));
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
    return $node->hasTranslation($this->languageId) ? $node->getTranslation($this->languageId) : $node;
  }
  /**
   * Get nids
   *
   * @return void
   */
  private function getRelatedContentIds($numberOfResults, $offsetIntoNumberOfResults, $sortOrder = 'ASC')
  {
    $contentTypes = array('page', 'moj_pdf_item', 'moj_radio_item', 'moj_video_item');
    $prison = Utilities::getTermFor($this->prisonId, $this->termStorage);
    $prisonCategories = Utilities::getPrisonCategoriesFor($prison);

    $query = $this->entityQuery->get('node')
      ->condition('status', 1)
      ->condition('type', $contentTypes, 'IN')
      ->accessCheck(false);

    $query->condition(Utilities::filterByPrisonCategories(
      $prisonId,
      $prisonCategories,
      $query
    ));

    $categoryCondition = $query
      ->orConditionGroup()
      ->condition('field_moj_top_level_categories', $this->categoryId)
      ->condition('field_moj_tags', $this->categoryId)
      ->condition('field_moj_secondary_tags', $this->categoryId);

    $query->condition($categoryCondition);

    $relatedContent = $query
      ->sort('nid', $sortOrder)
      ->range($offsetIntoNumberOfResults, $numberOfResults)
      ->execute();

    return $relatedContent;
  }

  /**
   * createReturnObject
   *
   * @param Node $relatedContentItem
   * @return array
   */
  private function createReturnObject($relatedContentItem)
  {
    $response = [];
    $response['id'] = $relatedContentItem->nid->value;
    $response['title'] = $relatedContentItem->title->value;
    $response['content_type'] = $relatedContentItem->type->target_id;
    $response['summary'] = $relatedContentItem->field_moj_description->summary;
    $response['image'] = $relatedContentItem->field_moj_thumbnail_image[0] ? $relatedContentItem->field_moj_thumbnail_image[0] : $relatedContentItem->field_image[0];
    $response['duration'] = $relatedContentItem->field_moj_duration ? $relatedContentItem->field_moj_duration->value : 0;

    return $response;
  }

  /**
   * Load full node details
   *
   * @param array $relatedContentIds
   * @return array
   */
  private function loadRelatedContentDetail(array $relatedContentIds)
  {
    return array_filter(
      $this->nodeStorage->loadMultiple($relatedContentIds),
      function ($relatedContentItem) {
        return $relatedContentItem->access();
      }
    );
  }
}
