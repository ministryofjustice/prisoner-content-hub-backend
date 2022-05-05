<?php

namespace Drupal\prisoner_hub_sub_terms\Resource;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Entity\RevisionableStorageInterface;
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
   * Override the parent function, as we need to convert the results to
   * taxonomy ids.
   */
  protected function loadResourceObjectDataFromEntityQuery(QueryInterface $entity_query, CacheableMetadata $cacheable_metadata, $load_latest_revisions = FALSE, $check_access = TRUE): ResourceObjectData {
    $results = \Drupal::service('jsonapi_resources.entity_query_executor')->executeQueryAndCaptureCacheability($entity_query, $cacheable_metadata);
    $taxonomy_ids = $this->getTaxonomyIdsFromQueryResults($results);
    return $this->loadResourceObjectsByEntityIds('taxonomy_term', $taxonomy_ids, $load_latest_revisions, $check_access);
  }

  /**
   * Take aggregated entity results from nodes and convert them to taxonomy ids.
   *
   * @param array $results
   *   The $results array from an \Drupal\Core\Entity\Query\QueryAggregateInterface
   *   Should contain fields field_moj_top_level_categories and field_moj_series.
   *
   * @return array
   *   An array of taxonomy ids.
   */
  protected function getTaxonomyIdsFromQueryResults($results) {
    return array_map(static function ($item) {
      // Return either category id or series id, first non NULL value.
      return $item['field_moj_top_level_categories_target_id'] ?? $item['field_moj_series_target_id'];
    }, $results);
  }

  /**
   * {@inheritdoc}
   *
   * This is a private function in the subclass, so we cannot call it directly.
   * Therefore the entire function has been copied over to this class.
   */
  protected function loadResourceObjectsByEntityIds($entity_type_id, array $ids, $load_latest_revisions = FALSE, $check_access = TRUE): ResourceObjectData {
    $storage = $this->entityTypeManager->getStorage($entity_type_id);
    if ($load_latest_revisions) {
      assert($storage instanceof RevisionableStorageInterface);
      $entities = $storage->loadMultipleRevisions(array_keys($ids));
    }
    else {
      $entities = $storage->loadMultiple($ids);
    }
    return $this->createCollectionDataFromEntities($entities, $check_access);
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
