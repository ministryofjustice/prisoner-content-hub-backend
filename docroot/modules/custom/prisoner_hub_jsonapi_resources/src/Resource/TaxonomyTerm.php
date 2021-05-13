<?php

namespace Drupal\prisoner_hub_jsonapi_resources\Resource;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\jsonapi\Query\OffsetPage;
use Drupal\jsonapi\ResourceResponse;
use Drupal\jsonapi_resources\Entity\Query\PaginatorInterface;
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

  const SIZE_MAX = 100;

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

    $cacheability->addCacheContexts(['url.path']);
    $cacheability->addCacheTags(['taxonomy_term_list']);

    $paginator = $this->getPaginatorForRequest($request);

    $paginator->applyToQuery($entity_query, $cacheability);
    //$this->applyPaginatorToQuery($paginator, $entity_query, $cacheability);

    $data = $this->loadResourceObjectDataFromEntityQuery($entity_query, $cacheability);

    $pagination_links = $paginator->getPaginationLinks($entity_query, $cacheability);

    $response = $this->createJsonapiResponse($data, $request, 200, [], $pagination_links);
    $response->addCacheableDependency($cacheability);

    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function getRouteResourceTypes($route, string $route_name): array {
    return $this->getResourceTypesByEntityTypeId('taxonomy_term');
  }

  private function applyPaginatorToQuery(PaginatorInterface $paginator, QueryInterface $query, CacheableMetadata $cacheable_metadata) {
    // Ensure that different pages will be cached separately.
    $cacheable_metadata->addCacheContexts(['url.query_args:page']);
    // Derive any pagination options from the query params or use defaults.
    $pagination = $paginator->request->query->has('page')
      ? OffsetPage::createFromQueryParameter($this->request->query->get('page'))
      : new OffsetPage(OffsetPage::DEFAULT_OFFSET, self::SIZE_MAX);
    $query->range($pagination->getOffset(), $pagination->getSize() + 1);
    $metadata = new PaginatorMetadata();
    $metadata->pageSizeMax = $pagination->getSize();
    $metadata->pageLocation = $pagination->getOffset();
    $query->addMetaData(PaginatorMetadata::KEY, $metadata);
  }
}
