<?php

namespace Drupal\prisoner_hub_prison_context\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
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

  public function __construct(string $jsonapi_base_path) {
    $this->jsonapiBasePath = $jsonapi_base_path;
  }

  /**
   * Copy jsonapi routes as new routes with the prison as a parameter.
   *
   * @param \Symfony\Component\Routing\RouteCollection $collection
   */
  public function alterRoutes(RouteCollection $collection) {
    foreach ($collection as $name => $route) {
      /* @var \Symfony\Component\Routing\Route $route */
      if (Routes::isJsonApiRequest($route->getDefaults())) {
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
