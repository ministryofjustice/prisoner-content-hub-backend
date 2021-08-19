<?php
namespace Drupal\prisoner_hub_taxonomy_sorting\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\taxonomy\TermInterface;
use Drupal\views\Views;

/**
 * Provides route responses for the prisoner_hub_taxonomy_sorting module.
 */
class SeriesPageController extends ControllerBase {

  /**
   * Returns a View to display on the page.
   *
   * The view "display_id" is determined from the value of field_sort_by on the
   * series Taxonomy term.
   * Each display in the View has different sorting applied to it.
   *
   * @return array
   *   A simple renderable array.
   */
  public function renderPage(TermInterface $taxonomy_term) {
    $view_name = 'series_taxonomy_term_content_sorting';
    $view_display_id = 'embed_1';
    $view = Views::getView($view_name);
    return $view->buildRenderable($view_display_id, [$taxonomy_term->id()]);
  }

  /**
   * {@inheritdoc}
   */
  public function titleCallback(TermInterface $taxonomy_term) {
    return $this->t('%label: Change episode order', ['%label' => $taxonomy_term->label()]);
  }

  /**
   * Hide the content sorting tab on Taxonomy terms where we don't apply it.
   */
  public function access(TermInterface $taxonomy_term) {
    return AccessResult::allowedIf($taxonomy_term->bundle() === 'series');
  }
}
