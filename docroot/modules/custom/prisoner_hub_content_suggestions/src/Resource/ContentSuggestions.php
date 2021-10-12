<?php

namespace Drupal\prisoner_hub_content_suggestions\Resource;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\jsonapi\JsonApiResource\ResourceObject;
use Drupal\jsonapi\JsonApiResource\ResourceObjectData;
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
 * jsonapi_resources/tests/modules/jsonapi_resources_test/src/Resource
 *
 * @internal
 */
class ContentSuggestions extends EntityQueryResourceBase {

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

    // Add a query tag so that we can apply random sorting via hook_query_TAG_alter().
    // See https://drupal.stackexchange.com/a/249153/4831
    $query->addTag('sort_by_random');

    $condition_group = $query->orConditionGroup();

    $secondary_tags = array_column($node->get('field_moj_secondary_tags')->getValue(), 'target_id');

    $data = [];
    if (!empty($secondary_tags)) {
      $condition_group->condition('field_moj_secondary_tags', $secondary_tags, 'IN');
      $query_copy = $query;
      $query_copy->condition($condition_group);
      $data = $this->loadResourceObjectDataFromEntityQuery($query_copy, $cacheability);
    }

    // Only apply category condition if the secondary tags will bring back
    // less than the requested number of results.
    if ((empty($data) || count($data->getData()) < $page_size)) {
      // Get categories from series, or directly from $node.
      $series_entities = $node->get('field_moj_series')->referencedEntities();
      if (!empty($series_entities)) {
        $categories = array_column($series_entities[0]->get('field_category')->getValue(), 'target_id');
        if (!empty($categories)) {
          $condition_group->condition('field_moj_series.entity:taxonomy_term.field_category', $categories, 'IN');
        }
      }
      else {
        $categories = array_column($node->get('field_moj_top_level_categories')->getValue(), 'target_id');
        if (!empty($categories)) {
          $condition_group->condition('field_moj_top_level_categories', $categories, 'IN');
        }
      }
      // If no conditions have been set, then return no results.
      if ($condition_group->count() == 0) {
        $data = $this->createCollectionDataFromEntities([]);
      }
      else {
        $query->condition($condition_group);
        $data = $this->loadResourceObjectDataFromEntityQuery($query, $cacheability);
      }
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
