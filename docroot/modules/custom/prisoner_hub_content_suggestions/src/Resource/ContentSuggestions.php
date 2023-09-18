<?php

namespace Drupal\prisoner_hub_content_suggestions\Resource;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\jsonapi\ResourceResponse;
use Drupal\jsonapi_resources\Entity\Query\PaginatorMetadata;
use Drupal\jsonapi_resources\Resource\EntityQueryResourceBase;
use Drupal\node\NodeInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Route;

/**
 * Processes a request for content suggestions.
 *
 * For more info on how this class works, see examples in
 * jsonapi_resources/tests/modules/jsonapi_resources_test/src/Resource.
 *
 * @internal
 */
class ContentSuggestions extends EntityQueryResourceBase {

  /**
   * Process the resource request.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   * @param \Drupal\node\NodeInterface $node
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

    // Exclude unpublished content as this isn't added by default to the query.
    // See https://drupal.stackexchange.com/a/257370/4831
    $query->condition('status', NodeInterface::PUBLISHED);

    // Exclude the current series.
    $series_value = $node->get('field_moj_series')->getValue();
    if (!empty($series_value)) {
      // Need to create an OR group to account for content that has no series.
      $series_condition_group = $query->orConditionGroup();
      $series_condition_group->condition('field_moj_series', $series_value[0]['target_id'], '<>');
      $series_condition_group->notExists('field_moj_series');
      $query->condition($series_condition_group);
    }

    // Add a query tag so that we can apply random sorting via
    // hook_query_TAG_alter().
    // See https://drupal.stackexchange.com/a/249153/4831
    $query->addTag('sort_by_random');

    $condition_group = $query->orConditionGroup();
    $query->condition($condition_group);
    $data = [];

    $topics = array_column($node->get('field_topics')->getValue(), 'target_id');
    if (!empty($topics)) {
      $condition_group->condition('field_topics', $topics, 'IN');
      $data = $this->loadResourceObjectDataFromEntityQuery($query, $cacheability);
      $query->addMetaData('sort_by_random_processed', TRUE);
    }

    // Only apply category condition if the secondary topics will bring back
    // less than the requested number of results.
    if ((empty($data) || count($data->getData()) < $page_size)) {
      // Get categories from series, or directly from $node.
      $series_entities = $node->get('field_moj_series')->referencedEntities();
      if (!empty($series_entities)) {
        $categories = array_column($series_entities[0]->get('field_category')->getValue(), 'target_id');
        if (!empty($categories)) {
          $condition_group->condition('field_moj_series.entity:taxonomy_term.field_category', $categories, 'IN');
          $data = $this->loadResourceObjectDataFromEntityQuery($query, $cacheability);
        }
      }
      else {
        $categories = array_column($node->get('field_moj_top_level_categories')->getValue(), 'target_id');
        if (!empty($categories)) {
          $condition_group->condition('field_moj_top_level_categories', $categories, 'IN');
          $data = $this->loadResourceObjectDataFromEntityQuery($query, $cacheability);
        }
      }
    }

    // If no data set, then return no results.
    if (empty($data)) {
      $data = $this->createCollectionDataFromEntities([]);
    }

    $response = $this->createJsonapiResponse($data, $request);
    $response->addCacheableDependency($cacheability);

    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function getRouteResourceTypes(Route $route, string $route_name): array {
    return $this->getResourceTypesByEntityTypeId('node');
  }

}
