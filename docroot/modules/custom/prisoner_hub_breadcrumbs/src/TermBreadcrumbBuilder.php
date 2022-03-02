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
 * Class TermBreadcrumbBuilder.
 *
 * Build breadcrumbs for taxonomy terms.
 * @see \Drupal\prisoner_hub_breadcrumbs\TermBreadcrumbBuilder
 */
class TermBreadcrumbBuilder implements BreadcrumbBuilderInterface {
  use StringTranslationTrait;

  /**
   * The entity repository manager.
   *
   * @var \Drupal\Core\Entity\EntityRepositoryInterface
   */
  protected $entityRepository;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs the TermBreadcrumbBuilder.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The entity repository.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, EntityRepositoryInterface $entity_repository) {
    $this->entityTypeManager = $entity_type_manager;
    $this->entityRepository = $entity_repository;
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
   */
  public function build(RouteMatchInterface $route_match) {
    $breadcrumb = new Breadcrumb();
    $breadcrumb->addLink(Link::createFromRoute($this->t('Home'), '<front>'));
    /** @var TermInterface $term */
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
