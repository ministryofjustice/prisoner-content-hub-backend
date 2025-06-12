<?php

namespace Drupal\prisoner_hub_cache_warmer\Plugin\warmer;

use Drupal\Core\Form\SubformStateInterface;
use Drupal\Core\Site\Settings;
use Drupal\Core\Utility\Error;
use Drupal\taxonomy\TermStorageInterface;
use Drupal\warmer\Plugin\WarmerPluginBase;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Promise\Utils;
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
   * Some data retrieved whilst warming is useful for future warmings.
   *
   * For example, the response from warming the primary navigation will
   * help work out the content to warm in anticipation of users clicking
   * on items in the primary navigation.
   *
   * For that reason, where appropriate, we cache responses to warming
   * requests.
   */
  protected array $cacheResponses = [];

  /**
   * These are the most popular pages that are not in the primary nav.
   *
   * These are based on a snapshot from GA on 14/5/25, and are ordered by
   * descending popularity.
   *
   * Each array entry is a term_id.
   */
  protected array $popularPages = [
    785,
    933,
    647,
    978,
    690,
    1505,
    787,
    1817,
    965,
    1295,
    1004,
    1016,
    1189,
    1663,
    1051,
    865,
    1859,
    660,
  ];

  /**
   * List of prisons to not cache warm.
   */
  protected array $excludePrisons = ['thestudio'];

  /**
   * List of queued requests for asynchronous processing.
   */
  protected array $queuedAsynchronousRequests = [];

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
      $this->cacheResponses[$prison->machine_name->value] = [];
      $this->warmPrisonHomePage($prison->machine_name->value);
      $this->warmPrimaryNavigationContent($prison->machine_name->value);
      $this->warmPopularPages($prison->machine_name->value);
      $this->executeAllAsynchronousRequests();
      $warm_count++;
    }
    return $warm_count;
  }

  /**
   * Warms the contents of the home page for a given prison.
   *
   * @param string $prison
   *   Machine name of the prison.
   */
  private function warmPrisonHomePage(string $prison) {
    // Homepage.
    $this->queueAsynchronousJsonApiRequest($prison, "node/homepage?include=field_featured_tiles.field_moj_thumbnail_image%2Cfield_featured_tiles%2Cfield_large_update_tile%2Cfield_key_info_tiles%2Cfield_key_info_tiles.field_moj_thumbnail_image%2Cfield_large_update_tile.field_moj_thumbnail_image&page%5Blimit%5D=4&fields%5Bnode--field_featured_tiles%5D=drupal_internal__nid%2Ctitle%2Cfield_moj_thumbnail_image%2Cfield_summary%2Cfield_moj_series%2Cpath%2Ctype.meta.drupal_internal__target_id%2Cpublished_at&fields%5Bnode--field_key_info_tiles%5D=drupal_internal__nid%2Ctitle%2Cfield_moj_thumbnail_image%2Cfield_summary%2Cfield_moj_series%2Cpath%2Ctype.meta.drupal_internal__target_id%2Cpublished_at&fields%5Bfile--file%5D=drupal_internal__fid%2Cid%2Cimage_style_uri");
    // Primary Navigation. Not asynchronous as we need the response to inform
    // later requests.
    $this->warmJsonApiRequest($prison, "primary_navigation?fields%5Bmenu_link_content--menu_link_content%5D=id%2Ctitle%2Curl", "primary navigation");
    // Urgent Banners.
    $this->queueAsynchronousJsonApiRequest($prison, "node/urgent_banner?include=field_more_info_page&fields%5Bnode--urgent_banner%5D=drupal_internal__nid%2Ctitle%2Ccreated%2Cchanged%2Cfield_more_info_page%2Cunpublish_on");
    // Updates.
    try {
      // The updates request restricts nodes to those published after midnight
      // 90 days ago.
      $earliest_published_date = ((new \DateTimeImmutable())
        ->sub(\DateInterval::createFromDateString('90 day')))
        ->setTime(0, 0)
        ->getTimestamp();
      $this->queueAsynchronousJsonApiRequest($prison, "node?filter%5B6%5D%5Bcondition%5D%5Bpath%5D=published_at&filter%5B6%5D%5Bcondition%5D%5Bvalue%5D=$earliest_published_date&filter%5B6%5D%5Bcondition%5D%5Boperator%5D=%3E%3D&filter%5B6%5D%5Bcondition%5D%5BmemberOf%5D=series_group&filter%5Bparent_or_group%5D%5Bgroup%5D%5Bconjunction%5D=OR&filter%5Bcategories_group%5D%5Bgroup%5D%5Bconjunction%5D=AND&filter%5Bcategories_group%5D%5Bgroup%5D%5BmemberOf%5D=parent_or_group&filter%5Bseries_group%5D%5Bgroup%5D%5Bconjunction%5D=AND&filter%5Bseries_group%5D%5Bgroup%5D%5BmemberOf%5D=parent_or_group&filter%5Bfield_moj_top_level_categories.field_is_homepage_updates%5D%5Bcondition%5D%5Bpath%5D=field_moj_top_level_categories.field_is_homepage_updates&filter%5Bfield_moj_top_level_categories.field_is_homepage_updates%5D%5Bcondition%5D%5Bvalue%5D=1&filter%5Bfield_moj_top_level_categories.field_is_homepage_updates%5D%5Bcondition%5D%5BmemberOf%5D=categories_group&filter%5Bpublished_at%5D%5Bcondition%5D%5Bpath%5D=published_at&filter%5Bpublished_at%5D%5Bcondition%5D%5Bvalue%5D=$earliest_published_date&filter%5Bpublished_at%5D%5Bcondition%5D%5Boperator%5D=%3E%3D&filter%5Bpublished_at%5D%5Bcondition%5D%5BmemberOf%5D=categories_group&filter%5Bfield_moj_series.field_is_homepage_updates%5D%5Bcondition%5D%5Bpath%5D=field_moj_series.field_is_homepage_updates&filter%5Bfield_moj_series.field_is_homepage_updates%5D%5Bcondition%5D%5Bvalue%5D=1&filter%5Bfield_moj_series.field_is_homepage_updates%5D%5Bcondition%5D%5BmemberOf%5D=series_group&include=field_moj_thumbnail_image&sort=-published_at%2Ccreated&fields%5Bnode--page%5D=drupal_internal__nid%2Ctitle%2Cfield_moj_thumbnail_image%2Cfield_summary%2Cfield_moj_series%2Cpath%2Ctype.meta.drupal_internal__target_id%2Cpublished_at&fields%5Bnode--moj_video_item%5D=drupal_internal__nid%2Ctitle%2Cfield_moj_thumbnail_image%2Cfield_summary%2Cfield_moj_series%2Cpath%2Ctype.meta.drupal_internal__target_id%2Cpublished_at&fields%5Bnode--moj_radio_item%5D=drupal_internal__nid%2Ctitle%2Cfield_moj_thumbnail_image%2Cfield_summary%2Cfield_moj_series%2Cpath%2Ctype.meta.drupal_internal__target_id%2Cpublished_at&fields%5Bnode--moj_pdf_item%5D=drupal_internal__nid%2Ctitle%2Cfield_moj_thumbnail_image%2Cfield_summary%2Cfield_moj_series%2Cpath%2Ctype.meta.drupal_internal__target_id%2Cpublished_at&fields%5Bfile--file%5D=drupal_internal__fid%2Cid%2Cimage_style_uri&page[offset]=0&page[limit]=5");
    }
    catch (\DateMalformedStringException | \DateInvalidOperationException $e) {
      Error::logException($this->logger, $e);
    }
    // Recently Added.
    $this->queueAsynchronousJsonApiRequest($prison, "recently-added?include=field_moj_thumbnail_image&sort=-published_at%2Ccreated&fields%5Bnode--page%5D=drupal_internal__nid%2Ctitle%2Cfield_moj_thumbnail_image%2Cfield_summary%2Cfield_moj_series%2Cpath%2Ctype.meta.drupal_internal__target_id%2Cpublished_at&fields%5Bnode--moj_video_item%5D=drupal_internal__nid%2Ctitle%2Cfield_moj_thumbnail_image%2Cfield_summary%2Cfield_moj_series%2Cpath%2Ctype.meta.drupal_internal__target_id%2Cpublished_at&fields%5Bnode--moj_radio_item%5D=drupal_internal__nid%2Ctitle%2Cfield_moj_thumbnail_image%2Cfield_summary%2Cfield_moj_series%2Cpath%2Ctype.meta.drupal_internal__target_id%2Cpublished_at&fields%5Bnode--moj_pdf_item%5D=drupal_internal__nid%2Ctitle%2Cfield_moj_thumbnail_image%2Cfield_summary%2Cfield_moj_series%2Cpath%2Ctype.meta.drupal_internal__target_id%2Cpublished_at&fields%5Bfile--file%5D=drupal_internal__fid%2Cid%2Cimage_style_uri&page[offset]=0&page[limit]=8");
    // Explore the Hub.
    $this->queueAsynchronousJsonApiRequest($prison, "explore/node?include=field_moj_thumbnail_image&page%5Blimit%5D=4&fields%5Bnode--page%5D=drupal_internal__nid%2Ctitle%2Cfield_moj_thumbnail_image%2Cfield_summary%2Cfield_moj_series%2Cpath%2Ctype.meta.drupal_internal__target_id%2Cpublished_at&fields%5Bnode--moj_video_item%5D=drupal_internal__nid%2Ctitle%2Cfield_moj_thumbnail_image%2Cfield_summary%2Cfield_moj_series%2Cpath%2Ctype.meta.drupal_internal__target_id%2Cpublished_at&fields%5Bnode--moj_radio_item%5D=drupal_internal__nid%2Ctitle%2Cfield_moj_thumbnail_image%2Cfield_summary%2Cfield_moj_series%2Cpath%2Ctype.meta.drupal_internal__target_id%2Cpublished_at&fields%5Bnode--moj_pdf_item%5D=drupal_internal__nid%2Ctitle%2Cfield_moj_thumbnail_image%2Cfield_summary%2Cfield_moj_series%2Cpath%2Ctype.meta.drupal_internal__target_id%2Cpublished_at");
    // Topics.
    $this->queueAsynchronousJsonApiRequest($prison, "taxonomy_term?filter%5Bvid.meta.drupal_internal__target_id%5D=topics&page%5Blimit%5D=100&sort=name&fields%5Btaxonomy_term--topics%5D=drupal_internal__tid%2Cname");
  }

  /**
   * Warms the items in the primary navigation for a given prison.
   *
   * @param string $prison
   *   Machine name of the prison for which we are making the call.
   */
  private function warmPrimaryNavigationContent(string $prison) {
    if (!isset($this->cacheResponses[$prison]['primary navigation']->data)) {
      return;
    }
    $tids = [];
    foreach ($this->cacheResponses[$prison]['primary navigation']->data as $menu_item) {
      if (!isset($menu_item->attributes->url)) {
        continue;
      }
      $matches = [];
      if (preg_match("/tags\/(\d+)/", $menu_item->attributes->url, $matches)) {
        $tids[] = $matches[1];
      }
    }
    $terms = $this->termStorage->loadMultiple($tids);
    foreach ($terms as $term) {
      if ($term->bundle() != 'moj_categories') {
        continue;
      }
      $this->warmCategoryPage($prison, $term->uuid());
    }
    foreach ($tids as $tid) {
      $this->queueAsynchronousRouterRequest($prison, "translate-path?path=tags/$tid");
    }

  }

  /**
   * Warms the globally popular pages for a given prison.
   *
   * At the time of writing, the most popular pages all correspond to taxonomy
   * terms, so pages that are nodes are not handled here.
   *
   * @param string $prison
   *   Machine name of the prison for which we are warming the page.
   */
  private function warmPopularPages(string $prison) {
    /** @var \Drupal\taxonomy\TermInterface $terms */
    $terms = $this->termStorage->loadMultiple($this->popularPages);

    foreach ($terms as $term) {
      match ($term->bundle()) {
        'moj_categories' => $this->warmCategoryPage($prison, $term->uuid()),
        'series' => $this->warmSeriesPage($prison, $term->uuid()),
        'topics' => $this->warmTopicPage($prison, $term->uuid()),
      };
      $this->queueAsynchronousRouterRequest($prison, "translate-path?path=tags/$term->id()");
    }
  }

  /**
   * Warms a category page for a given page.
   *
   * @param string $prison
   *   Machine name of the prison.
   * @param string $uuid
   *   UUID of category.
   */
  private function warmCategoryPage(string $prison, string $uuid) {
    $this->queueAsynchronousJsonApiRequest($prison, "node?filter%5Bfield_moj_top_level_categories.id%5D=$uuid&include=field_moj_thumbnail_image&sort=-created&fields%5Bnode--page%5D=drupal_internal__nid%2Ctitle%2Cfield_summary%2Cfield_moj_thumbnail_image%2Cpath%2Cpublished_at&fields%5Bnode--moj_video_item%5D=drupal_internal__nid%2Ctitle%2Cfield_summary%2Cfield_moj_thumbnail_image%2Cpath%2Cpublished_at&fields%5Bnode--moj_radio_item%5D=drupal_internal__nid%2Ctitle%2Cfield_summary%2Cfield_moj_thumbnail_image%2Cpath%2Cpublished_at&fields%5Bmoj_pdf_item%5D=drupal_internal__nid%2Ctitle%2Cfield_summary%2Cfield_moj_thumbnail_image%2Cpath%2Cpublished_at&page[offset]=0&page[limit]=40");
    $this->queueAsynchronousJsonApiRequest($prison, "taxonomy_term/moj_categories/$uuid/sub_terms?include=field_moj_thumbnail_image&fields%5Btaxonomy_term--series%5D=type%2Cdrupal_internal__tid%2Cname%2Cfield_moj_thumbnail_image%2Cpath%2Ccontent_updated%2Cchild_term_count%2Cpublished_at&fields%5Btaxonomy_term--moj_categories%5D=type%2Cdrupal_internal__tid%2Cname%2Cfield_moj_thumbnail_image%2Cpath%2Ccontent_updated%2Cchild_term_count%2Cpublished_at&page[offset]=0&page[limit]=40");
    $this->queueAsynchronousJsonApiRequest($prison, "taxonomy_term/moj_categories/$uuid?include=field_featured_tiles%2Cfield_featured_tiles.field_moj_thumbnail_image&fields%5Bnode--page%5D=drupal_internal__nid%2Cdrupal_internal__tid%2Ctitle%2Cfield_moj_thumbnail_image%2Cfield_topics%2Cpath%2Cfield_exclude_feedback%2Cpublished_at&fields%5Bnode--moj_video_item%5D=drupal_internal__nid%2Cdrupal_internal__tid%2Ctitle%2Cfield_moj_thumbnail_image%2Cfield_topics%2Cpath%2Cfield_exclude_feedback%2Cpublished_at&fields%5Bnode--moj_radio_item%5D=drupal_internal__nid%2Cdrupal_internal__tid%2Ctitle%2Cfield_moj_thumbnail_image%2Cfield_topics%2Cpath%2Cfield_exclude_feedback%2Cpublished_at&fields%5Bmoj_pdf_item%5D=drupal_internal__nid%2Cdrupal_internal__tid%2Ctitle%2Cfield_moj_thumbnail_image%2Cfield_topics%2Cpath%2Cfield_exclude_feedback%2Cpublished_at&fields%5Btaxonomy_term_series%5D=drupal_internal__nid%2Cdrupal_internal__tid%2Ctitle%2Cfield_moj_thumbnail_image%2Cfield_topics%2Cpath%2Cfield_exclude_feedback%2Cpublished_at&fields%5Btaxonomy_term--moj_categories%5D=name%2Cdescription%2Cfield_exclude_feedback%2Cfield_featured_tiles%2Cbreadcrumbs%2Cchild_term_count");
  }

  /**
   * Warms a series page for a given page.
   *
   * @param string $prison
   *   Machine name of the prison.
   * @param string $uuid
   *   UUID of series.
   */
  private function warmSeriesPage(string $prison, string $uuid) {
    $this->warmJsonApiRequest($prison, "node?filter%5Bfield_moj_series.id%5D=$uuid&include=field_moj_thumbnail_image%2Cfield_moj_series.field_moj_thumbnail_image&sort=series_sort_value%2Ccreated&fields%5Bnode--page%5D=drupal_internal__nid%2Ctitle%2Cfield_summary%2Cfield_moj_thumbnail_image%2Cfield_moj_series%2Cpath%2Cpublished_at&fields%5Bnode--moj_video_item%5D=drupal_internal__nid%2Ctitle%2Cfield_summary%2Cfield_moj_thumbnail_image%2Cfield_moj_series%2Cpath%2Cpublished_at&fields%5Bnode--moj_radio_item%5D=drupal_internal__nid%2Ctitle%2Cfield_summary%2Cfield_moj_thumbnail_image%2Cfield_moj_series%2Cpath%2Cpublished_at&fields%5Bnode--moj_pdf_item%5D=drupal_internal__nid%2Ctitle%2Cfield_summary%2Cfield_moj_thumbnail_image%2Cfield_moj_series%2Cpath%2Cpublished_at&fields%5Bfile--file%5D=image_style_uri&fields%5Btaxonomy_term--series%5D=name%2Cdescription%2Cdrupal_internal__tid%2Cfield_moj_thumbnail_image%2Cpath%2Cfield_exclude_feedback%2Cbreadcrumbs&page[offset]=0&page[limit]=40");
  }

  /**
   * Warms a topic page for a given page.
   *
   * @param string $prison
   *   Machine name of the prison.
   * @param string $uuid
   *   UUID of topic.
   */
  private function warmTopicPage(string $prison, string $uuid) {
    $this->warmJsonApiRequest($prison, "node?filter%5Bfield_topics.id%5D=$uuid&include=field_moj_thumbnail_image%2Cfield_topics.field_moj_thumbnail_image&sort=-created&fields%5Bnode--page%5D=drupal_internal__nid%2Ctitle%2Cfield_summary%2Cfield_moj_thumbnail_image%2Cfield_topics%2Cpath%2Cpublished_at&fields%5Bnode--moj_video_item%5D=drupal_internal__nid%2Ctitle%2Cfield_summary%2Cfield_moj_thumbnail_image%2Cfield_topics%2Cpath%2Cpublished_at&fields%5Bnode--moj_radio_item%5D=drupal_internal__nid%2Ctitle%2Cfield_summary%2Cfield_moj_thumbnail_image%2Cfield_topics%2Cpath%2Cpublished_at&fields%5Bnode--moj_pdf_item%5D=drupal_internal__nid%2Ctitle%2Cfield_summary%2Cfield_moj_thumbnail_image%2Cfield_topics%2Cpath%2Cpublished_at&fields%5Bfile--file%5D=image_style_uri&fields%5Btaxonomy_term--topics%5D=name%2Cdescription%2Cdrupal_internal__tid%2Cfield_moj_thumbnail_image%2Cpath%2Cfield_exclude_feedback%2Cbreadcrumbs&page[offset]=0&page[limit]=40");
  }

  /**
   * Makes a call to JSON:API to warm the cache.
   *
   * @param string $prison
   *   Machine name of the prison for which we are making the call.
   * @param string $request
   *   Part of the request following the prison name.
   * @param string $cacheKey
   *   Key under which to cache the response data.
   *   Omit or set empty string to not cache the response.
   */
  private function warmJsonApiRequest(string $prison, string $request, string $cacheKey = '') {
    try {
      $response = $this->httpClient->request('GET', "$this->cacheWarmerEndpoint/jsonapi/prison/$prison/$request");
      $body = $response->getBody();
      if ($cacheKey) {
        $this->cacheResponses[$prison][$cacheKey] = json_decode($body);
      }
    }
    catch (GuzzleException $e) {
      Error::logException($this->logger, $e);
    }
  }

  /**
   * Initiates an asynchronous call to JSON:API to warm the cache.
   *
   * @param string $request
   *   Part of the request following the prison name.
   *
   * @return \GuzzleHttp\Promise\PromiseInterface
   *   Promise of the async request.
   */
  private function warmJsonApiRequestAsync(string $request) {
    return $this->httpClient->requestAsync('GET', $request);
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
        if (!in_array($prison->machine_name->value, $this->excludePrisons)) {
          $all_prisons[] = $prison->machine_name->value;
        }
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

  /**
   * {@inheritdoc}
   *
   * Set the batchSize to only warm one prison at a time.
   */
  public function getConfiguration() {
    $configuration = parent::getConfiguration();
    $configuration['batchSize'] = 1;
    return $configuration;
  }

  /**
   * Queues an asynchronous JSON:API request for later processing.
   *
   * @param string $prison
   *   Machine name of the prison for this request.
   * @param string $path
   *   Path of the request to queue.
   */
  protected function queueAsynchronousJsonApiRequest(string $prison, string $path) {
    $this->queuedAsynchronousRequests[] = [$prison, "$this->cacheWarmerEndpoint/jsonapi/prison/$prison/$path"];
  }

  /**
   * Queues an asynchronous router request for later processing.
   *
   * @param string $prison
   *   Machine name of the prison for this request.
   * @param string $path
   *   Path of the request to queue.
   */
  protected function queueAsynchronousRouterRequest(string $prison, string $path) {
    $this->queuedAsynchronousRequests[] = "$this->cacheWarmerEndpoint/router/prison/$prison/$path";
  }

  /**
   * Execute all outstanding asynchronous requests in batches.
   */
  protected function executeAllAsynchronousRequests() {
    $maxConcurrentRequests = 20;
    $batchedRequests = array_chunk($this->queuedAsynchronousRequests, $maxConcurrentRequests);
    foreach ($batchedRequests as $batch) {
      $promises = [];
      foreach ($batch as $request) {
        $promises[] = $this->warmJsonApiRequestAsync($request);
      }
      Utils::all($promises)->wait();
    }
    $this->queuedAsynchronousRequests = [];
  }

}
