<?php

namespace Drupal\prisoner_hub_breadcrumbs;

use Drupal\Core\Breadcrumb\Breadcrumb;
use Drupal\Core\Breadcrumb\BreadcrumbBuilderInterface;
use Drupal\Core\Link;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\taxonomy\TermInterface;

/**
 * Class SeriesTermBreadcrumbBuilder.
 *
 * Build breadcrumbs for topics taxonomy terms.
 */
class TopicsTermBreadcrumbBuilder implements BreadcrumbBuilderInterface {
  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function applies(RouteMatchInterface $route_match) {
    if ($route_match->getRouteName() == 'entity.taxonomy_term.canonical') {
      $term = $route_match->getParameter('taxonomy_term');
      return $term instanceof TermInterface && $term->bundle() == 'topics';
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function build(RouteMatchInterface $route_match) {
    $breadcrumb = new Breadcrumb();
    $breadcrumb->addLink(Link::createFromRoute($this->t('Home'), '<front>'));
    $breadcrumb->addLink(Link::fromTextAndUrl($this->t('Browse all topics'), Url::fromUri('internal:/topics')));

    $breadcrumb->addCacheContexts(['route']);

    return $breadcrumb;
  }
}
