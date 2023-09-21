<?php

namespace Drupal\prisoner_hub_primary_nav\Resource;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\jsonapi\ResourceResponse;
use Drupal\jsonapi_menu_items\Resource\MenuItemsResource;
use Drupal\system\Entity\Menu;
use Drupal\system\MenuInterface;
use Drupal\taxonomy\TermInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Processes a request for the primary navigation.
 */
class PrimaryNavResource extends MenuItemsResource {

  /**
   * JSON:API resource for the '/%jsonapi%/primary_navigation' route.
   *
   * If this request is using a prison context,
   * e.g. /jsonapi/prison/wayland/primary_navigation
   * then lookup the menu being used for that prison.  If none then use the
   * default menu.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   * @param \Drupal\system\MenuInterface|null $menu
   *   (Optional) The menu object.  This will _always_ be NULL, as our route
   *   isn't defined with the menu parameter.  We declare it as an argument so
   *   that this class is still compatible with
   *   \Drupal\jsonapi_menu_items\Resource\MenuItemsResource.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function process(Request $request, MenuInterface $menu = NULL): ResourceResponse {
    $cacheability = new CacheableMetadata();

    if (is_null($menu)) {
      $prison = \Drupal::routeMatch()->getParameter('prison');
      if ($prison instanceof TermInterface) {
        $cacheability->addCacheableDependency($prison);
        $entities = $prison->get(\Drupal::getContainer()->getParameter('prisoner_hub_primary_nav.primary_nav_field_name'))->referencedEntities();
        if (!empty($entities)) {
          $menu = reset($entities);
        }
      }
    }
    if (is_null($menu)) {
      $menu = Menu::load(\Drupal::getContainer()->getParameter('prisoner_hub_primary_nav.default_menu'));
    }
    $response = parent::process($request, $menu);
    $response->addCacheableDependency($cacheability);
    return $response;
  }

}
