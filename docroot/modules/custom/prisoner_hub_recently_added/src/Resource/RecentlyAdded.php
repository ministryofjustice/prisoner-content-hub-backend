<?php

namespace Drupal\prisoner_hub_recently_added\Resource;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Http\Exception\CacheableBadRequestHttpException;
use Drupal\Core\Render\RenderContext;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\jsonapi\Query\OffsetPage;
use Drupal\jsonapi\ResourceResponse;
use Drupal\jsonapi\ResourceType\ResourceType;
use Drupal\jsonapi_resources\Resource\EntityResourceBase;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Route;

/**
 * Processes a request for recently added content/series.
 *
 * For more info on how this class works, see examples in
 * jsonapi_resources/tests/modules/jsonapi_resources_test/src/Resource.
 *
 * @internal
 */
class RecentlyAdded extends EntityResourceBase implements ContainerInjectionInterface {

  /**
   * Array of content types to use in the resource.
   *
   * @var array|string[]
   */
  public static array $contentTypes = [
    'moj_radio_item',
    'page',
    'link',
    'moj_pdf_item',
    'moj_video_item',
  ];

  /**
   * RecentlyAdded resource constructor.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $routeMatch
   *   Current route match.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   Renderer.
   */
  public function __construct(protected RouteMatchInterface $routeMatch, protected RendererInterface $renderer) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('current_route_match'),
      $container->get('renderer'),
    );
  }

  /**
   * Process the resource request.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @return \Drupal\jsonapi\ResourceResponse
   *   The response.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function process(Request $request): ResourceResponse {
    $cacheability = new CacheableMetadata();
    $cacheability->addCacheContexts(['url.path']);

    // This is a custom cache tag that is invalidated in
    // prisoner_hub_recently_added_node_update().
    $cache_tag = 'prisoner_hub_recently_added';
    $prison = $this->routeMatch->getParameter('prison');
    if ($prison) {
      $cache_tag .= ':' . $prison->get('machine_name')->getString();
    }
    $cacheability->addCacheTags([$cache_tag]);

    $pagination = $this->getPagination($request);
    if ($pagination->getOffset() != 0) {
      throw new CacheableBadRequestHttpException($cacheability, 'This resource does not currently support an offset greater than 0.');
    }
    if ($pagination->getSize() <= 0) {
      throw new CacheableBadRequestHttpException($cacheability, 'The page size needs to be a positive integer.');
    }

    // Multidimensional array, each item containing a 'published_at' timestamp
    // and a 'entity' key.
    $priority_timestamps_and_entities = [];

    // Load in series and content entities separately.
    // We are unable to run everything in one db query, due to the different
    // rules for content in a series.
    // First half of the content (rounding up) should be priority content.
    $priority_count = (int) ceil($pagination->getSize() / 2);
    $this->loadSeriesEntities($priority_timestamps_and_entities, $priority_count, TRUE);
    $this->loadContentEntities($priority_timestamps_and_entities, $priority_count, TRUE);

    // Sort the $timestamps_and_entities array.
    usort($priority_timestamps_and_entities, function ($a, $b) {
      return $b['published_at'] <=> $a['published_at'];
    });

    // Because the series entities and content entities have been independently
    // loaded, we may have too many entries in the
    // $priority_timestamps_and_entities array. Make sure we have
    // $priority_count at most.
    $priority_timestamps_and_entities = array_slice($priority_timestamps_and_entities, 0, $priority_count);

    // Then get the content non-priority content.
    $non_priority_timestamps_and_entities = [];
    $non_priority_count = $pagination->getSize() - count($priority_timestamps_and_entities);
    $this->loadSeriesEntities($non_priority_timestamps_and_entities, $non_priority_count, FALSE);
    $this->loadContentEntities($non_priority_timestamps_and_entities, $non_priority_count, FALSE);

    // Sort the $timestamps_and_entities array.
    usort($non_priority_timestamps_and_entities, function ($a, $b) {
      return $b['published_at'] <=> $a['published_at'];
    });

    $timestamps_and_entities = array_merge($priority_timestamps_and_entities, $non_priority_timestamps_and_entities);

    // Extract the "entity" key from the array.
    $entities = array_column($timestamps_and_entities, 'entity');

    $data = $this->createCollectionDataFromEntities(array_slice($entities, 0, $pagination->getSize()));
    $response = $this->createJsonapiResponse($data, $request);
    $response->addCacheableDependency($cacheability);
    return $response;

  }

  /**
   * Load series taxonomy term entities, that have the most recent content.
   *
   * This function runs a query that finds the most recently published content,
   * that is in a series.  Groups them by series (to remove duplicates) and
   * appends them onto $entities.
   *
   * @param array $timestamps_and_entities
   *   The array of content entities, passed in by reference, to be appended to.
   * @param int $size
   *   The size requested.
   * @param bool $priority
   *   TRUE - only return prioritised content.
   *   FALSE - only return non-prioritised content.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function loadSeriesEntities(array &$timestamps_and_entities, int $size, bool $priority) {
    if ($size == 0) {
      return;
    }

    // Use aggregateQuery instead of standard entity query, so that we can group
    // by series (to remove duplicates).
    $query = $this->entityTypeManager->getStorage('node')->getAggregateQuery()->accessCheck(TRUE);
    $query->groupBy('field_moj_series');
    $query->sortAggregate('published_at', 'MAX', 'DESC');
    $query->condition('type', self::$contentTypes, 'IN');

    // Only query for content that _is_ in a series.
    $query->condition('field_moj_series', NULL, 'IS NOT NULL');

    // Ensure we only check for published content.
    // Note that prison category rules are automatically applied to the query.
    // @see \Drupal\prisoner_hub_prison_access\EventSubscriber\QueryAccessSubscriber.
    $query->condition('status', NodeInterface::PUBLISHED);

    if ($priority) {
      $query->condition('field_prioritise_on_recently_add', TRUE);
    }
    else {
      // Non-prioritised content has value NULL or FALSE, depending on if it
      // existed before the field_prioritise_on_recently_add field existed.
      $orCondition = $query->orConditionGroup()
        ->condition('field_prioritise_on_recently_add', NULL, 'IS NULL')
        ->condition('field_prioritise_on_recently_add', FALSE);
      $query->condition($orCondition);
    }

    // If already have enough entities set, ensure we only query for newer
    // content. (This avoids having to unnecessarily load entities).
    if (count($timestamps_and_entities) >= $size) {
      $query->condition('published_at', min(array_column($timestamps_and_entities, 'published_at')), '>');
    }

    $query->range(0, $size);
    $results = $this->executeQueryInRenderContext($query);
    foreach ($results as $result) {
      $term = $this->entityTypeManager->getStorage('taxonomy_term')->load($result['field_moj_series_target_id']);
      if ($term) {
        $timestamps_and_entities[] = [
          'published_at' => (int) $result['published_at_max'],
          'entity' => $term,
        ];
      }
    }
  }

  /**
   * Load content entities that are not in a series.
   *
   * @param array $timestamps_and_entities
   *   The array of timestamps and entities, passed in by reference, to be
   *   appended to.
   * @param int $size
   *   The size requested.
   * @param bool $priority
   *   TRUE - only return prioritised content.
   *   FALSE - only return non-prioritised content.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function loadContentEntities(array &$timestamps_and_entities, int $size, bool $priority) {
    if ($size == 0) {
      return;
    }

    $query = $this->entityTypeManager->getStorage('node')->getQuery();
    $query->condition('type', self::$contentTypes, 'IN');

    // Only get back content not in a series.
    $query->condition('field_moj_series', NULL, 'IS NULL');

    // Ensure we only check for published content.
    // Note that prison category rules are automatically applied to the query.
    // @see \Drupal\prisoner_hub_prison_access\EventSubscriber\QueryAccessSubscriber.
    $query->condition('status', NodeInterface::PUBLISHED);

    if ($priority) {
      $query->condition('field_prioritise_on_recently_add', TRUE);
    }
    else {
      // Non-prioritised content has value NULL or FALSE, depending on if it
      // existed before the field_prioritise_on_recently_add field existed.
      $orCondition = $query->orConditionGroup()
        ->condition('field_prioritise_on_recently_add', NULL, 'IS NULL')
        ->condition('field_prioritise_on_recently_add', FALSE);
      $query->condition($orCondition);
    }

    // If already have enough entities set, ensure we only query for newer
    // content. (This avoids having to unnecessarily load entities).
    if (count($timestamps_and_entities) >= $size) {
      $query->condition('published_at', min(array_column($timestamps_and_entities, 'published_at')), '>');
    }

    $query->sort('published_at', 'DESC');
    $query->range(0, $size);
    $result = $this->executeQueryInRenderContext($query);
    $nodes = $this->entityTypeManager->getStorage('node')->loadMultiple($result);
    foreach ($nodes as $node) {
      $timestamps_and_entities[] = [
        'published_at' => (int) $node->get('published_at')->value,
        'entity' => $node,
      ];
    }
  }

  /**
   * Executes the query in a render context.
   *
   * This avoids a fatal error, as running entity queries at this stage causes
   * Drupal to break.
   *
   * @param \Drupal\Core\Entity\Query\QueryInterface $query
   *   The query to execute to get the return results.
   *
   * @return int|array
   *   Returns the result of the query.
   *
   * @see \Drupal\jsonapi\Controller\EntityResource::executeQueryInRenderContext()
   * @todo Remove this after https://www.drupal.org/project/drupal/issues/3028976 is fixed.
   */
  protected function executeQueryInRenderContext(QueryInterface $query) {
    $context = new RenderContext();
    return $this->renderer->executeInRenderContext($context, function () use ($query) {
      return $query->accessCheck(TRUE)->execute();
    });
  }

  /**
   * Get pagination for the request.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @return \Drupal\jsonapi\Query\OffsetPage
   *   The pagination object.
   */
  private function getPagination(Request $request): OffsetPage {
    return $request->query->has('page')
      ? OffsetPage::createFromQueryParameter($request->query->all()['page'] ?? [])
      : new OffsetPage(OffsetPage::DEFAULT_OFFSET, OffsetPage::SIZE_MAX);
  }

  /**
   * {@inheritdoc}
   *
   * This tells jsonapi_resources module that our resource works only works with
   * certain content types and series taxonomy terms.
   */
  public function getRouteResourceTypes(Route $route, string $route_name): array {
    return array_filter($this->resourceTypeRepository->all(), function (ResourceType $type) {
      return $type->getEntityTypeId() == 'node' && in_array($type->getBundle(), self::$contentTypes) ||
        $type->getEntityTypeId() == 'taxonomy_term' && $type->getBundle() == 'series';
    });
  }

}
