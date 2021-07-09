<?php

namespace Drupal\moj_resources;

use Drupal\image\Entity\ImageStyle;
use Drupal\node\NodeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

require_once('Utils.php');

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
   * @param [string] $languageId
   * @return array
   */
  public function RelatedContentApiEndpoint($languageId, $categoryId, $numberOfResults, $offsetIntoNumberOfResults, $prisonId, $sortOrder = 'ASC')
  {
    $this->languageId = $languageId;
    $relatedContentIds = $this->getRelatedContentIds($categoryId, $numberOfResults, $offsetIntoNumberOfResults, $prisonId, $sortOrder);
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
  private function getRelatedContentIds($categoryId, $numberOfResults, $offsetIntoNumberOfResults, $prisonId, $sortOrder = 'DESC')
  {
    $contentTypes = array('page', 'moj_pdf_item', 'moj_radio_item', 'moj_video_item');

    $query = $this->nodeStorage->getQuery()
      ->condition('status', 1)
      ->condition('type', $contentTypes, 'IN')
      ->accessCheck(false);

    if ($categoryId !== 0) {
      $categoryCondition = $query
        ->orConditionGroup()
        ->condition('field_moj_top_level_categories', $categoryId)
        ->condition('field_moj_secondary_tags', $categoryId);

      $query->condition($categoryCondition);
    }

    $query = getPrisonResults($prisonId, $query);

    $relatedContent = $query
      ->sort('created', $sortOrder)
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
    $referenced_entites =  $relatedContentItem->get('field_moj_thumbnail_image')->referencedEntities();
    if (!empty($referenced_entites)) {
      $file = $referenced_entites[0];
      $response["image"] = [];
      $response["image"]['url'] =  file_create_url(ImageStyle::load('tile_small')->buildUri($file->getFileUri()));
      $response["image"]['alt'] =  $relatedContentItem->field_moj_thumbnail_image->alt;
      $response["image"]['title'] =  $relatedContentItem->field_moj_thumbnail_image->title;
    }
    else {
      $response['image'] = [];
    }
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
