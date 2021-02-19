<?php

namespace Drupal\prisoner_hub_prison_context\Routing;

use Drupal\Core\Routing\RouteBuildEvent;
use Drupal\Core\Routing\RouteSubscriberBase;
use Drupal\Core\Routing\RoutingEvents;
use Symfony\Component\Routing\RouteCollection;

/**
 * Listens to the dynamic route events.
 */
class RouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    // TODO: Work out why this method must be declared.
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {

    $events[RoutingEvents::ALTER] = 'onAlterRoutes';
    return $events;
  }

  public function onAlterRoutes(RouteBuildEvent $event) {
    foreach ($event->getRouteCollection() as $name => $route) {
      /* @var \Symfony\Component\Routing\Route $route */
      // WARNING: This is using an internal jsonapi value, that could change.
      // TODO: Find a better way to determine jsonapi routes.
      // TODO: Do we want to modify _all_ jsonapi routes?
      if ($route->getDefault('_is_jsonapi')) {
        $new_route = clone($route);

        // TODO: Is this the best way we can modify the path?
        $new_route->setPath(str_replace('/jsonapi/', '/jsonapi/prison/{prison}/', $route->getPath()));
        $parameters = $route->getOption('parameters');
        $parameters['prison'] = ['type' => 'prison_context'];
        $new_route->setOption('parameters', $parameters);
        $event->getRouteCollection()->add('prisoner_hub_prison_context.' . $name, $new_route);
      }
    }
  }

}
