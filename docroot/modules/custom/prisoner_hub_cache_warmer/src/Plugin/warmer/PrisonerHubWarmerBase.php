<?php

namespace Drupal\prisoner_hub_cache_warmer\Plugin\warmer;

use Drupal\Core\Form\SubformStateInterface;
use Drupal\Core\Site\Settings;
use Drupal\taxonomy\TermStorageInterface;
use Drupal\warmer\Plugin\WarmerPluginBase;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Promise\Utils;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for prison cache warmers.
 */
abstract class PrisonerHubWarmerBase extends WarmerPluginBase {

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
   * List of queued requests for asynchronous processing.
   */
  protected array $queuedAsynchronousRequests = [];

  /**
   * List of queued promises.
   *
   * These must complete before the $queuedAsynchronousRequests are issued,
   * as fulfilment of these promises may add to that queue.
   */
  protected array $queuedPromises = [];

  /**
   * List of prisons to not cache warm.
   *
   * @return string[]
   *   List of prison machine names.
   */
  abstract protected function getExcludedPrisons();

  /**
   * Warms a category page for a given page.
   *
   * @param string $prison
   *   Machine name of the prison.
   * @param string $uuid
   *   UUID of category.
   */
  abstract protected function warmCategoryPage(string $prison, string $uuid);

  /**
   * Issues an async request for the primary navigation.
   *
   * Returns a promise that when fulfilled, queues requests for each item in
   * the returned navigation.
   *
   * @param string $prison
   *   Machine name of the prison.
   *
   * @return \GuzzleHttp\Promise\PromiseInterface
   *   Promise for the async request.
   */
  abstract protected function warmPrimaryNavigation(string $prison);

  /**
   * Warms the contents of the home page for a given prison.
   *
   * @param string $prison
   *   Machine name of the prison.
   */
  abstract protected function warmPrisonHomePage(string $prison);

  /**
   * Warms a series page for a given page.
   *
   * @param string $prison
   *   Machine name of the prison.
   * @param string $uuid
   *   UUID of series.
   */
  abstract protected function warmSeriesPage(string $prison, string $uuid);

  /**
   * Warms a topic page for a given page.
   *
   * @param string $prison
   *   Machine name of the prison.
   * @param string $uuid
   *   UUID of topic.
   */
  abstract protected function warmTopicPage(string $prison, string $uuid);

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
      $this->warmPrisonHomePage($prison->machine_name->value);
      $this->warmPopularPages($prison->machine_name->value);
      $this->executeAllAsynchronousRequests();
      $warm_count++;
    }
    return $warm_count;
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
  protected function warmPopularPages(string $prison) {
    /** @var \Drupal\taxonomy\TermInterface $terms */
    $terms = $this->termStorage->loadMultiple($this->popularPages);

    foreach ($terms as $term) {
      match ($term->bundle()) {
        'moj_categories' => $this->warmCategoryPage($prison, $term->uuid()),
        'series' => $this->warmSeriesPage($prison, $term->uuid()),
        'topics' => $this->warmTopicPage($prison, $term->uuid()),
      };
      $this->queueAsynchronousRouterRequest($prison, "translate-path?path=tags/{$term->id()}");
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
  protected function warmJsonApiRequestAsync(string $request) {
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
        if (!in_array($prison->machine_name->value, $this->getExcludedPrisons())) {
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
    $this->queuedAsynchronousRequests[] = "$this->cacheWarmerEndpoint/en/jsonapi/prison/$prison/$path";
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
    // Ensure all queued promises are complete before processing the queued
    // requests. Outstanding promises may still be adding to the requests.
    Utils::all($this->queuedPromises)->wait();
    $this->queuedPromises = [];

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
