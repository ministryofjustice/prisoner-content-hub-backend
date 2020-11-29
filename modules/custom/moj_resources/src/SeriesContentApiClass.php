<?php

namespace Drupal\moj_resources;

use Drupal\node\NodeInterface;
use Drupal\Core\Entity\Query\QueryFactory;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\moj_resources\Utilities;

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
   * language Tag
   *
   * @var string
  */
  protected $language;

  /**
   * NodeStorage object
   *
   * @var EntityManagerInterface
  */
  protected $nodeStorage;

  /**
   * TermStorage object
   *
   * @var EntityManagerInterface
  */
  protected $termStorage;

  /**
   * Entity Query object
   *
   * @var QueryFactory
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
   * @param string $language
   * @param int $seriesId
   * @param int $numberOfResults
   * @param int $resultsOffset
   * @param int $prisonId
   * @param string $sortOrder
   *
   * @return array
  */
  public function SeriesContentApiEndpoint($language, $seriesId, $numberOfResults, $resultsOffset, $prisonId, $sortOrder) {
    $this->language = $language;
    $this->nodeIds = $this->getSeriesContentIds($seriesId, $numberOfResults, $resultsOffset, $prisonId);
    $this->nodes = $this->loadContent($this->nodeIds);

    $series = $this->createReturnObject($this->nodes);
    $series = $this->sortSeries($series, $sortOrder);

    return $series;
  }

  /**
   * API resource function
   *
   * @param string $language
   * @param int $seriesId
   * @param int $numberOfResults
   * @param int $episodeId
   * @param int $prisonId
   * @param string $sortOrder
   *
   * @return array
  */
  public function SeriesNextEpisodeApiEndpoint($language, $seriesId, $numberOfResults, $episodeId, $prisonId, $sortOrder) {
    $this->language = $language;
    $this->nodeIds = $this->getSeriesContentIds($seriesId, null, null, $prisonId);
    $this->nodes = $this->loadContent($this->nodeIds);

    $series = $this->createReturnObject($this->nodes);
    $series = $this->sortSeries($series, $sortOrder);
    $series = $this->getNextEpisodes($episodeId, $series, $numberOfResults);

    return $series;
  }

  /**
   * Creates the object to return
   *
   * @param NodeInterface[] $seriesContent
   *
   * @return array
  */
  private function createReturnObject($seriesContent) {
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
   * Sort series
   *
   * @param array $series
   * @param string $sortOrder
   *
   * @return array
  */
  private function sortSeries($series, $sortOrder) {
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
   * Get next episodes for a series
   *
   * @param int $episodeId
   * @param array $series
   * @param int $numberOfNextEpisodes
   *
   * @return array
  */
  private function getNextEpisodes($episodeId, $series, $numberOfNextEpisodes) {
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

    $episodes = array_slice($series, $episodeOffset, $numberOfNextEpisodes);

    return $episodes;
  }

  /**
   * TranslateNode function
   *
   * @param NodeInterface $node
   *
   * @return NodeInterface
  */
  private function translateNode($node) {
    return $node->hasTranslation($this->language) ? $node->getTranslation($this->language) : $node;
  }

  /**
   * Returns a prepared statement for selecting Series Content
   *
   * @param int $seriesId
   * @param int $numberOfResultsToReturn
   * @param int $resultsOffset
   * @param int $prisonId
   *
   * @return int[]
  */
  private function getSeriesContentIds($seriesId, $numberOfResultsToReturn, $resultsOffset, $prisonId) {
    $series = Utilities::getTermFor($seriesId, $this->termStorage);
    $prison = Utilities::getTermFor($prisonId, $this->termStorage);
    $seriesPrisonCategories = Utilities::getPrisonCategoriesFor($series);
    $prisonCategories = Utilities::getPrisonCategoriesFor($prison);

    $query = $this->entity_query->get('node')
      ->condition('status', 1)
      ->accessCheck(false);

    $seriesPrison = $series->get('field_promoted_to_prison');
    $seriesHasPrisonSelected = !$seriesPrison->isEmpty();

    if ($seriesHasPrisonSelected) {
      $query->condition(Utilities::filterByTypePrison(
        $prisonId,
        $seriesPrison->target_id,
        $prisonCategories,
        $query
      ));
    } else {
      $query->condition(Utilities::filterByTypePrisonCategories(
        $prisonId,
        $seriesPrisonCategories,
        $prisonCategories,
        $query
      ));
    }

    $query->condition('field_moj_series', $seriesId);

    if ($numberOfResultsToReturn) {
      $query->range($resultsOffset, $numberOfResultsToReturn);
    }

    return $query->execute();
  }

  /**
   * Load full node details
   *
   * @param int[] $nodeIds
   * @return NodeInterface[]
  */
  private function loadContent($nodeIds) {
    return array_filter(
      $this->nodeStorage->loadMultiple($nodeIds),
      function ($item) {
        return $item->access();
      }
    );
  }
}
