<?php

namespace Drupal\prisoner_hub_sub_terms\Resource;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Entity\RevisionableStorageInterface;
use Drupal\Core\Http\Exception\CacheableBadRequestHttpException;
use Drupal\Core\Render\RenderContext;
use Drupal\Core\Url;
use Drupal\jsonapi\JsonApiResource\Link;
use Drupal\jsonapi\JsonApiResource\LinkCollection;
use Drupal\jsonapi\JsonApiResource\ResourceObjectData;
use Drupal\jsonapi\Query\OffsetPage;
use Drupal\jsonapi\ResourceResponse;
use Drupal\jsonapi_resources\Resource\EntityResourceBase;
use Drupal\node\NodeInterface;
use Drupal\taxonomy\Entity\Term;
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
class SubTerms extends EntityResourceBase {

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
    // $this->getTaxonomyIdsFromQueryResults().

    // Group by category and series.
    $query->groupBy('field_moj_top_level_categories');
    $query->groupBy('field_moj_series');

    // Aggregate the groupings by the most recently created, and sort by that.
    $query->sortAggregate('changed', 'MAX', 'DESC');

    $pagination = $this->getPagination($request);
    if ($pagination->getSize() <= 0) {
      throw new CacheableBadRequestHttpException($cacheability, sprintf('The page size needs to be a positive integer.'));
    }

    $results = $this->executeQueryInRenderContext($query);
    $taxonomy_ids = $this->getTaxonomyIdsFromQueryResults($results);
    $entities = Term::loadMultiple($taxonomy_ids);
    $processed_entities = $this->filterClosestSubCategoriesAndSeries($entities, $taxonomy_term);

    $result_entities = array_slice($processed_entities, $pagination->getOffset(), $pagination->getSize());
    $data = $this->createCollectionDataFromEntities($result_entities);

    $pager_links = $this->getPagerLinks($request, $pagination, count($processed_entities), count($result_entities));
    $response = $this->createJsonapiResponse($data, $request, 200, [], $pager_links);
    $response->addCacheableDependency($cacheability);
    return $response;
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
   * Take a list of entities and filter out those are two or more levels below.
   *
   * This is required as the entity query we run looks at content in all levels
   * of the taxonomy (e.g. sub-sub categories, and series that are part of
   * sub-sub categories).  We want to remove these and replace with the highest
   * level (after $top_parent_entity).
   * i.e. Our resource only returns sub-categories one level down, and series
   * directly assigned to the current category.
   *
   * @param array $entities
   *   A list of Taxonomy entities to process.
   * @param \Drupal\taxonomy\TermInterface $top_parent_entity
   *   The top level entity (i.e. the category for the current page).
   *
   * @return array
   *   An array of filtered entities.
   */
  protected function filterClosestSubCategoriesAndSeries(array $entities, TermInterface $top_parent_entity) {
    $processed_entities = [];
    foreach ($entities as $entity) {
      if ($entity->bundle() == 'series') {
        $category_id = $entity->get('field_category')->target_id;
        if ($category_id == $top_parent_entity->id()) {
          // This is a series of the current category.
          // In this case we display the series itself, rather than searching
          // for closer sub-categories.
          $processed_entities[$entity->id()] = $entity;
          continue;
        }
      }
      else {
        $category_id = $entity->id();
      }
      $top_level_id = $this->findClosestSubCategory($category_id, $top_parent_entity->id());
      if (!isset($processed_entities[$top_level_id]) && $top_level_id) {
        $top_level_entity = isset($entities[$top_level_id]) ? $entities[$top_level_id] : Term::load($top_level_id);
        $processed_entities[$top_level_id] = $top_level_entity;
      }
    }
    return $processed_entities;
  }

  /**
   * Take a taxonomy id and find the highest level, underneath $top_parent_id.
   *
   * This function is called recursively, so that it works on multiple levels
   * of taxonomy.
   *
   * @param string|int $id
   *   The id to search for parents.
   * @param string|int $top_parent_id
   *   The taxonomy term id of the top parent (i.e. the current category page).
   *
   * @return mixed
   *   Either the taxonomy term id if found, or NULL.
   */
  protected function findClosestSubCategory($id, $top_parent_id) {
    // It's okay to call loadTree() multiple times, as it has it's own cache.
    $tree = $this->entityTypeManager->getStorage('taxonomy_term')->loadTree('moj_categories', $top_parent_id);
    foreach ($tree as $term_result) {
      if ($term_result->tid == $id) {
        if (in_array($top_parent_id, $term_result->parents)) {
          return $term_result->tid;
        }
        else {
          foreach ($term_result->parents as $parent_id) {
            $id = $this->findClosestSubCategory($parent_id, $top_parent_id, $tree);
            if ($id) {
              return $id;
            }
          }
          // If no $id is found, then continue searching.  Possibly there are
          // multiple parents and this one is from a different tree.
        }
      }
    }
    // In case we haven't found anything return NULL.
    // This should only happen if using multiple parents and one is part of
    // a totally different tree.
    return NULL;
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
   * {@inheritdoc}
   *
   * This tells jsonapi_resources module that our resource works with all
   * taxonomy types.
   */
  public function getRouteResourceTypes(Route $route, string $route_name): array {
    return $this->getResourceTypesByEntityTypeId('taxonomy_term');
  }

  /**
   * Pagination functionality.
   *
   * The functions below have been copied directly from
   * \Drupal\jsonapi_search_api\Resource\IndexResource.
   *
   * Although pagination handling is supplied by the jsonapi_resources module,
   * this is only provided when you extend EntityQueryResourceBase, and ensure
   * that pagination can be applied directly onto the entity query.  In our case
   * we are unable to apply pagination onto the query, so we need to handle it
   * in PHP (the same as the jsonapi_search_api module).
   */

  /**
   * Get pagination for the request.
   *
   * @see https://git.drupalcode.org/project/jsonapi_search_api/-/blob/61cd08be71d76528564898b19a7f91f94a07aa03/src/Resource/IndexResource.php#L189-202
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
   * Get pager links.
   *
   * @see https://git.drupalcode.org/project/jsonapi_search_api/-/blob/61cd08be71d76528564898b19a7f91f94a07aa03/src/Resource/IndexResource.php#L204-240
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   * @param \Drupal\jsonapi\Query\OffsetPage $pagination
   *   The pagination object.
   * @param int $total_count
   *   The total count.
   * @param int $result_count
   *   The result count.
   *
   * @return \Drupal\jsonapi\JsonApiResource\LinkCollection
   *   The link collection.
   */
  protected function getPagerLinks(Request $request, OffsetPage $pagination, int $total_count, int $result_count): LinkCollection {
    $pager_links = new LinkCollection([]);
    $size = (int) $pagination->getSize();
    $offset = $pagination->getOffset();
    $query = (array) $request->query->getIterator();

    // Check if this is not the last page.
    if (($pagination->getOffset() + $result_count) < $total_count) {
      $next_url = static::getRequestLink($request, static::getPagerQueries('next', $offset, $size, $query));
      $pager_links = $pager_links->withLink('next', new Link(new CacheableMetadata(), $next_url, 'next'));
      $last_url = static::getRequestLink($request, static::getPagerQueries('last', $offset, $size, $query, $total_count));
      $pager_links = $pager_links->withLink('last', new Link(new CacheableMetadata(), $last_url, 'last'));
    }
    // Check if this is not the first page.
    if ($offset > 0) {
      $first_url = static::getRequestLink($request, static::getPagerQueries('first', $offset, $size, $query));
      $pager_links = $pager_links->withLink('first', new Link(new CacheableMetadata(), $first_url, 'first'));
      $prev_url = static::getRequestLink($request, static::getPagerQueries('prev', $offset, $size, $query));
      $pager_links = $pager_links->withLink('prev', new Link(new CacheableMetadata(), $prev_url, 'prev'));
    }
    return $pager_links;
  }

  /**
   * Get the query param array.
   *
   * @see https://git.drupalcode.org/project/jsonapi_search_api/-/blob/61cd08be71d76528564898b19a7f91f94a07aa03/src/Resource/IndexResource.php#L242-301
   *
   * @param string $link_id
   *   The name of the pagination link requested.
   * @param int $offset
   *   The starting index.
   * @param int $size
   *   The pagination page size.
   * @param array $query
   *   The query parameters.
   * @param int $total
   *   The total size of the collection.
   *
   * @return array
   *   The pagination query param array.
   */
  protected static function getPagerQueries($link_id, $offset, $size, array $query = [], $total = 0) {
    $extra_query = [];
    switch ($link_id) {
      case 'next':
        $extra_query = [
          'page' => [
            'offset' => $offset + $size,
            'limit' => $size,
          ],
        ];
        break;

      case 'first':
        $extra_query = [
          'page' => [
            'offset' => 0,
            'limit' => $size,
          ],
        ];
        break;

      case 'last':
        if ($total) {
          $extra_query = [
            'page' => [
              'offset' => (ceil($total / $size) - 1) * $size,
              'limit' => $size,
            ],
          ];
        }
        break;

      case 'prev':
        $extra_query = [
          'page' => [
            'offset' => max($offset - $size, 0),
            'limit' => $size,
          ],
        ];
        break;
    }
    return array_merge($query, $extra_query);
  }

  /**
   * Get the full URL for a given request object.
   *
   * @see https://git.drupalcode.org/project/jsonapi_search_api/-/blob/61cd08be71d76528564898b19a7f91f94a07aa03/src/Resource/IndexResource.php#L303-324
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   * @param array|null $query
   *   The query parameters to use. Leave it empty to get the query from the
   *   request object.
   *
   * @return \Drupal\Core\Url
   *   The full URL.
   */
  public static function getRequestLink(Request $request, $query = NULL) {
    if ($query === NULL) {
      return Url::fromUri($request->getUri());
    }

    $uri_without_query_string = $request->getSchemeAndHttpHost() . $request->getBaseUrl() . $request->getPathInfo();
    return Url::fromUri($uri_without_query_string)->setOption('query', $query);
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
    foreach ($entities as $entity) {

    }
    return $this->createCollectionDataFromEntities($entities, $check_access);
  }

}
