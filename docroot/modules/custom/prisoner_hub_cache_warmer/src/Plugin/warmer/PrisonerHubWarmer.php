<?php

namespace Drupal\prisoner_hub_cache_warmer\Plugin\warmer;

use Drupal\Core\Form\SubformStateInterface;
use Drupal\Core\Site\Settings;
use Drupal\Core\Utility\Error;
use Drupal\taxonomy\TermStorageInterface;
use Drupal\warmer\Plugin\WarmerPluginBase;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Warmer for each prison.
 *
 * @Warmer(
 *   id = "prisoner_hub",
 *   label = @Translation("Prisoner Hub"),
 *   description = @Translation("Makes page cache requests for each prison.")
 * )
 */
class PrisonerHubWarmer extends WarmerPluginBase {

  /**
   * Term storage.
   */
  protected TermStorageInterface $termStorage;

  /**
   * The HTTP client.
   */
  protected ClientInterface $httpClient;

  /**
   * The logger service.
   */
  protected LoggerInterface $logger;

  /**
   * Base address of the cache warmer endpoint.
   *
   * Should be the same as the base address used by the front end application.
   */
  protected string $cacheWarmerEndpoint;

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   *    Thrown if the entity type doesn't exist.
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   *    Thrown if the storage handler couldn't be loaded.
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);

    $instance->termStorage = $container->get('entity_type.manager')->getStorage('taxonomy_term');
    $instance->httpClient = $container->get('http_client');
    $instance->logger = $container->get('logger.channel.prisoner_hub_cache_warmer');
    $instance->cacheWarmerEndpoint = Settings::get('cache_warmer_endpoint');

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function loadMultiple(array $ids = []) {
    $prisons = [];

    foreach ($ids as $id) {
      $prison = taxonomy_machine_name_term_load($id, 'prisons');
      if ($prison) {
        $prisons[] = $prison;
      }
    }

    return $prisons;
  }

  /**
   * {@inheritdoc}
   */
  public function warmMultiple(array $items = []) {
    $warm_count = 0;
    foreach ($items as $prison) {
      /** @var \Drupal\taxonomy\TermInterface $prison */
      try {
        // Homepage.
        $this->httpClient->request('GET', "{$this->cacheWarmerEndpoint}/jsonapi/prison/{$prison->machine_name->value}/node/homepage?include=field_featured_tiles.field_moj_thumbnail_image%2Cfield_featured_tiles%2Cfield_large_update_tile%2Cfield_key_info_tiles%2Cfield_key_info_tiles.field_moj_thumbnail_image%2Cfield_large_update_tile.field_moj_thumbnail_image&page%5Blimit%5D=4&fields%5Bnode--field_featured_tiles%5D=drupal_internal__nid%2Ctitle%2Cfield_moj_thumbnail_image%2Cfield_summary%2Cfield_moj_series%2Cpath%2Ctype.meta.drupal_internal__target_id%2Cpublished_at&fields%5Bnode--field_key_info_tiles%5D=drupal_internal__nid%2Ctitle%2Cfield_moj_thumbnail_image%2Cfield_summary%2Cfield_moj_series%2Cpath%2Ctype.meta.drupal_internal__target_id%2Cpublished_at&fields%5Bfile--file%5D=drupal_internal__fid%2Cid%2Cimage_style_uri");
        // Primary Navigation.
        $this->httpClient->request('GET', "{$this->cacheWarmerEndpoint}/jsonapi/prison/{$prison->machine_name->value}/primary_navigation?fields%5Bmenu_link_content--menu_link_content%5D=id%2Ctitle%2Curl");
        // Urgent Banners.
        $this->httpClient->request('GET', "{$this->cacheWarmerEndpoint}/jsonapi/prison/{$prison->machine_name->value}/node/urgent_banner?include=field_more_info_page&fields%5Bnode--urgent_banner%5D=drupal_internal__nid%2Ctitle%2Ccreated%2Cchanged%2Cfield_more_info_page%2Cunpublish_on");
        // Updates.
        // @todo the updates request has unpredictable date based filtering.
        // We can't pre-warm this until LNP-1138 is implemented.
        // $this->httpClient->request('GET', "{$this->cacheWarmerEndpoint}/jsonapi/prison/{$prison->machine_name->value}/node?filter%5B6%5D%5Bcondition%5D%5Bpath%5D=published_at&filter%5B6%5D%5Bcondition%5D%5Bvalue%5D=1723192942&filter%5B6%5D%5Bcondition%5D%5Boperator%5D=%3E%3D&filter%5B6%5D%5Bcondition%5D%5BmemberOf%5D=series_group&filter%5Bparent_or_group%5D%5Bgroup%5D%5Bconjunction%5D=OR&filter%5Bcategories_group%5D%5Bgroup%5D%5Bconjunction%5D=AND&filter%5Bcategories_group%5D%5Bgroup%5D%5BmemberOf%5D=parent_or_group&filter%5Bseries_group%5D%5Bgroup%5D%5Bconjunction%5D=AND&filter%5Bseries_group%5D%5Bgroup%5D%5BmemberOf%5D=parent_or_group&filter%5Bfield_moj_top_level_categories.field_is_homepage_updates%5D%5Bcondition%5D%5Bpath%5D=field_moj_top_level_categories.field_is_homepage_updates&filter%5Bfield_moj_top_level_categories.field_is_homepage_updates%5D%5Bcondition%5D%5Bvalue%5D=1&filter%5Bfield_moj_top_level_categories.field_is_homepage_updates%5D%5Bcondition%5D%5BmemberOf%5D=categories_group&filter%5Bpublished_at%5D%5Bcondition%5D%5Bpath%5D=published_at&filter%5Bpublished_at%5D%5Bcondition%5D%5Bvalue%5D=1723192942&filter%5Bpublished_at%5D%5Bcondition%5D%5Boperator%5D=%3E%3D&filter%5Bpublished_at%5D%5Bcondition%5D%5BmemberOf%5D=categories_group&filter%5Bfield_moj_series.field_is_homepage_updates%5D%5Bcondition%5D%5Bpath%5D=field_moj_series.field_is_homepage_updates&filter%5Bfield_moj_series.field_is_homepage_updates%5D%5Bcondition%5D%5Bvalue%5D=1&filter%5Bfield_moj_series.field_is_homepage_updates%5D%5Bcondition%5D%5BmemberOf%5D=series_group&include=field_moj_thumbnail_image&sort=-published_at%2Ccreated&fields%5Bnode--page%5D=drupal_internal__nid%2Ctitle%2Cfield_moj_thumbnail_image%2Cfield_summary%2Cfield_moj_series%2Cpath%2Ctype.meta.drupal_internal__target_id%2Cpublished_at&fields%5Bnode--moj_video_item%5D=drupal_internal__nid%2Ctitle%2Cfield_moj_thumbnail_image%2Cfield_summary%2Cfield_moj_series%2Cpath%2Ctype.meta.drupal_internal__target_id%2Cpublished_at&fields%5Bnode--moj_radio_item%5D=drupal_internal__nid%2Ctitle%2Cfield_moj_thumbnail_image%2Cfield_summary%2Cfield_moj_series%2Cpath%2Ctype.meta.drupal_internal__target_id%2Cpublished_at&fields%5Bnode--moj_pdf_item%5D=drupal_internal__nid%2Ctitle%2Cfield_moj_thumbnail_image%2Cfield_summary%2Cfield_moj_series%2Cpath%2Ctype.meta.drupal_internal__target_id%2Cpublished_at&fields%5Bfile--file%5D=drupal_internal__fid%2Cid%2Cimage_style_uri&page[offset]=0&page[limit]=5");
        // Recently Added.
        $this->httpClient->request('GET', "{$this->cacheWarmerEndpoint}/jsonapi/prison/{$prison->machine_name->value}/recently-added?include=field_moj_thumbnail_image&sort=-published_at%2Ccreated&fields%5Bnode--page%5D=drupal_internal__nid%2Ctitle%2Cfield_moj_thumbnail_image%2Cfield_summary%2Cfield_moj_series%2Cpath%2Ctype.meta.drupal_internal__target_id%2Cpublished_at&fields%5Bnode--moj_video_item%5D=drupal_internal__nid%2Ctitle%2Cfield_moj_thumbnail_image%2Cfield_summary%2Cfield_moj_series%2Cpath%2Ctype.meta.drupal_internal__target_id%2Cpublished_at&fields%5Bnode--moj_radio_item%5D=drupal_internal__nid%2Ctitle%2Cfield_moj_thumbnail_image%2Cfield_summary%2Cfield_moj_series%2Cpath%2Ctype.meta.drupal_internal__target_id%2Cpublished_at&fields%5Bnode--moj_pdf_item%5D=drupal_internal__nid%2Ctitle%2Cfield_moj_thumbnail_image%2Cfield_summary%2Cfield_moj_series%2Cpath%2Ctype.meta.drupal_internal__target_id%2Cpublished_at&fields%5Bfile--file%5D=drupal_internal__fid%2Cid%2Cimage_style_uri&page[offset]=0&page[limit]=8");
        // Explore the Hub.
        $this->httpClient->request('GET', "{$this->cacheWarmerEndpoint}/jsonapi/prison/{$prison->machine_name->value}/explore/node?include=field_moj_thumbnail_image&page%5Blimit%5D=4&fields%5Bnode--page%5D=drupal_internal__nid%2Ctitle%2Cfield_moj_thumbnail_image%2Cfield_summary%2Cfield_moj_series%2Cpath%2Ctype.meta.drupal_internal__target_id%2Cpublished_at&fields%5Bnode--moj_video_item%5D=drupal_internal__nid%2Ctitle%2Cfield_moj_thumbnail_image%2Cfield_summary%2Cfield_moj_series%2Cpath%2Ctype.meta.drupal_internal__target_id%2Cpublished_at&fields%5Bnode--moj_radio_item%5D=drupal_internal__nid%2Ctitle%2Cfield_moj_thumbnail_image%2Cfield_summary%2Cfield_moj_series%2Cpath%2Ctype.meta.drupal_internal__target_id%2Cpublished_at&fields%5Bnode--moj_pdf_item%5D=drupal_internal__nid%2Ctitle%2Cfield_moj_thumbnail_image%2Cfield_summary%2Cfield_moj_series%2Cpath%2Ctype.meta.drupal_internal__target_id%2Cpublished_at");

        $warm_count++;
      }
      catch (GuzzleException $e) {
        Error::logException($this->logger, $e);
      }
    }
    return $warm_count;
  }

  /**
   * {@inheritdoc}
   */
  public function buildIdsBatch($cursor) {
    // Load all prison categories - they are the first level in the tree.
    $prison_categories = $this->termStorage->loadTree('prisons', 0, 1, TRUE);
    $all_prisons = [];
    foreach ($prison_categories as $category) {
      // Then load all prisons - they are the second level in the tree.
      $prisons_in_category = $this->termStorage->loadTree('prisons', $category->id(), 1, TRUE);
      foreach ($prisons_in_category as $prison) {
        $all_prisons[] = $prison->machine_name->value;
      }
    }
    sort($all_prisons);

    $cursor_position = is_null($cursor) ? -1 : array_search($cursor, $all_prisons);
    if ($cursor_position === FALSE) {
      return [];
    }
    return array_slice($all_prisons, $cursor_position + 1, (int) $this->getBatchSize());
  }

  /**
   * {@inheritdoc}
   */
  public function addMoreConfigurationFormElements(array $form, SubformStateInterface $form_state): array {
    // @todo Implement addMoreConfigurationFormElements() method.
    return [];
  }

}
