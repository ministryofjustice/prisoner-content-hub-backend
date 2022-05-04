<?php

namespace Drupal\prisoner_hub_sub_terms\Resource;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\jsonapi\JsonApiResource\ResourceObjectData;
use Drupal\jsonapi\ResourceResponse;
use Drupal\jsonapi_resources\Resource\EntityQueryResourceBase;
use Drupal\node\NodeInterface;
use Drupal\taxonomy\TermInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Route;


/**
 * Processes a request for sub terms.
 *
 * For more info on how this class works, see examples in
 * jsonapi_resources/tests/modules/jsonapi_resources_test/src/Resource
 *
 * @internal
 */
class SubTerms extends EntityQueryResourceBase {

  /**
   * Process the resource request.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   * @param TermInterface $taxonomy_term
   *   The taxonomy term.
   *
   * @return \Drupal\jsonapi\ResourceResponse
   *   The response.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function process(Request $request, TermInterface $taxonomy_term): ResourceResponse {
    $cacheability = new CacheableMetadata();
    $cacheability->addCacheContexts(['url.path']);

    $tids = [$taxonomy_term->id()];

    // Check content also assigned to any sub-category (multiple levels) of the
    // current category.
    $children = $this->entityTypeManager->getStorage('taxonomy_term')->loadTree($taxonomy_term->bundle(), $taxonomy_term->id());
    foreach ($children as $child) {
      $tids[] = $child->tid;
    }

    // Use aggregate entity query, so that we can use groupBy on the category
    // and series fields.  Removing duplicate category and series ids.
    // @see https://www.drupal.org/node/1918702
    $query = $this->entityTypeManager->getStorage('node')->getAggregateQuery();

    // Check for content that's...
    $condition_group = $query->orConditionGroup()
      // Assigned to a category that has a parent (multiple levels) which is the
      // current category.
      ->condition('field_moj_top_level_categories.entity:taxonomy_term.parent', $tids, 'IN')

      // Assigned to a series that is assigned to the current category (or one
      // of it's children).
      ->condition('field_moj_series.entity:taxonomy_term.field_category', $tids, 'IN');

    $query->condition($condition_group);

    // Filter for published content.
    $query->condition('status', NodeInterface::PUBLISHED);

    // Note that prison filtering is automatically applied to the query.
    // @see \Drupal\prisoner_hub_prison_access\EventSubscriber\QueryAccessSubscriber

    // Add groupBy's to the query.  Note that by adding these, the target id
    // of the taxonomy term will become available in the query result.
    // We can then convert these to taxonomy entities in
    // $this->loadResourceObjectsByEntityIds().
    $query->groupBy('field_moj_top_level_categories');
    $query->groupBy('field_moj_series');

    // Aggregate the groupings by the most recently created, and sort by that.
    $query->sortAggregate('changed', 'MAX', 'DESC');

    $paginator = $this->getPaginatorForRequest($request);
    $paginator->applyToQuery($query, $cacheability);

    // Use the standard entity query executor from jsonapi_resources module.
    // This handles things like pagination, and cacheability for us.
    // This calls loadResourceObjectsByEntityIds() which we override below.
    $data = $this->loadResourceObjectDataFromEntityQuery($query, $cacheability);

    $pagination_links = $paginator->getPaginationLinks($query, $cacheability, TRUE);
    $response = $this->createJsonapiResponse($data, $request, 200, [], $pagination_links);
    $response->addCacheableDependency($cacheability);
    return $response;

  }

  /**
   * {@inheritdoc}
   *
   * Override parent method so that we load in the correct entities.
   * As our original entity query was for nodes, but we actually want to load in
   * taxonomy terms from the entity reference field values.
   *
   * Overriding this method is a bit of a workaround, so that we can still call
   * $this->loadResourceObjectDataFromEntityQuery().
   * We would have otherwise used $this->entityQueryExecutor directly, but it's
   * private.
   */
  protected function loadResourceObjectsByEntityIds($entity_type_id, array $ids, $load_latest_revisions = FALSE, $check_access = TRUE): ResourceObjectData {
    $filtered_ids = array_map(static function ($item) {
      // Return either category id or series id, first non NULL value.
      return $item['field_moj_top_level_categories_target_id'] ?? $item['field_moj_series_target_id'];
    }, $ids);
    return parent::loadResourceObjectsByEntityIds('taxonomy_term', $filtered_ids, $load_latest_revisions, $check_access);
  }

  /**
   * {@inheritdoc}
   *
   * This tells jsonapi_resources module that our resource works with all
   * taxonomy types.
   */
  public function getRouteResourceTypes(Route $route, string $route_name): array {
    return $this->getResourceTypesByEntityTypeId('taxonomy_term');
  }
}
