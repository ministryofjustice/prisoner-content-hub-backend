<?php

namespace Drupal\moj_resources;

use Drupal\node\NodeInterface;
use Drupal\Core\Entity\Query\QueryFactory;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

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
   * NodeStorage object
   *
   * @var Drupal\Core\Entity\EntityManagerInterface
   */
  protected $node_storage;

  /**
   * TermStorage object
   *
   * @var Drupal\Core\Entity\EntityManagerInterface
   */
  protected $term_storage;

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
    $this->node_storage = $entityTypeManager->getStorage('node');
    $this->term_storage = $entityTypeManager->getStorage('taxonomy_term');
    $this->entity_query = $entityQuery;
  }

  /**
   * API resource function
   *
   * @param [string] $lang
   * @return array
   */
  public function SeriesContentApiEndpoint($lang, $series_id, $number, $offset, $prison, $sort_order)
  {
    $this->lang = $lang;
    $this->nids = $this->getSeriesContentNodeIds($series_id, $number, $offset, $prison);
    $this->nodes = $this->loadNodesDetails($this->nids);

    $series = $this->decorateSeries($this->nodes);
    $series = $this->sortSeries($series, $sort_order);

    return $series;
  }

  /**
   * API resource function
   *
   * @param [string] $lang
   * @return array
   */
  public function SeriesNextEpisodeApiEndpoint($lang, $series_id, $number, $episode_id, $prison, $sort_order)
  {
    $this->lang = $lang;
    $this->nids = $this->getSeriesContentNodeIds($series_id, null, null, $prison);
    $this->nodes = $this->loadNodesDetails($this->nids);
    $series = $this->decorateSeries($this->nodes);
    $series = $this->sortSeries($series, $sort_order);
    $series = $this->getNextEpisodes($episode_id, $series, $number);

    return $series;
  }

  /**
   * decorateSeries
   *
   */
  private function decorateSeries($series_content)
  {
    return array_map(function ($curr) {
      $episode_id = ($curr->field_moj_season->value * 1000) + ($curr->field_moj_episode->value);
      $result = [];
      $result["episode_id"] = $episode_id;
      $result["content_type"] = $curr->type->target_id;
      $result["title"] = $curr->title->value;
      $result["id"] = $curr->nid->value;
      $result["image"] = $curr->field_moj_thumbnail_image[0];
      $result["season"] = $curr->field_moj_season->value;
      $result["episode"] = $curr->field_moj_episode->value;
      $result["duration"] = $curr->field_moj_duration ? $curr->field_moj_duration->value : 0;
      $result["description"] = $curr->field_moj_description[0];
      $result["categories"] = $curr->field_moj_top_level_categories;
      $result["prison_types"] = $curr->field_prison_types;

      if ($curr->field_moj_secondary_tags) {
        $result["secondary_tags"] = $curr->field_moj_secondary_tags;
      } else {
        $result["secondary_tags"] = $curr->field_moj_tags;
      }

      if ($result["content_type"] === 'moj_radio_item') {
        $result["media"] = $curr->field_moj_audio[0];
      } else {
        $result["media"] = $curr->field_video[0];
      }

      return $result;
    }, $series_content);
  }

  /**
   * sortSeries
   *
   */
  private function sortSeries(&$series, $sort_order)
  {
    usort($series, function ($a, $b) use ($sort_order) {
      if ($a['episode_id'] == $b['episode_id']) {
        return 0;
      }

      if ($sort_order == 'ASC') {
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
  private function getNextEpisodes($episode_id, $series, $number)
  {
    function indexOf($comp, $array)
    {
      foreach ($array as $key => $value) {
        if ($comp($value)) {
          return $key;
        }
      }
    }

    $episode_index = indexOf(function ($value) use ($episode_id) {
      return $value['episode_id'] == $episode_id;
    }, $series);

    if (is_null($episode_index)) {
      return array();
    }

    $episode_offset = $episode_index + 1;

    $episodes = array_slice($series, $episode_offset, $number);

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
  private function getSeriesContentNodeIds($series_id, $number_to_return, $offset, $prison_id)
  {
    $prison = $this->term_storage->load($prison_id);
    $prison_types = [];

    foreach ($prison->field_prison_types as $prison_type) {
      array_push($prison_types, $prison_type->target_id);
    }

    $series = $this->term_storage->load($series_id);
    $series_prison_types = [];

    foreach ($series->field_prison_types as $prison_type) {
      array_push($series_prison_types, $prison_type->target_id);
    }

    $has_no_matching_scopes = empty(array_intersect($prison_types, $series_prison_types));

    if (!empty($series_prison_types) && $has_no_matching_scopes) {
      throw new BadRequestHttpException(
        t('The Series does not have a matching scope for that prison'),
        null,
        400
      );
    }

    $query = $this->entity_query->get('node')
      ->condition('status', 1)
      ->accessCheck(false);

    if ($series_id !== 0) {
      $query->condition('field_moj_series', $series_id);
    }

    $query = filterByPrisonTypes($prison_types, $query);

    if ($number_to_return) {
      $query->range($offset, $number_to_return);
    }

    return $query->execute();
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
