<?php

namespace Drupal\moj_resources;

use Drupal\image\Entity\ImageStyle;
use Drupal\node\NodeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\taxonomy\Entity\Term;

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
   * Class Constructor
   *
   * @param EntityTypeManagerInterface $entityTypeManager
   */
  public function __construct(
    EntityTypeManagerInterface $entityTypeManager
  ) {
    $this->node_storage = $entityTypeManager->getStorage('node');
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

    return $series;
  }
  /**
   * API resource function
   *
   * @param [string] $lang
   * @return array
   */
  public function SeriesNextEpisodeApiEndpoint($lang, $seriesId, $number, $episodeId, $prison)
  {
    $this->lang = $lang;
    $this->nids = $this->getSeriesContentNodeIds($seriesId, null, null, $prison);
    $this->nodes = $this->loadNodesDetails($this->nids);
    $series = $this->decorateSeries($this->nodes);
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
      $result["date"] = $curr->field_release_date->value;
      $result["content_type"] = $curr->type->target_id;
      $result["title"] = $curr->title->value;
      $result["id"] = $curr->nid->value;
      $file = $curr->get('field_moj_thumbnail_image')->referencedEntities()[0];
      $result["image"] = [];
      $result["image"]['url'] =  file_create_url(ImageStyle::load('tile_small')->buildUri($file->getFileUri()));
      $result["image"]['alt'] =  $curr->field_moj_thumbnail_image->alt;
      $result["image"]['title'] =  $curr->field_moj_thumbnail_image->title;

      $result["season"] = $curr->field_moj_season->value;
      $result["episode"] = $curr->field_moj_episode->value;
      $result["description"] = $curr->field_moj_description[0];
      $result["categories"] = $curr->field_moj_top_level_categories;
      $result["secondary_tags"] = $curr->field_moj_secondary_tags;
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
    $results = $this->node_storage->getQuery()
      ->condition('status', 1)
      ->accessCheck(false);
    $results->condition('field_moj_series', $seriesId);

    $seriesTerm = Term::load($seriesId);
    $sortByFieldValue = $seriesTerm->get('field_sort_by')->getValue();
    $sortByFieldValue = empty($sortByFieldValue) ? NULL : $sortByFieldValue[0]['value'];

    switch ($sortByFieldValue) {
      case 'season_and_episode_asc':
        $sortFields = ['field_moj_season', 'field_moj_episode'];
        $sortDirection = 'ASC';
        break;

      case 'release_date_desc':
        $sortFields = ['field_release_date'];
        $sortDirection = 'DESC';
        break;

      case 'release_date_asc':
        $sortFields = ['field_release_date'];
        $sortDirection = 'ASC';
        break;

      case 'season_and_episode_desc':
      default:
        $sortFields = ['field_moj_season', 'field_moj_episode'];
        $sortDirection = 'DESC';
    }

    foreach ($sortFields as $sortField) {
      $results->sort($sortField, $sortDirection);
    }

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
