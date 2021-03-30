<?php

namespace Drupal\moj_resources;

use Drupal\node\NodeInterface;
use Drupal\Core\Entity\Query\QueryFactory;
use Drupal\Core\Entity\EntityTypeManagerInterface;

require_once('Utils.php');

/**
 * CategoryMenuApiClass
 */

class CategoryMenuApiClass
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
     * Term storage object
     *
     * @var Drupal\Core\Entity\EntityManagerInterface
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
  public function CategoryMenuApiEndpoint($languageId, $categoryId, $prisonId)
  {
    $this->languageId = $languageId;
    $this->categoryId = $categoryId;
    $this->prisonId = $prisonId;

    return $this->getCategoryMenuItems();
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
  private function getCategoryMenuItems()
  {
    $contentTypes = array('page', 'moj_pdf_item', 'moj_radio_item', 'moj_video_item', );

    $query = $this->entityQuery->get('node')
      ->condition('status', 1)
      ->condition('type', $contentTypes, 'IN')
      ->accessCheck(false);

    if ($this->categoryId !== 0) {
     $query->condition('field_moj_top_level_categories', $this->categoryId);
    }

    $query = getPrisonResults($this->prisonId, $query);

    $results = $query->execute();
    $content = $this->loadContentDetails($results);

    return $this->generateMenuFrom($content);
  }

  /**
   * Extract Series And Secondary Tag Ids
   *
   * @param array $content
   * @return array
   */

  private function loadSecondaryTagsAndSeries($menuIds)
  {
    $response = array();
    $response['secondary_tag_ids'] = array_map($this->translateNode, $this->loadTermDetails($menuIds['secondary_tag_ids']));
    $response['series_ids'] = array_map($this->translateNode, $this->loadTermDetails($menuIds['series_ids']));

    return $response;
  }

  private function generateMenuFrom($content)
  {
    $menuIds = ['secondary_tag_ids' => [], 'series_ids' => []];

    foreach($content as $contentItem) {
      $secondaryTagId = $contentItem->field_moj_secondary_tags->target_id;
      $seriesId = $contentItem->field_moj_series->target_id;

      if (boolval($secondaryTagId) && !in_array($secondaryTagId, $menuIds['secondary_tag_ids'])) {
        array_push($menuIds['secondary_tag_ids'], $secondaryTagId);
      }

      if (boolval($seriesId) && !in_array($seriesId, $menuIds['series_ids'])) {
        array_push($menuIds['series_ids'], $seriesId);
      }
    }

    return $this->loadSecondaryTagsAndSeries($menuIds);
  }
  /**
     * Load full node details
     *
     * @param array $contentIds
     * @return array
     */
  private function loadContentDetails(array $contentIds)
  {
    return array_filter(
      $this->nodeStorage->loadMultiple($contentIds),
      function ($content) {
        return $content->access();
      }
    );
  }

  private function loadTermDetails(array $termIds)
  {
    return $this->termStorage->loadMultiple($termIds);
  }
}
