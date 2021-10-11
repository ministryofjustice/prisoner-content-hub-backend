<?php

namespace Drupal\prisoner_hub_recommendations\Resource;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\jsonapi\ResourceResponse;
use Drupal\jsonapi_resources\Entity\Query\PaginatorMetadata;
use Drupal\jsonapi_resources\Resource\EntityQueryResourceBase;
use Drupal\node\NodeInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Route;


/**
 * Processes a request for content recommendations.
 *
 * For more info on how this class works, see examples in
 * jsonapi_resources/tests/modules/jsonapi_resources_test/src/Resource
 *
 * @internal
 */
class Recommendations extends EntityQueryResourceBase {

  /**
   * Process the resource request.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   * @param NodeInterface $node
   *   The node.
   *
   * @return \Drupal\jsonapi\ResourceResponse
   *   The response.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function process(Request $request, NodeInterface $node): ResourceResponse {
    $cacheability = new CacheableMetadata();
    $cacheability->addCacheContexts(['url.path']);

    $query = $this->getEntityQuery('node');

    $paginator = $this->getPaginatorForRequest($request);
    $paginator->applyToQuery($query, $cacheability);
    $page_size = $query->getMetaData(PaginatorMetadata::KEY)->pageSizeMax;

    // Exclude the current node.
    $query->condition('nid', $node->id(), '<>');

    // Exclude the current series.
    $series_value = $node->get('field_moj_series')->getValue();
    if (!empty($series_value)) {
      $query->condition('field_moj_series', $series_value[0]['target_id'], '<>');
    }

    $condition_group = $this->getConditionGroupForQuery($query, $node, $page_size);

    // If no conditions have been set, then make the query return no results.
    // (Otherwise it would return all results).
    if ($condition_group->count() == 0) {
      $query->addTag('no_results');
    }
    else {
      $query->condition($condition_group);

      // Add a query tag so that we can apply random sorting via hook_query_TAG_alter().
      // See https://drupal.stackexchange.com/a/249153/4831
      $query->addTag('sort_by_random');
    }

    $data = $this->loadResourceObjectDataFromEntityQuery($query, $cacheability);

    $pagination_links = $paginator->getPaginationLinks($query, $cacheability, TRUE);

    $response = $this->createJsonapiResponse($data, $request, 200, [], $pagination_links);
    $response->addCacheableDependency($cacheability);

    return $response;
  }

  /**
   * Get the condition group for the query.
   *
   * The condition group can contain 0, 1, or 2 conditions.  Depending on the
   * values of $node, and whether or not enough results can be obtained via
   * secondary tags.
   *
   * @param \Drupal\Core\Entity\Query\QueryInterface $query
   *  The current entity query object.
   * @param \Drupal\node\NodeInterface $node
   *   The current node object.
   * @param int $page_size
   *   The current page size.
   *
   * @return \Drupal\Core\Entity\Query\ConditionInterface
   */
  protected function getConditionGroupForQuery(QueryInterface $query, NodeInterface $node, int $page_size) {
    $condition_group = $query->orConditionGroup();
    $secondary_tags = array_column($node->get('field_moj_secondary_tags')->getValue(), 'target_id');

    // Get categories from series, or directly from $node.
    $series_entities = $node->get('field_moj_series')->referencedEntities();
    if (!empty($series_entities)) {
      $categories = array_column($series_entities[0]->get('field_category')->getValue(), 'target_id');
    }
    else {
      $categories = array_column($node->get('field_moj_top_level_categories')->getValue(), 'target_id');
    }

    $secondary_tag_result = [];
    if (!empty($secondary_tags)) {
      $condition_group->condition('field_moj_secondary_tags', $secondary_tags, 'IN');
      $secondary_tag_query = $query;
      $secondary_tag_query_query_cacheability = new CacheableMetadata();
      $secondary_tag_result = $this->loadResourceObjectDataFromEntityQuery($secondary_tag_query, $secondary_tag_query_query_cacheability);
    }

    // Only apply category condition if the secondary tags will bring back
    // less than the requested number of results.
    if ((empty($secondary_tag_result) || count($secondary_tag_result->getData()) < $page_size) && !empty($categories)) {
      $condition_group->condition('field_moj_top_level_categories', $categories, 'IN');
    }
    return $condition_group;
  }

  /**
   * {@inheritdoc}
   */
  public function getRouteResourceTypes(Route $route, string $route_name): array {
    return $this->getResourceTypesByEntityTypeId('node');
  }

}
