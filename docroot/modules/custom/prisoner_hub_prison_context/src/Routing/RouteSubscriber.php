<?php

namespace Drupal\prisoner_hub_prison_context\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Drupal\jsonapi\Routing\Routes;
use Symfony\Component\Routing\Route;
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
   * RouteSubscriber constructor.
   *
   * @param string $jsonapi_base_path
   *   The jsonapi base path parameter.
   */
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
      if (self::isJsonApiRoute($route)) {
        $new_route = clone($route);
        $this->addPrisonContextToRoute($new_route, str_replace($this->jsonapiBasePath, $this->jsonapiBasePath . '/prison/{prison}', $route->getPath()));
        $collection->add('prisoner_hub_prison_context.' . $name, $new_route);
      }
      elseif ($name == 'decoupled_router.path_translation') {
        $new_route = clone($route);
        $this->addPrisonContextToRoute($new_route, 'router/prison/{prison}/translate-path');
        $collection->add('prisoner_hub_prison_context.' . $name, $new_route);
      }
    }
  }

  /**
   * Add prison context to a route.
   *
   * @param \Symfony\Component\Routing\Route $route
   *   The route to be modified.
   * @param string $new_path
   *   The new path for the route.
   *
   * @return \Symfony\Component\Routing\Route
   *   The modified route, with prison context.
   */
  protected function addPrisonContextToRoute(Route $route, string $new_path) {
    $route->setPath($new_path);
    $parameters = $route->getOption('parameters');
    $parameters['prison'] = ['type' => 'prison_context'];
    $route->setOption('parameters', $parameters);
    return $route;
  }

  /**
   * Check to see if a route is a JSON:API request, that should be copied.
   *
   * @param $route
   *   The route to check.
   *
   * @return bool
   *   TRUE if the route is for JsonAPI, FALSE if otherwise.
   */
  static public function isJsonApiRoute($route) {
    return Routes::isJsonApiRequest($route->getDefaults()) || !empty($route->getDefault('_jsonapi_resource'));
  }
}
