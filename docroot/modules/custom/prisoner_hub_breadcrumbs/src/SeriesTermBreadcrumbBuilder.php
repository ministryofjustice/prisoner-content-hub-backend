<?php

namespace Drupal\prisoner_hub_breadcrumbs;

use Drupal\Core\Breadcrumb\Breadcrumb;
use Drupal\Core\Breadcrumb\BreadcrumbBuilderInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\taxonomy\TermInterface;

/**
 * Class SeriesTermBreadcrumbBuilder.
 *
 * Build breadcrumbs for series taxonomy terms.
 */
class SeriesTermBreadcrumbBuilder implements BreadcrumbBuilderInterface {
  use StringTranslationTrait;

  /**
   * Constructs the SeriesTermBreadcrumbBuilder.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entityRepository
   *   The entity repository.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected EntityRepositoryInterface $entityRepository,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public function applies(RouteMatchInterface $route_match) {
    if ($route_match->getRouteName() == 'entity.taxonomy_term.canonical') {
      $term = $route_match->getParameter('taxonomy_term');
      return $term instanceof TermInterface && $term->bundle() == 'series';
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   *   Thrown if the entity type doesn't exist.
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   *   Thrown if the storage handler couldn't be loaded.
   */
  public function build(RouteMatchInterface $route_match) {
    $breadcrumb = new Breadcrumb();
    $breadcrumb->addLink(Link::createFromRoute($this->t('Home'), '<front>'));
    /** @var \Drupal\taxonomy\TermInterface $term */
    $term = $route_match->getParameter('taxonomy_term');

    $breadcrumb->addCacheableDependency($term);

    $categories = $term->get('field_category')->referencedEntities();

    // If no categories found, return the breadcrumb with just "Home" link.
    if (empty($categories)) {
      return $breadcrumb;
    }

    /** @var \Drupal\taxonomy\TermInterface $category */
    $category = reset($categories);
    $parents = $this->entityTypeManager->getStorage('taxonomy_term')->loadAllParents($category->id());
    foreach (array_reverse($parents) as $term) {
      $term = $this->entityRepository->getTranslationFromContext($term);
      $breadcrumb->addCacheableDependency($term);
      $breadcrumb->addLink(Link::createFromRoute($term->getName(), 'entity.taxonomy_term.canonical', ['taxonomy_term' => $term->id()]));
    }

    // This breadcrumb builder is based on a route parameter, and hence it
    // depends on the 'route' cache context.
    $breadcrumb->addCacheContexts(['route']);

    return $breadcrumb;
  }

}
