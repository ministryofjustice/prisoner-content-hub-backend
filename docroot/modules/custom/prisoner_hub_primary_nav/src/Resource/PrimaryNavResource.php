<?php

namespace Drupal\prisoner_hub_primary_nav\Resource;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\jsonapi\ResourceResponse;
use Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface;
use Drupal\jsonapi_menu_items\Resource\MenuItemsResource;
use Drupal\jsonapi_resources\Resource\ResourceBase;
use Drupal\jsonapi_resources\Unstable\DocumentExtractor;
use Drupal\jsonapi_resources\Unstable\ResourceResponseFactory;
use Drupal\system\Entity\Menu;
use Drupal\system\MenuInterface;
use Drupal\taxonomy\TermInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Route;

/**
 * Processes a request for the primary navigation.
 */
class PrimaryNavResource extends ResourceBase implements ContainerInjectionInterface {

  /**
   * Resource we are decorating.
   *
   * @var \Drupal\jsonapi_menu_items\Resource\MenuItemsResource
   */
  protected MenuItemsResource $menuItemsResource;

  /**
   * Constructor.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   Container.
   * @param \Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface $resourceTypeRepository
   *   Resource type repository.
   * @param \Drupal\jsonapi_resources\Unstable\ResourceResponseFactory $resourceResponseFactory
   *   Resource response factory.
   * @param \Drupal\jsonapi_resources\Unstable\DocumentExtractor $documentExtractor
   *   Document extractor.
   * @param \Drupal\Core\Routing\RouteMatchInterface $routeMatch
   *   Route match.
   * @param string $primaryNavFieldName
   *   Name of the field containing the primary nav.
   * @param string $defaultMenu
   *   Machine name of the default menu.
   */
  public function __construct(
    ContainerInterface $container,
    ResourceTypeRepositoryInterface $resourceTypeRepository,
    ResourceResponseFactory $resourceResponseFactory,
    DocumentExtractor $documentExtractor,
    protected RouteMatchInterface $routeMatch,
    protected string $primaryNavFieldName,
    protected string $defaultMenu,
  ) {
    $this->menuItemsResource = MenuItemsResource::create($container);

    $this->menuItemsResource->setResourceTypeRepository($resourceTypeRepository);
    $this->menuItemsResource->setResourceResponseFactory($resourceResponseFactory);
    $this->menuItemsResource->setDocumentExtractor($documentExtractor);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container,
      $container->get('jsonapi.resource_type.repository'),
      $container->get('jsonapi_resources.resource_response_factory'),
      $container->get('jsonapi_resources.document_extractor'),
      $container->get('current_route_match'),
      $container->getParameter('prisoner_hub_primary_nav.primary_nav_field_name'),
      $container->getParameter('prisoner_hub_primary_nav.default_menu'),
    );
  }

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
      $prison = $this->routeMatch->getParameter('prison');
      if ($prison instanceof TermInterface) {
        $cacheability->addCacheableDependency($prison);
        $entities = $prison->get($this->primaryNavFieldName)->referencedEntities();
        if (!empty($entities)) {
          $menu = reset($entities);
        }
      }
    }
    if (is_null($menu)) {
      $menu = Menu::load($this->defaultMenu);
    }
    $response = $this->menuItemsResource->process($request, $menu);
    $response->addCacheableDependency($cacheability);
    return $response;
  }

  /**
   * Overridden to ensure getRouteResourceTypes() called on the decorated class.
   */
  public function getRouteResourceTypes(Route $route, string $route_name): array {
    return $this->menuItemsResource->getRouteResourceTypes($route, $route_name);
  }

}
