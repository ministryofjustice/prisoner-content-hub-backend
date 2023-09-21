<?php

namespace Drupal\prisoner_hub_breadcrumbs;

use Drupal\Core\Breadcrumb\Breadcrumb;
use Drupal\Core\Breadcrumb\BreadcrumbBuilderInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\node\NodeInterface;

/**
 * Class NodeBreadcrumbBuilder.
 *
 * Build breadcrumbs for nodes.
 *
 * @see \Drupal\prisoner_hub_breadcrumbs\SeriesTermBreadcrumbBuilder
 */
class NodeBreadcrumbBuilder implements BreadcrumbBuilderInterface {
  use StringTranslationTrait;

  /**
   * Constructs the NodeBreadcrumbBuilder.
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
    // Only apply to nodes that have either a category or a series.
    if ($route_match->getRouteName() == 'entity.node.canonical') {
      $node = $route_match->getParameter('node');
      if ($node instanceof NodeInterface && $node->hasField('field_moj_top_level_categories') && $node->hasField('field_moj_series')) {
        return !empty($node->get('field_moj_top_level_categories')->getValue()) || !empty($node->get('field_moj_series')->getValue());
      }
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
    /** @var \Drupal\node\NodeInterface $node */
    $node = $route_match->getParameter('node');

    $breadcrumb->addCacheableDependency($node);

    $series_value = $node->get('field_moj_series')->referencedEntities();
    $series = NULL;
    if (!empty($series_value)) {
      /** @var \Drupal\taxonomy\TermInterface $series */
      $series = reset($series_value);
      $categories = $series->get('field_category')->referencedEntities();
    }
    else {
      $categories = $node->get('field_moj_top_level_categories')->referencedEntities();
    }

    // If no categories found, return the breadcrumb with just "Home" link.
    if (empty($categories)) {
      return $breadcrumb;
    }

    /** @var \Drupal\taxonomy\TermInterface $category */
    $category = reset($categories);
    $parents = $this->entityTypeManager->getStorage('taxonomy_term')->loadAllParents($category->id());
    $breadcrumb_terms = array_reverse($parents);
    if ($series) {
      $breadcrumb_terms[] = $series;
    }
    foreach ($breadcrumb_terms as $term) {
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
