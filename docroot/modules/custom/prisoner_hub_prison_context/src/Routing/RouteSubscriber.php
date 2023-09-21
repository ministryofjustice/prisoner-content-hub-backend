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
   * RouteSubscriber constructor.
   *
   * @param string $jsonapiBasePath
   *   The jsonapi base path parameter.
   */
  public function __construct(protected string $jsonapiBasePath) {
  }

  /**
   * {@inheritdoc}
   *
   * Copy jsonapi routes as new routes with the prison as a parameter.
   */
  public function alterRoutes(RouteCollection $collection) {
    foreach ($collection as $name => $route) {
      /** @var \Symfony\Component\Routing\Route $route */
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
   * @param \Symfony\Component\Routing\Route $route
   *   The route to check.
   *
   * @return bool
   *   TRUE if the route is for JsonAPI, FALSE if otherwise.
   */
  public static function isJsonApiRoute(Route $route) {
    return Routes::isJsonApiRequest($route->getDefaults()) || !empty($route->getDefault('_jsonapi_resource'));
  }

}
