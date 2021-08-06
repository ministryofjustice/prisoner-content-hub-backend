<?php

namespace Drupal\prisoner_hub_prison_context;


use Drupal\Core\PathProcessor\OutboundPathProcessorInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\prisoner_hub_prison_context\Routing\RouteSubscriber;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Route;

/**
 * Processes the inbound path to use the prison context, passing it forward
 * from the current request.
 *
 * This was primarily required for the decoupled_router module, so that JSON:API
 * urls would reflect the current prison.  But should work for any url that is
 * being requested through a prison context.
 */
class PathProcessorOutbound implements OutboundPathProcessorInterface {

  /**
   * The routematch service.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * The JsonApi base path parameter.  This is normally '/jsonapi'.
   *
   * @var string
   */
  protected $jsonApiBasePath;

  /**
   * PathProcessorOutbound constructor.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match service.
   * @param string $jsonapi_base_path
   *   The jsonapi base path parameter.
   */
  public function __construct(RouteMatchInterface $route_match, string $jsonapi_base_path) {
    $this->routeMatch = $route_match;
    $this->jsonApiBasePath = $jsonapi_base_path;
  }

  /**
   * Process outbound urls, i.e. paths that are being generated in the current
   * request.
   */
  public function processOutbound($path, &$options = [], Request $request = NULL, BubbleableMetadata $bubbleable_metadata = NULL) {
    $prison = $this->routeMatch->getParameter('prison');
    if ($prison && isset($options['route']) && RouteSubscriber::isJsonApiRoute($options['route'])) {
      $prison_machine_name = $prison->get('machine_name')->getString();
      $path = str_replace($this->jsonApiBasePath, $this->jsonApiBasePath . '/prison/' . $prison_machine_name, $path);
    }
    return $path;
  }

}
