<?php

namespace Drupal\prisoner_hub_recently_added\Resource;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Http\Exception\CacheableBadRequestHttpException;
use Drupal\Core\Render\RenderContext;
use Drupal\jsonapi\Query\OffsetPage;
use Drupal\jsonapi\ResourceResponse;
use Drupal\jsonapi\ResourceType\ResourceType;
use Drupal\jsonapi_resources\Resource\EntityResourceBase;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Drupal\taxonomy\Entity\Term;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Route;

/**
 * Processes a request for recently added content/series.
 *
 * For more info on how this class works, see examples in
 * jsonapi_resources/tests/modules/jsonapi_resources_test/src/Resource
 *
 * @internal
 */
class RecentlyAdded extends EntityResourceBase {

  /**
   * Array of content types to use in the resource.
   *
   * @var array|string[]
   */
  static array $content_types = ['moj_radio_item', 'page', 'link', 'moj_pdf_item', 'moj_video_item'];

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

    // This is a custom cache tag that is invalidated in prisoner_hub_recently_added_node_update().
    $cache_tag = 'prisoner_hub_recently_added';
    $prison = \Drupal::routeMatch()->getParameter('prison');
    if ($prison) {
      $cache_tag .= ':' . $prison->get('machine_name')->getString();
    }
    $cacheability->addCacheTags([$cache_tag]);

    $pagination = $this->getPagination($request);
    if ($pagination->getOffset() != 0) {
      throw new CacheableBadRequestHttpException($cacheability, sprintf('This resource does not currently support and offset greater than 0.'));
    }
    if ($pagination->getSize() <= 0) {
      throw new CacheableBadRequestHttpException($cacheability, sprintf('The page size needs to be a positive integer.'));
    }

    /** @var \Drupal\Core\Entity\ContentEntityInterface[] $entities */
    // An array of either node or series entities.
    $entities = [];

    /** @var int[] $published_at_timestamps */
    // The published_at timestamps, keyed to correspond with $entities.
    $published_at_timestamps = [];

    // Load in series and content entities separately.
    // We are unable to run everything in one db query, due to the different
    // rules for content in a series.
    $this->loadSeriesEntities($entities, $published_at_timestamps, $pagination->getSize());
    $this->loadContentEntities($entities, $published_at_timestamps, $pagination->getSize());

    // Sort the $published_at_timestamps array and update the $entities to use
    // the same order.
    array_multisort($published_at_timestamps, SORT_DESC, SORT_NUMERIC, $entities);

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
   * @param array $entities
   *   The array of content entities, passed in by reference, to be appended to.
   * @param array $published_at_timestamps
   *   The array of timestamps, passed in by reference, to be appended to.
   * @param int $size
   *   The size requested.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function loadSeriesEntities(array &$entities, array &$published_at_timestamps, int $size) {
    // Use aggregateQuery instead of standard entity query, so that we can group
    // by series (to remove duplicates).
    $query = $this->entityTypeManager->getStorage('node')->getAggregateQuery();
    $query->groupBy('field_moj_series');
    $query->sortAggregate('published_at', 'MAX', 'DESC');
    $query->condition('type', self::$content_types, 'IN');

    // Only query for content that _is_ in a series.
    $query->condition('field_moj_series', NULL, 'IS NOT NULL');

    // Ensure we only check for published content.
    // Note that prison category rules are automatically applied to the query.
    // See \Drupal\prisoner_hub_prison_access\EventSubscriber\QueryAccessSubscriber
    $query->condition('status', NodeInterface::PUBLISHED);

    // If already have enough entities set, ensure we only query for newer
    // content. (This avoids having to unnecessarily load entities).
    if (count($entities) >= $size) {
      $query->condition('published_at', min($published_at_timestamps), '>');
    }

    $query->range(0, $size);
    $results = $this->executeQueryInRenderContext($query);
    foreach ($results as $result) {
      $term = Term::load($result['field_moj_series_target_id']);
      if ($term) {
        $entities[] = $term;
        $published_at_timestamps[] = (int) $result['published_at_max'];
      }
    }
  }

  /**
   * Load content entities that are not in a series.
   *
   * @param array $entities
   *   The array of content entities, passed in by reference, to be appended to.
   * @param array $published_at_timestamps
   *   The array of timestamps, passed in by reference, to be appended to.
   * @param int $size
   *   The size requested.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function loadContentEntities(array &$entities, array &$published_at_timestamps, int $size) {
    $query = $this->entityTypeManager->getStorage('node')->getQuery();
    $query->condition('type', self::$content_types, 'IN');

    // Only get back content not in a series.
    $query->condition('field_moj_series', NULL, 'IS NULL');

    // Ensure we only check for published content.
    // Note that prison category rules are automatically applied to the query.
    // See \Drupal\prisoner_hub_prison_access\EventSubscriber\QueryAccessSubscriber
    $query->condition('status', NodeInterface::PUBLISHED);

    // If already have enough entities set, ensure we only query for newer
    // content. (This avoids having to unnecessarily load entities).
    if (count($entities) >= $size) {
      $query->condition('published_at', min($published_at_timestamps), '>');
    }

    $query->sort('published_at', 'DESC');
    $query->range(0, $size);
    $result = $this->executeQueryInRenderContext($query);
    $nodes = Node::loadMultiple($result);
    foreach ($nodes as $node) {
      $entities[] = $node;
      $published_at_timestamps[] = (int) $node->get('published_at')->value;
    }
  }

  /**
   * Executes the query in a render context.
   *
   * This avoids a fatal error, as running entity queries at this stage causes
   * Drupal to break.
   * @see \Drupal\jsonapi\Controller\EntityResource::executeQueryInRenderContext()
   * @todo Remove this after https://www.drupal.org/project/drupal/issues/3028976 is fixed.
   *
   * @param \Drupal\Core\Entity\Query\QueryInterface $query
   *   The query to execute to get the return results.
   *
   * @return int|array
   *   Returns the result of the query.
   */
  protected function executeQueryInRenderContext(QueryInterface $query) {
    $context = new RenderContext();
    $results = \Drupal::service('renderer')->executeInRenderContext($context, function () use ($query) {
      return $query->execute();
    });
    return $results;
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
      ? OffsetPage::createFromQueryParameter($request->query->get('page'))
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
      return $type->getEntityTypeId() == 'node' && in_array($type->getBundle(), self::$content_types) ||
        $type->getEntityTypeId() == 'taxonomy_term' && $type->getBundle() == 'series';
    });
  }
}
