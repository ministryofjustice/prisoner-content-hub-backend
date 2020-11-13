<?php

namespace Drupal\moj_resources;

use Drupal\node\NodeInterface;
use Drupal\Core\Entity\Query\QueryFactory;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\moj_resources\Utilities;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * PromotedContentApiClass
 */

class SeriesContentApiClass
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
   * NodeStorage object
   *
   * @var Drupal\Core\Entity\EntityManagerInterface
   */
  protected $nodeStorage;

  /**
   * TermStorage object
   *
   * @var Drupal\Core\Entity\EntityManagerInterface
   */
  protected $termStorage;

  /**
   * Entity Query object
   *
   * @var Drupal\Core\Entity\Query\QueryFactory
   *
   * Instance of QueryFactory
   */
  protected $entity_query;

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
    $this->entity_query = $entityQuery;
  }

  /**
   * API resource function
   *
   * @param [string] $lang
   * @return array
   */
  public function SeriesContentApiEndpoint($lang, $seriesId, $number, $offset, $prison, $sortOrder)
  {
    $this->lang = $lang;
    $this->node_ids = $this->getSeriesContentNodeIds($seriesId, $number, $offset, $prison);
    $this->nodes = $this->loadNodesDetails($this->node_ids);

    $series = $this->decorateSeries($this->nodes);
    $series = $this->sortSeries($series, $sortOrder);

    return $series;
  }

  /**
   * API resource function
   *
   * @param [string] $lang
   * @return array
   */
  public function SeriesNextEpisodeApiEndpoint($lang, $seriesId, $number, $episodeId, $prison, $sortOrder)
  {
    $this->lang = $lang;
    $this->node_ids = $this->getSeriesContentNodeIds($seriesId, null, null, $prison);
    $this->nodes = $this->loadNodesDetails($this->node_ids);
    $series = $this->decorateSeries($this->nodes);
    $series = $this->sortSeries($series, $sortOrder);
    $series = $this->getNextEpisodes($episodeId, $series, $number);

    return $series;
  }

  /**
   * decorateSeries
   *
   */
  private function decorateSeries($seriesContent)
  {
    return array_map(function ($node) {
      $episodeId = ($node->field_moj_season->value * 1000) + ($node->field_moj_episode->value);
      $content = [];
      $content["episode_id"] = $episodeId;
      $content["content_type"] = $node->type->target_id;
      $content["title"] = $node->title->value;
      $content["id"] = $node->nid->value;
      $content["image"] = $node->field_moj_thumbnail_image[0];
      $content["season"] = $node->field_moj_season->value;
      $content["episode"] = $node->field_moj_episode->value;
      $content["duration"] = $node->field_moj_duration ? $node->field_moj_duration->value : 0;
      $content["description"] = $node->field_moj_description[0];
      $content["categories"] = $node->field_moj_top_level_categories;
      $content["prison_categories"] = $node->field_prison_categories;

      if ($node->field_moj_secondary_tags) {
        $content["secondary_tags"] = $node->field_moj_secondary_tags;
      } else {
        $content["secondary_tags"] = $node->field_moj_tags;
      }

      if ($content["content_type"] === 'moj_radio_item') {
        $content["media"] = $node->field_moj_audio[0];
      } else {
        $content["media"] = $node->field_video[0];
      }

      return $content;
    }, $seriesContent);
  }

  /**
   * sortSeries
   *
   */
  private function sortSeries(&$series, $sortOrder)
  {
    usort($series, function ($a, $b) use ($sortOrder) {
      if ($a['episode_id'] == $b['episode_id']) {
        return 0;
      }

      if ($sortOrder == 'ASC') {
        return ($a['episode_id'] < $b['episode_id']) ? -1 : 1;
      }

      return ($b['episode_id'] > $a['episode_id']) ? 1 : -1;
    });

    return $series;
  }

  /**
   * getNextEpisodes
   *
   */
  private function getNextEpisodes($episodeId, $series, $number)
  {
    function indexOf($comp, $array)
    {
      foreach ($array as $key => $value) {
        if ($comp($value)) {
          return $key;
        }
      }
    }

    $episodeIndex = indexOf(function ($value) use ($episodeId) {
      return $value['episode_id'] == $episodeId;
    }, $series);

    if (is_null($episodeIndex)) {
      return array();
    }

    $episodeOffset = $episodeIndex + 1;

    $episodes = array_slice($series, $episodeOffset, $number);

    return $episodes;
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
    return $node->hasTranslation($this->lang) ? $node->getTranslation($this->lang) : $node;
  }

  /**
   * Check series is valid or error
   *
   * @param int $seriesId
   *
   * @return object
   */
  private function getSeries($seriesId) {
    $series = $this->termStorage->load($seriesId);

    if (!$series) {
      throw new NotFoundHttpException(
        t('Series not found'),
        null,
        404
      );
    }

    return $series;
  }

  /**
   * Check prison is valid or error
   *
   * @param int $prisonId
   *
   * @return object
   */
  private function getPrison($prisonId) {
    $prison = $this->termStorage->load($prisonId);

    if (!$prison) {
      throw new NotFoundHttpException(
        t('Prison not found'),
        null,
        404
      );
    }

    return $prison;
  }

  /**
   * Returns prison categories for prison and series
   *
   * @param int $prison
   * @param int $series
   *
   * @return array
   */
  private function getPrisonCategories($prison, $series) {
    $prisonCategories = [];

    foreach ($prison->field_prison_categories as $prisonCategory) {
      array_push($prisonCategories, $prisonCategory->target_id);
    }

    $seriesPrisonCategories = [];

    foreach ($series->field_prison_categories as $prisonCategory) {
      array_push($seriesPrisonCategories, $prisonCategory->target_id);
    }

    if (empty($prisonCategories['series'])) {
      throw new BadRequestHttpException(
        t('The Series does not have any prison categories selected'),
        null,
        400
      );
    }

    if (empty($prisonCategories['prison'])) {
      throw new BadRequestHttpException(
        t('The Prison does not have any prison categories selected'),
        null,
        400
      );
    }

    return [
      'prison' => $prisonCategories,
      'series' => $seriesPrisonCategories
    ];
  }

  private function getPrisonFilteredQuery($prisonId, $idToCheck, $prisonCategories, $query) {
    if ($idToCheck !== $prisonId) {
      throw new BadRequestHttpException(
        t('Supplied prison does not match id to check'),
        null,
        400
      );
    }

    $prisonCategoriesFilter = $query
      ->andConditionGroup()
      ->notExists('field_moj_prisons')
      ->condition('field_prison_categories', $prisonCategories, 'IN');
    $noPrisonCategoryFilter = $query
      ->andConditionGroup()
      ->notExists('field_moj_prisons')
      ->notExists('field_prison_categories');
    $contentFilter = $query
      ->orConditionGroup()
      ->condition('field_moj_prisons', $prisonId, '=')
      ->condition($prisonCategoriesFilter)
      ->condition($noPrisonCategoryFilter);
    $query->condition($contentFilter);

    return $query;
  }

  /**
   * Get node_ids
   *
   * @return void
   */
  private function getSeriesContentNodeIds($seriesId, $numberToReturn, $offset, $prisonId)
  {
    $series = $this->getSeries($seriesId);
    $prison = $this->getPrison($prisonId);
    $prisonCategories = $this->getPrisonCategories($prison, $series);

    // checking filter by prison
    if ($series->field_promoted_to_prison !== null) {
      $query = $this->getPrisonFilteredQuery(
        $series->field_promoted_to_prison->target_id,
        $prisonId,
        $prisonCategories['prison'],
        $query
      );
    } else {
      $hasNoMatchingPrisonCategories = empty(array_intersect($prisonCategories['prison'], $prisonCategories['series']));

      if ($hasNoMatchingPrisonCategories) {
        throw new BadRequestHttpException(
          t('The Series does not have a matching prison category for this prison'),
          null,
          400
        );
      }

      $query->condition('field_prison_categories', $prisonCategories, 'IN');
    }

    $query = $this->entity_query->get('node')
      ->condition('status', 1)
      ->accessCheck(false);

    $query->condition('field_moj_series', $seriesId);

    if ($numberToReturn) {
      $query->range($offset, $numberToReturn);
    }

    return $query->execute();
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
      function ($item) {
        return $item->access();
      }
    );
  }

  /**
   * Sanitise node
   *
   * @param [type] $item
   * @return void
   */
  private function serialize($item)
  {
    $serializer = \Drupal::service($item->getType() . '.serializer.default'); // TODO: Inject dependency
    return $serializer->serialize($item, 'json', ['plugin_id' => 'entity']);
  }
}
