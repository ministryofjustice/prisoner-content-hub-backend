<?php

namespace Drupal\prisoner_hub_prison_context\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Drupal\jsonapi\JsonApiResource\ResourceObject;
use Drupal\jsonapi\Routing\Routes;
use Symfony\Component\Routing\RouteCollection;

/**
 * Listens to the dynamic route events.
 */
class RouteSubscriber extends RouteSubscriberBase {

  /**
   * @var string
   *
   * The jsonapi base path.
   */
  protected $jsonapiBasePath;

  /**
   * @var array
   *
   * List of entity type ids to apply prison context routing to.
   */
  protected $entityTypeIds;

  public function __construct(string $jsonapi_base_path, array $entity_type_ids) {
    $this->jsonapiBasePath = $jsonapi_base_path;
    $this->entityTypeIds = $entity_type_ids;
  }

  /**
   * Copy jsonapi routes as new routes with the prison as a parameter.
   *
   * @param \Symfony\Component\Routing\RouteCollection $collection
   */
  public function alterRoutes(RouteCollection $collection) {
    foreach ($collection as $name => $route) {
      /* @var \Symfony\Component\Routing\Route $route */
      if ($route->getDefault(Routes::JSON_API_ROUTE_FLAG_KEY)) {
        $entity_type = isset($route->getOption('parameters')['entity']) ? $route->getOption('parameters')['entity']['type'] : NULL;
        // Only copy jsonapi routes that are either:
        // 1) For one of the entity types we have specified (in prisoner_hub_prison_context.services.yml)
        // 2) Is a jsonapi_resources (contrib module) route.
        if (in_array(str_replace('entity:', '', $entity_type), $this->entityTypeIds) || $route->getDefault('_jsonapi_resource')) {
          $new_route = clone($route);
          $new_route->setPath(str_replace($this->jsonapiBasePath, $this->jsonapiBasePath . '/prison/{prison}', $route->getPath()));
          $parameters = $route->getOption('parameters');
          $parameters['prison'] = ['type' => 'prison_context'];
          $new_route->setOption('parameters', $parameters);
          $collection->add('prisoner_hub_prison_context.' . $name, $new_route);
        }
      }
    }
  }
}
