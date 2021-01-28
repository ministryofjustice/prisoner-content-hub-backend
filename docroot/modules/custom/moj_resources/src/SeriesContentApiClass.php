<?php

namespace Drupal\moj_resources;

use Drupal\node\NodeInterface;
use Drupal\Core\Entity\Query\QueryFactory;
use Drupal\Core\Entity\EntityTypeManagerInterface;

require_once('Utils.php');

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
  protected $nids = array();
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
  protected $node_storage;
  /**
   * Entity Query object
   *
   * @var Drupal\Core\Entity\Query\QueryFactory
   *
   * Instance of queryfactory
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
    $this->node_storage = $entityTypeManager->getStorage('node');
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
    $this->nids = $this->getSeriesContentNodeIds($seriesId, $number, $offset, $prison);
    $this->nodes = $this->loadNodesDetails($this->nids);

    $series = $this->decorateSeries($this->nodes);
    $series = $this->sortSeries($series, $sortOrder, $number);

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
    $this->nids = $this->getSeriesContentNodeIds($seriesId, null, null, $prison);
    $this->nodes = $this->loadNodesDetails($this->nids);
    $series = $this->decorateSeries($this->nodes);
    $series = $this->sortSeries($series, $sortOrder);
    $series = $this->getNextEpisodes($episodeId, $series, $number);

    return $series;
  }
  /**
   * decorateSeries
   *
   */
  private function decorateSeries($node)
  {
    $results = array_reduce($node, function ($acc, $curr) {
      $episodeId = 0;
      $season = $curr->field_moj_season->value;
      $episode = $curr->field_moj_episode->value;
      if (intval($season) > 0 && intval($episode) > 0) {
        $episodeId = ($season * 1000) + $episode;
      }
      $result = [];
      $result["episode_id"] = $episodeId;
      $result["last_updated"] = $curr->changed->value;
      $result["date"] = $curr->field_moj_date->value;
      $result["content_type"] = $curr->type->target_id;
      $result["title"] = $curr->title->value;
      $result["id"] = $curr->nid->value;
      $result["image"] = $curr->field_moj_thumbnail_image[0];
      $result["season"] = $curr->field_moj_season->value;
      $result["episode"] = $curr->field_moj_episode->value;
      $result["duration"] = $curr->field_moj_duration ? $curr->field_moj_duration->value : 0;
      $result["description"] = $curr->field_moj_description[0];
      $result["categories"] = $curr->field_moj_top_level_categories;
      if ($curr->field_moj_secondary_tags) {
        $result["secondary_tags"] = $curr->field_moj_secondary_tags;
      } else {
        $result["secondary_tags"] = $curr->field_moj_tags;
      }
      $result["prisons"] = $curr->field_moj_prisons;

      if ($result["content_type"] === 'moj_radio_item') {
        $result["media"] = $curr->field_moj_audio[0];
      } else {
        $result["media"] = $curr->field_video[0];
      }

      $acc[] = $result;

      return $acc;
    }, []);

    return $results;
  }
  /**
   * sortSeries
   *
   */


  private function sortSeries($series, $sortOrder, $number)
  {
    $noEpisode = array_filter($series, function($node) {
      return $node["episode_id"] === 0;
    });

    $hasEpisode = array_filter($series, function($node) {
      return $node["episode_id"] !== 0;
    });

    usort($hasEpisode, function ($a, $b) use ($sortOrder) {
      if ($a['episode_id'] == $b['episode_id']) {
        return 0;
      }

      if ($sortOrder == 'ASC') {
        return ($a['episode_id'] < $b['episode_id']) ? -1 : 1;
      }

      return ($a['episode_id'] < $b['episode_id']) ? 1 : -1;
    });

    usort($noEpisode, function ($a, $b) use ($sortOrder) {
      if ($sortOrder == 'ASC') {
        return ($a['date'] < $b['date']) ? -1 : 1;
      }

      return ($a['date'] < $b['date']) ? 1 : -1;
    });

    return array_slice(array_merge($noEpisode, $hasEpisode), 0, $number);
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
   * Get nids
   *
   * @return void
   */
  private function getSeriesContentNodeIds($seriesId, $number, $offset, $prison)
  {
    $results = $this->entity_query->get('node')
      ->condition('status', 1)
      ->accessCheck(false);

    $results->condition('field_moj_series', $seriesId);

    $results = getPrisonResults($prison, $results);

    if ($number) {
      $results->range($offset, $number);
    }

    return $results
      ->execute();
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
      $this->node_storage->loadMultiple($nids),
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
