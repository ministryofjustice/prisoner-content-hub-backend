<?php

namespace Drupal\prisoner_hub_cache_warmer\Plugin\warmer;

use Drupal\Core\Form\SubformStateInterface;
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
//    $instance->logger = $container->get('logger.channel.prisoner_hub_cache_warmer');

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
    foreach ($items as $item) {
      /** @var \Drupal\taxonomy\TermInterface $item */
      /* @todo make base path configurable */
      try {
        $this->httpClient->request('GET', "http://localhost:11001/jsonapi/prison/$item->machine_name/node/homepage");
      }
      catch (GuzzleException $e) {
//        Error::logException($this->logger, $e);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function buildIdsBatch($cursor) {
    // Load all prison categories - they are the first level in the tree.
    $prison_categories = $this->termStorage->loadTree('prisons', 0, 1);
    $prisons = [];
    foreach ($prison_categories as $category) {
      // Then load all prisons - they are the second level in the tree.
      $prisons_in_category = $this->termStorage->loadTree('prisons', $category->id(), 1);
      foreach ($prisons_in_category as $prison) {
        $prisons[] = $prison->machine_name;
      }
    }
    asort($prisons);

    // @todo slice by cursor.
    return $prisons;
  }

  /**
   * {@inheritdoc}
   */
  public function addMoreConfigurationFormElements(array $form, SubformStateInterface $form_state) {
    // @todo Implement addMoreConfigurationFormElements() method.
  }

}
