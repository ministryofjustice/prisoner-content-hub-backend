<?php

namespace Drupal\prisoner_hub_edit_only\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Class RouteSubscriber
 *
 * Alter routes to override node and taxonomy canonical view pages, so that
 * we can redirect them to the edit page.
 */
class RouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    if ($node_view_route = $collection->get('entity.node.canonical')) {
      $node_view_route->setDefault('_controller', '\Drupal\prisoner_hub_edit_only\Controller\EntityViewOverride::viewNode');
    }
  }
}
