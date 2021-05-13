<?php

namespace Drupal\prisoner_hub_jsonapi_resources\Resource;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\jsonapi\ResourceResponse;
use Drupal\jsonapi_resources\Entity\Query\PaginatorMetadata;
use Drupal\jsonapi_resources\Resource\EntityQueryResourceBase;
use Symfony\Component\HttpFoundation\Request;

/**
 * Processes a request for a collection of "topics".
 *
 * Topics (currently) refers to categories and tags Taxonomy terms.
 *
 * @internal
 */
class TaxonomyTerm extends EntityQueryResourceBase {

  /**
   * Process the resource request.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @return \Drupal\jsonapi\ResourceResponse
   *   The response.
   */
  public function process(Request $request): ResourceResponse {
    $cacheability = new CacheableMetadata();
    $entity_query = $this->getEntityQuery('taxonomy_term');
    $cacheability->addCacheTags(['taxonomy_term_list']);

    $data = $this->loadResourceObjectDataFromEntityQuery($entity_query, $cacheability);

    $response = $this->createJsonapiResponse($data, $request, 200, []);
    $response->addCacheableDependency($cacheability);

    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function getRouteResourceTypes($route, string $route_name): array {
    return $this->getResourceTypesByEntityTypeId('taxonomy_term');
  }
}
